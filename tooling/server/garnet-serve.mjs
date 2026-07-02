#!/usr/bin/env node
// @ts-check
/**
 * Garnet dev server — cross-platform replacement for the bundled nginx.
 *
 * Architecture (mirrors what nginx + the php-cgi pool did, minus the
 * vendored binary):
 *
 *   browser ──▶ Node :8001 ──┬─ static file under <public>/ ? stream it
 *                            └─ else proxy to one of N `php -S` workers
 *                               on 127.0.0.1:<basePort..basePort+N-1>
 *
 * Worker routing (this is what keeps Playwright's per-worker DB isolation
 * working — see WorkerScopeMiddleware):
 *   - request carries `X-Test-Worker: <i>`  → pinned to worker <i>
 *   - `X-Test-Worker: template`              → worker 0
 *   - no / unknown header                    → least-in-flight round-robin
 *
 * No npm dependencies — native `http`, `fs`, `child_process` only. Node is
 * already required for the frontend build (rspack), so this adds no new
 * prerequisite.
 *
 * Usage:
 *   node garnet-serve.mjs --port=8001 --workers=32 --base-port=8011 \
 *        --public=/abs/path/to/Public --php-bin=php [--router=/abs/router.php]
 *
 * Ctrl-C (SIGINT) / SIGTERM tears down the whole worker pool.
 */

import http from 'node:http';
import { createReadStream, existsSync, statSync, mkdirSync } from 'node:fs';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// ── Args ───────────────────────────────────────────────────────────────────
const args = Object.fromEntries(
	process.argv.slice(2).map((a) => {
		const m = a.match(/^--([^=]+)=(.*)$/);
		return m ? [m[1], m[2]] : [a.replace(/^--/, ''), true];
	}),
);

const PORT       = parseInt(String(args.port ?? '8001'), 10);
const WORKERS    = Math.max(1, parseInt(String(args.workers ?? '32'), 10));
const BASE_PORT  = parseInt(String(args['base-port'] ?? '8011'), 10);
const PUBLIC_DIR = path.resolve(String(args.public ?? process.cwd()));
const PHP_BIN    = String(args['php-bin'] ?? 'php');
const ROUTER     = path.resolve(String(args.router ?? path.join(__dirname, 'php-worker-router.php')));
const DEBUG      = !!args.debug;

if (!existsSync(PUBLIC_DIR)) {
	console.error(`[garnet-serve] --public dir not found: ${PUBLIC_DIR}`);
	process.exit(1);
}

const LOG_DIR = path.join(__dirname, 'logs');
mkdirSync(LOG_DIR, { recursive: true });

// ── opcache / mysqli tuning, identical intent to the old php-cgi pool ───────
// `php -S` runs under the CLI SAPI, so opcache needs enable_cli=1 (not just
// enable). validate_timestamps keeps edit-and-reload working in dev.
const PHP_INI = [
	'opcache.enable=1',
	'opcache.enable_cli=1',
	'opcache.memory_consumption=512',
	'opcache.interned_strings_buffer=32',
	'opcache.max_accelerated_files=50000',
	'opcache.validate_timestamps=1',
	'opcache.revalidate_freq=2',
	'opcache.save_comments=1',
	'opcache.fast_shutdown=1',
	'opcache.jit=disable',
	'opcache.jit_buffer_size=0',
	'log_errors=1',
	'display_errors=0',
	'realpath_cache_size=512M',
	'realpath_cache_ttl=7200',
	'mysqli.default_host=127.0.0.1',
	'mysqli.allow_persistent=1',
	'mysqli.max_persistent=-1',
	'mysqli.max_links=-1',
];

// ── Spawn the php -S worker pool ────────────────────────────────────────────
/** @type {{ port:number, proc:import('node:child_process').ChildProcess, inflight:number }[]} */
const pool = [];

function spawnWorker(index) {
	const port = BASE_PORT + index;
	const iniArgs = PHP_INI.flatMap((kv) => ['-d', kv]);
	const errLog = path.join(LOG_DIR, `php-worker-${port}.log`);

	const phpArgs = [
		...iniArgs,
		'-d', `error_log=${errLog}`,
		'-S', `127.0.0.1:${port}`,
		'-t', PUBLIC_DIR,
		ROUTER,
	];

	const proc = spawn(PHP_BIN, phpArgs, {
		cwd: PUBLIC_DIR,
		stdio: ['ignore', 'ignore', 'ignore'],
		windowsHide: true,
	});

	const entry = { port, proc, inflight: 0 };

	proc.on('exit', (code, signal) => {
		if (shuttingDown) return;
		// A worker died mid-run (JIT crash, OOM, fatal). Respawn it so the
		// pool self-heals — same resilience nginx got from max_fails=0.
		console.error(`[garnet-serve] worker :${port} exited (code=${code} signal=${signal}) — respawning`);
		const i = pool.findIndex((w) => w === entry);
		if (i >= 0) pool[i] = spawnWorker(index);
	});

	return entry;
}

for (let i = 0; i < WORKERS; i++) {
	pool.push(spawnWorker(i));
}

// ── Worker selection ────────────────────────────────────────────────────────
function pickWorker(req) {
	const hdr = req.headers['x-test-worker'];
	const raw = Array.isArray(hdr) ? hdr[0] : hdr;

	if (raw !== undefined && raw !== '') {
		if (raw === 'template') return pool[0];
		const idx = Number(raw);
		if (Number.isInteger(idx) && idx >= 0 && idx < pool.length) {
			return pool[idx];
		}
		// Unknown value falls through to the round-robin pool.
	}

	// Least-in-flight (mirrors nginx `least_conn`).
	let best = pool[0];
	for (const w of pool) {
		if (w.inflight < best.inflight) best = w;
	}
	return best;
}

// ── Static file serving ─────────────────────────────────────────────────────
const MIME = {
	'.html': 'text/html; charset=utf-8',
	'.htm': 'text/html; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.mjs': 'text/javascript; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.json': 'application/json; charset=utf-8',
	'.map': 'application/json; charset=utf-8',
	'.svg': 'image/svg+xml',
	'.ico': 'image/x-icon',
	'.png': 'image/png',
	'.jpg': 'image/jpeg',
	'.jpeg': 'image/jpeg',
	'.gif': 'image/gif',
	'.webp': 'image/webp',
	'.avif': 'image/avif',
	'.woff': 'font/woff',
	'.woff2': 'font/woff2',
	'.ttf': 'font/ttf',
	'.eot': 'application/vnd.ms-fontobject',
	'.txt': 'text/plain; charset=utf-8',
	'.xml': 'application/xml; charset=utf-8',
	'.wasm': 'application/wasm',
	'.pdf': 'application/pdf',
};

/** Resolve a request path to a real file under PUBLIC_DIR, or null. Guards traversal. */
function resolveStatic(urlPath) {
	if (urlPath === '/' || urlPath === '') return null;
	let decoded;
	try {
		decoded = decodeURIComponent(urlPath.split('?')[0]);
	} catch {
		return null;
	}
	// Normalise and confine to PUBLIC_DIR (no `..` escape).
	const full = path.normalize(path.join(PUBLIC_DIR, decoded));
	if (full !== PUBLIC_DIR && !full.startsWith(PUBLIC_DIR + path.sep)) return null;
	try {
		const st = statSync(full);
		if (st.isFile()) return { full, size: st.size, mtime: st.mtimeMs };
	} catch {
		// not a file
	}
	return null;
}

function serveStatic(res, file, method) {
	const ext = path.extname(file.full).toLowerCase();
	const type = MIME[ext] ?? 'application/octet-stream';
	// Content-hashed gen assets can be cached hard; everything else short.
	const immutable = /\.[0-9a-f]{8,}\.(gen\.)?(js|css)$/i.test(file.full)
		|| /[.-][0-9a-f]{8,}\./i.test(path.basename(file.full));
	const headers = {
		'Content-Type': type,
		'Content-Length': String(file.size),
		'Cache-Control': immutable ? 'public, max-age=31536000, immutable' : 'no-cache',
	};
	res.writeHead(200, headers);
	if (method === 'HEAD') {
		res.end();
		return;
	}
	createReadStream(file.full).pipe(res);
}

// ── Proxy to a php -S worker ────────────────────────────────────────────────
function proxy(req, res) {
	const worker = pickWorker(req);
	worker.inflight++;

	const options = {
		host: '127.0.0.1',
		port: worker.port,
		method: req.method,
		path: req.url,
		headers: { ...req.headers, host: `127.0.0.1:${worker.port}` },
	};

	const upstream = http.request(options, (upRes) => {
		res.writeHead(upRes.statusCode ?? 502, upRes.headers);
		upRes.pipe(res);
		upRes.on('end', () => { worker.inflight--; });
	});

	upstream.on('error', (err) => {
		worker.inflight--;
		if (!res.headersSent) {
			res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
		}
		res.end(`502 Bad Gateway — php worker :${worker.port} unreachable\n${DEBUG ? err.stack : ''}`);
	});

	req.pipe(upstream);
}

// ── Front HTTP server ───────────────────────────────────────────────────────
const server = http.createServer((req, res) => {
	const method = req.method ?? 'GET';
	const urlPath = (req.url ?? '/').split('?')[0];

	// Static fast-path for safe methods only; dynamic always goes to PHP.
	if (method === 'GET' || method === 'HEAD') {
		const file = resolveStatic(urlPath);
		if (file) {
			serveStatic(res, file, method);
			return;
		}
	}
	proxy(req, res);
});

server.on('clientError', (err, socket) => {
	if (socket.writable) socket.end('HTTP/1.1 400 Bad Request\r\n\r\n');
});

server.listen(PORT, '0.0.0.0', () => {
	const end = BASE_PORT + WORKERS - 1;
	console.log(
		`Serving via Node :${PORT} → pool of ${WORKERS} \`php -S\` workers ` +
		`[${BASE_PORT}..${end}] (opcache on)${DEBUG ? ' (debug)' : ''}`,
	);
	console.log(`Static root: ${PUBLIC_DIR}`);
	console.log('Pool is live. Press Ctrl-C to stop.');
});

// ── Graceful shutdown ───────────────────────────────────────────────────────
let shuttingDown = false;
function shutdown() {
	if (shuttingDown) return;
	shuttingDown = true;
	console.log('\n[garnet-serve] shutting down — killing worker pool…');
	for (const w of pool) {
		try { w.proc.kill('SIGKILL'); } catch { /* already gone */ }
	}
	server.close(() => process.exit(0));
	// Hard exit if close hangs on lingering keep-alive sockets.
	setTimeout(() => process.exit(0), 1500).unref();
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
