/**
 * Log a page in under one of the bare framework's two built-in "roles":
 *
 *   - 'account' — a generic authenticated end-user via the Auth module's
 *     real email-code flow (`registerAccount` in auth.ts).
 *   - 'admin'   — the Garnet admin panel (`/__garnet/`) via the one-shot
 *     CLI token flow (`php garnet admin` — see
 *     `Kernel/Io/GarnetCli/GarnetAdminCommand.php` and docs/cli.md's
 *     "Admin panel" section). The token is generated once per worker and
 *     applied by hitting `/__garnet/?token=<token>`, which the admin app
 *     exchanges for the `garnet_admin` cookie
 *     (`Kernel/Io/GarnetCli/Admin/AdminApp.php`).
 *
 * There is no dev-login shortcut and no custom business roles
 * (moderator/expert/owner/…) in the bare framework — those belong to
 * whatever roles YOUR app defines on top of Auth. Extend this file with
 * your own role→login mapping once your app has real roles.
 */
import { Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import * as path from 'node:path';
import { registerAccount } from './auth';

const APP_DIR = process.env.PW_APP_DIR ?? process.env.GARNET_APP_DIR ?? path.resolve(__dirname, '..', '..');

/**
 * Generate a fresh admin token via the CLI and return it. Each call
 * mints a new token file (`.garnet_admin` in the app dir) — safe to call
 * once per worker.
 */
export function generateAdminToken(): string {
	const out = execFileSync('php', ['garnet', 'admin'], { cwd: APP_DIR, encoding: 'utf-8' });
	const match = out.match(/token=([a-f0-9]+)/i);
	if (!match) {
		throw new Error(`generateAdminToken: could not parse token from CLI output:\n${out}`);
	}
	return match[1];
}

/**
 * Log `page` into the Garnet admin panel by visiting the token URL.
 * Leaves the page on `/__garnet/`.
 */
export async function adminLogin(page: Page, token?: string): Promise<void> {
	const t = token ?? generateAdminToken();
	await page.goto(`/__garnet/?token=${t}`);
}

/**
 * Log `page` in as a generic authenticated account, registering it via
 * the real email-code flow on first use.
 */
export async function accountLogin(page: Page, login: string): Promise<void> {
	await registerAccount(page, login);
}
