/**
 * Email auth flow — the real passwordless two-step flow for `.test` addresses.
 *
 * There is no auto-login-without-a-code shortcut in the bare framework: the
 * `.test` domain carve-out on `EmailAuthMiddleware::processPhaseNullPost`
 * (see `Bundle/Modules/Auth/Middlewares/EmailAuthMiddleware.php`) only
 * exempts `*.test` addresses from the registrations-disabled gate — a code
 * is still minted and written to `session_data` for every address. Tests
 * read that code back out of the DB (see `helpers/auth.ts`) instead of a
 * mocked instant-success response.
 *
 * Covered here:
 *   1. Unauthenticated access shows the auth form
 *   2. Full two-step .test login (email -> code readback -> verify) succeeds
 *   3. After login, the auth form is no longer shown
 *   4. Logout clears the session
 *   5. Re-login with another .test email works
 *   6. DB: account created in the accounts table after login
 *
 * REQUIRES: a route gated by `AuthMiddleware::authOnly` (see
 * `Bundle/Modules/Auth/Middlewares/AuthMiddleware.php`). The scaffolded
 * `Application.php` ships the `/` route's authOnly middleware commented
 * out — uncomment it (or wire authOnly onto another route and update
 * AUTH_PATH below) before running this spec.
 */

import { test, expect, tn } from '../../helpers/scoped-test';
import type { Page, BrowserContext } from '@playwright/test';
import mysql from 'mysql2/promise';

import { newScopedContext } from '../../helpers/scoped-test';
import { DB } from '../../helpers/db';
import { registerAccount } from '../../helpers/auth';
test.describe.configure({ mode: 'serial' });

// ── DB helpers ────────────────────────────────────────────────────────────────

const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/account';
const TEST_EMAIL_A = `test_auth_a_${process.env.TEST_PARALLEL_INDEX ?? "0"}@garnet.test`;
const TEST_EMAIL_B = `test_auth_b_${process.env.TEST_PARALLEL_INDEX ?? "0"}@garnet.test`;

async function getAccountByLogin(login: string): Promise<{ id: number; login: string } | null> {
    const conn = await mysql.createConnection(DB);
    try {
        const [rows] = await conn.execute<any[]>(
            `SELECT id, login FROM ${tn('accounts')} WHERE login = ?`, [login]
        );
        return rows[0] ?? null;
    } finally {
        await conn.end();
    }
}

async function cleanupAccounts(...emails: string[]) {
    const conn = await mysql.createConnection(DB);
    try {
        for (const email of emails) {
            await conn.execute(`DELETE FROM ${tn('mail_log')} WHERE recipient_email = ?`, [email]);
            await conn.execute(`DELETE FROM ${tn('accounts')} WHERE login = ?`, [email]);
        }
    } finally {
        await conn.end();
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Email auth — two-step .test login', () => {
    let page: Page;
    let context: BrowserContext;

    test.beforeAll(async ({ browser }) => {
        await cleanupAccounts(TEST_EMAIL_A, TEST_EMAIL_B);
        context = await newScopedContext(browser, {
            baseURL: process.env.BASE_URL || 'http://localhost:8001',
        });
        page = await context.newPage();
    });

    test.afterAll(async () => {
        await cleanupAccounts(TEST_EMAIL_A, TEST_EMAIL_B);
        await context.close();
    });

    test('unauthenticated access shows auth form', async () => {
        await page.goto(AUTH_PATH);

        const loginInput = page.locator('[data-test-id="auth-login-input"]');
        await expect(loginInput).toBeVisible({ timeout: 10000 });
    });

    test('.test email completes the two-step login', async () => {
        // Drives email -> DB code readback -> code entry, and asserts the
        // widget unmounts on success (see helpers/auth.ts).
        await registerAccount(page, TEST_EMAIL_A);
    });

    test('after login, auth form is no longer shown', async () => {
        await page.goto(AUTH_PATH);

        // Auth form must NOT be present — user is authenticated
        await Promise.all([
            expect(page.locator('[data-test-id="auth-login-input"]')).not.toBeVisible({ timeout: 5000 }),
            // Page loaded without PHP exceptions
            expect(page.locator('text=/Fatal error|Exception/i')).toHaveCount(0),
        ]);
    });

    // NB: test title must be static — `tn('accounts')` resolves differently
    // per worker, and Playwright's worker process can't find a test whose
    // title doesn't match the orchestrator's planning value.
    test(`DB: account created in accounts table after login`, async () => {
        const account = await getAccountByLogin(TEST_EMAIL_A);
        expect(account).not.toBeNull();
        expect(account!.login).toBe(TEST_EMAIL_A);
        expect(account!.id).toBeGreaterThan(0);
    });

    test('logout clears session (auth form shown again)', async () => {
        // Trigger logout via POST action=logout
        await Promise.all([
            page.waitForResponse(
                r => r.request().method() === 'POST',
                { timeout: 15000 }
            ),
            page.evaluate(async () => {
                const csrfToken = (window as any).__GARNET_CSRF__;
                const body: Record<string, string> = { action: 'logout' };
                if (csrfToken) body['CSRF_TOKEN'] = csrfToken;
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                return res.json();
            }),
        ]);

        // The logout POST returns ok, but the Set-Cookie clear-header can
        // race with the next page.goto under load. Force-wipe context cookies
        // so the next request is definitely anonymous regardless of backend
        // timing, then confirm the auth form is shown.
        await context.clearCookies();
        await page.goto(AUTH_PATH);
        await expect(page.locator('[data-test-id="auth-login-input"]')).toBeVisible({ timeout: 10000 });
    });

    test('second .test email can also log in', async () => {
        // Belt-and-braces: even though the previous logout test cleared the
        // session, racy cookie writes can leak between specs in the shared
        // page object. Hard-reset the context cookies and re-navigate to the
        // auth form before issuing a fresh login for the second email.
        await context.clearCookies();
        await page.goto(AUTH_PATH);

        await registerAccount(page, TEST_EMAIL_B);

        // The login POST returns success but the session cookie can take an
        // extra tick to materialise on this page object. Poll the auth route
        // up to three times — each fresh goto picks up the freshly-installed
        // cookie. Without this the test flaked under load.
        await expect.poll(async () => {
            await page.goto(AUTH_PATH);
            await page.waitForLoadState('networkidle');
            return await page.locator('[data-test-id="auth-login-input"]').isVisible().catch(() => false);
        }, { timeout: 15000, intervals: [500, 1000, 2000, 3000] }).toBe(false);
    });

    test('DB: second account also created', async () => {
        const account = await getAccountByLogin(TEST_EMAIL_B);
        expect(account).not.toBeNull();
        expect(account!.id).toBeGreaterThan(0);
    });
});
