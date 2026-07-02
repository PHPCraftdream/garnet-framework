/**
 * Drive the framework's real passwordless email-auth flow
 * (`Bundle/Modules/Auth/Middlewares/EmailAuthMiddleware.php`) from a spec.
 *
 * There is no `/dev-login` shortcut in the bare framework — that's an
 * app-level convenience some apps add on top. Instead this drives the
 * actual two-step flow (email → mailed code → verify) and reads the code
 * back from the DB, since `*.test` addresses never receive real mail
 * inside a TestScope (`Kernel/Core/Env/TestScope.php`) but the code is
 * still written to `session_data` (param `auth_code`) exactly like a
 * real login.
 *
 * Requires the app to have wired `EmailAuthMiddleware::authOnly` onto
 * some route (see `Bundle/Modules/Auth/README.md`); the scaffolded
 * `Application.php` ships this commented out by default — uncomment it
 * (or wire it on your own route) before using these helpers.
 */
import { Page, expect } from '@playwright/test';
import mysql from 'mysql2/promise';

import { tn } from './scoped-test';
import { DB as DB_CONFIG } from './db';

// Route where the auth widget (`Auth2.tsx`) is mounted. Override via
// PW_AUTH_PATH if your app mounts it somewhere other than `/`.
const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/';

/**
 * Tick the PD-consent checkbox so the auth submit button enables. The
 * button stays disabled until `pdConsent` is true — checking the box
 * triggers the `start-session` POST that mints the CSRF cookie. Waits
 * for the submit button to become enabled, which is the visible proof
 * that the start-session round-trip completed.
 */
export async function tickPdConsent(page: Page): Promise<void> {
	const consent = page.locator('[data-test-id="auth-consent-pd"]');
	if (!(await consent.isVisible({ timeout: 2000 }).catch(() => false))) {
		return;
	}
	if (!(await consent.isChecked())) {
		await consent.check();
	}
	const submitBtn = page.locator('[data-test-id="auth-submit-btn"]');
	await submitBtn.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
	const handle = await submitBtn.elementHandle();
	if (handle) {
		await page.waitForFunction(
			(el) => !(el as HTMLButtonElement).disabled,
			handle,
			{ timeout: 5000 },
		).catch(() => {});
	}
}

/**
 * Read the mailed auth code back out of `session_data` for the session
 * cookie currently held by `page`'s context. The code is written by
 * `EmailAuthMiddleware::sendCode()` under param `auth_code`.
 */
async function readAuthCodeFromDb(page: Page): Promise<string> {
	const cookies = await page.context().cookies();
	const sessionCookie = cookies.find((c) => /session/i.test(c.name));
	if (!sessionCookie) {
		throw new Error('readAuthCodeFromDb: no session cookie on the page context');
	}

	const connection = await mysql.createConnection(DB_CONFIG);
	try {
		const [rows] = await connection.execute<any[]>(
			`SELECT sd.value FROM ${tn('session_data')} sd
			 JOIN ${tn('session')} s ON s.id = sd.sessionId
			 WHERE s.id = ? AND sd.param = 'auth_code'`,
			[sessionCookie.value]
		);
		if (rows.length === 0) {
			throw new Error('readAuthCodeFromDb: no auth_code found for this session — was sendCode() reached?');
		}
		return rows[0].value as string;
	} finally {
		await connection.end();
	}
}

/**
 * Register (or log back in as, if it already exists) `login` through the
 * real email-auth widget: fill the email, accept PD consent, submit,
 * read the mailed code back from the DB, and enter it.
 *
 * `Auth2.tsx` is a single-form widget — the SAME `auth-login-input` /
 * `auth-submit-btn` pair is reused for both the email phase and the
 * code phase (only the placeholder and input type change). There is no
 * separate code-input selector.
 *
 * `login` must end in `.test` for this to work outside an active
 * TestScope registration gate — see `EmailAuthMiddleware::processPhaseNullPost()`.
 */
export async function registerAccount(page: Page, login: string): Promise<void> {
	await page.context().clearCookies();
	await page.goto(AUTH_PATH);

	const input = page.locator('[data-test-id="auth-login-input"]');
	const submitBtn = page.locator('[data-test-id="auth-submit-btn"]');

	await expect(input).toBeVisible({ timeout: 20000 });
	await input.fill(login);
	await tickPdConsent(page);
	await expect(submitBtn).toBeEnabled({ timeout: 5000 });
	await submitBtn.click();

	// Email-phase POST succeeded and re-rendered the widget into the
	// code-entry phase — mints session_data.auth_code server-side. Wait
	// for the (same) input to clear/re-render, then read the code back.
	await expect(input).toBeVisible({ timeout: 20000 });
	const code = await readAuthCodeFromDb(page);
	await input.fill(code);
	await submitBtn.click();

	// On success the widget unmounts entirely.
	await page.waitForFunction(
		() => document.querySelector('[data-test-id="auth-submit-btn"]') === null,
		{ timeout: 30000 }
	);
	await page.goto('/');
	console.log(`Registration successful for ${login}`);
}

/** Alias — the flow is identical whether the account already exists. */
export const loginAccount = registerAccount;

/**
 * Clear test accounts and their sessions for the current worker scope.
 * Without an explicit login, wipes every account matching `%.test` (the
 * TestScope carve-out domain) but never touches real accounts.
 */
export async function clearTestData(login?: string): Promise<void> {
	const connection = await mysql.createConnection(DB_CONFIG);
	const pattern = login ?? '%.test';
	const op = login ? '=' : 'LIKE';
	try {
		try {
			const [sessionRows] = await connection.execute<any[]>(
				`SELECT DISTINCT s.id FROM ${tn('session')} s
				 JOIN ${tn('session_data')} sd ON sd.sessionId = s.id
				 WHERE sd.param = 'auth_login' AND sd.value ${op} ?`,
				[pattern]
			);
			if (sessionRows.length > 0) {
				const sessionIds = sessionRows.map((r) => r.id);
				await connection.execute(
					`DELETE FROM ${tn('session_data')} WHERE sessionId IN (${sessionIds.map(() => '?').join(',')})`,
					sessionIds
				);
				await connection.execute(
					`DELETE FROM ${tn('session')} WHERE id IN (${sessionIds.map(() => '?').join(',')})`,
					sessionIds
				);
			}
		} catch (e) {
			console.log('Session cleanup warning:', (e as any)?.message);
		}
		await connection.execute(
			`DELETE ad FROM ${tn('accounts_data')} ad JOIN ${tn('accounts')} a ON a.id = ad.account_id WHERE a.login ${op} ?`,
			[pattern]
		);
		await connection.execute(
			`DELETE FROM ${tn('accounts')} WHERE login ${op} ?`,
			[pattern]
		);
		console.log(login ? `Test data cleared for: ${login}` : 'All test data cleared');
	} catch (e) {
		console.error('Error clearing test data:', e);
	} finally {
		await connection.end();
	}
}
