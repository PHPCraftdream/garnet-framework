#!/usr/bin/env node
// @ts-check
/**
 * Scaffold a throwaway app from Templates/Application, build it, serve it,
 * run the framework's e2e Playwright suite against it, then tear everything
 * down — so `composer test:e2e` reproduces exactly what CI's `e2e` job does,
 * without a developer having to scaffold/serve/clean up by hand.
 *
 * No npm dependencies — native `child_process`/`fs`/`http` only, same
 * convention as tooling/server/garnet-serve.mjs.
 *
 * Usage:
 *   node tooling/scripts/test-e2e.mjs [--keep]
 *   composer test:e2e -- --keep
 *
 *   --keep   Don't delete the throwaway app afterwards (for debugging).
 */

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frameworkRoot = path.resolve(__dirname, '..', '..');
const keep = process.argv.includes('--keep');

const SAFE_SPECS = [
	'specs/smoke.spec.ts',
	'specs/framework-bundle/api-no-react-import.spec.ts',
	'specs/framework-bundle/cron-cli.spec.ts',
	'specs/framework-bundle/html-lang.spec.ts',
	'specs/framework-bundle/auth-page-chrome.spec.ts',
];

/** @param {string} cmd @param {string[]} args @param {import('node:child_process').SpawnSyncOptions} [opts] */
function run(cmd, args, opts = {}) {
	console.log(`==> ${cmd} ${args.join(' ')}`);
	// A single shell-joined string (rather than shell:true + an args array)
	// avoids Node's DEP0190 warning while still resolving .cmd/.bat shims
	// (npx, composer) on Windows.
	const result = spawnSync([cmd, ...args].join(' '), { stdio: 'inherit', shell: true, ...opts });
	if (result.status !== 0) {
		throw new Error(`Command failed (exit ${result.status}): ${cmd} ${args.join(' ')}`);
	}
}

/** @param {string} url @param {number} timeoutMs */
async function waitForServer(url, timeoutMs) {
	// Shells out to curl rather than using node:http — on this stack
	// `http.get('http://localhost:…')` can stall indefinitely (likely an
	// IPv6-first `localhost` resolution racing a server bound to IPv4 only),
	// while `curl` against the same URL is reliably instant.
	const nullDevice = process.platform === 'win32' ? 'NUL' : '/dev/null';
	const deadline = Date.now() + timeoutMs;
	while (Date.now() < deadline) {
		const result = spawnSync('curl', ['--fail', '--silent', '--output', nullDevice, url]);
		if (result.status === 0) {
			return;
		}
		await new Promise((resolve) => setTimeout(resolve, 1000));
	}
	throw new Error('Server did not come up in time.');
}

async function main() {
	// mkdtempSync creates the leaf dir itself, but `app:create` refuses to
	// scaffold into a dir that already exists — so the temp dir is the
	// PARENT, and the app lives in a not-yet-created child of it.
	const tmpParent = mkdtempSync(path.join(os.tmpdir(), 'garnet-test-e2e-'));
	const appDir = path.join(tmpParent, 'E2eLocal');
	/** @type {import('node:child_process').ChildProcess | null} */
	let server = null;

	const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

	const cleanup = async () => {
		if (server && server.pid && !server.killed) {
			// `garnet serve` spawns its own child tree (the Node dev server +
			// a pool of `php -S` workers) — server.kill() only signals the
			// immediate child. On Windows, taskkill's /t recurses into the
			// whole tree; without it the workers keep file handles open in
			// the temp app dir and rmSync below fails with EPERM.
			if (process.platform === 'win32') {
				spawnSync('taskkill', ['/pid', String(server.pid), '/t', '/f'], { stdio: 'ignore' });
			} else {
				server.kill();
			}
		}
		if (!keep && existsSync(tmpParent)) {
			// Give Windows a moment to actually release file handles after
			// the kill above before trying to remove the directory.
			const deadline = Date.now() + 5000;
			for (;;) {
				try {
					rmSync(tmpParent, { recursive: true, force: true });
					break;
				} catch (err) {
					if (Date.now() > deadline) {
						console.warn(`Could not fully clean up ${tmpParent}: ${err.message ?? err}`);
						break;
					}
					await sleep(300);
				}
			}
		}
	};

	try {
		console.log(`==> Scaffolding throwaway app at ${appDir}`);
		run('php', ['bin/garnet', 'app:create', 'E2eLocal', `--target=${appDir}`, '--quiet'], {
			cwd: frameworkRoot,
			env: { ...process.env, GARNET_SKIP_NODE_SETUP: '1' },
		});

		console.log('==> Installing app node deps + Playwright browsers');
		run('php', ['garnet', 'setup', '--skip-composer'], { cwd: appDir });

		console.log('==> Building the app');
		run('php', ['garnet', 'build'], { cwd: appDir });

		console.log('==> Starting the server');
		// No shell:true here (matches garnet-serve.mjs's own convention) — a
		// shell-wrapped spawn makes `server.kill()` only kill the shell, not
		// the `garnet serve` process tree it launches (the Node dev server +
		// its pool of `php -S` workers), which then holds file locks in the
		// temp app dir and makes cleanup's rmSync fail with EPERM.
		server = spawn('php', ['garnet', 'serve'], { cwd: appDir, stdio: 'inherit' });

		await waitForServer('http://localhost:8001/', 30_000);

		console.log('==> Server is up — running the framework e2e suite');
		// Only the DB-free subset — no MySQL service here. The 8 other
		// ported framework-bundle specs (auth-email-preserved-on-error,
		// auth-magic-link-defer, magic-link, registration-gate,
		// consent-csrf, email-auth, email-link-csrf, cookie-samesite) are
		// no longer blocked by missing auth wiring (the scaffold wires
		// EmailAuthMiddleware::authOnly onto /account by default — see
		// Templates/Application/Application.php::runWebApp), they just need
		// a real migrated database. See the matching comment in
		// Templates/Application/.github/workflows/ci.yml's `e2e` job and
		// docs/e2e-testing.md for how to run them against a DB-enabled app.
		run('npx', ['playwright', 'test', ...SAFE_SPECS, '--reporter=list'], {
			cwd: path.join(appDir, 'Tests'),
		});
	} finally {
		await cleanup();
	}
}

main().catch((err) => {
	console.error(err.message ?? err);
	process.exitCode = 1;
});
