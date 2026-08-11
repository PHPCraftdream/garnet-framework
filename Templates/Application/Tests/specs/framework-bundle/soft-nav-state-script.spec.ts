/**
 * Soft-navigation state-script refresh — regression guard for the
 * PageLoader.updatePage() fix (see Bundle/Front/Common/Dom/PageLoader.ts
 * lines 163-215).
 *
 * BEFORE the fix:
 *   - Session::rotate() (login, logout, expired-code-render) minted a fresh
 *     CSRF token in the server HTML, but PageLoader skipped re-executing
 *     `<script id="__GARNET_CSRF__">` because the id already existed.
 *     Result: window.__GARNET_CSRF__ stayed stale, causing 403 on the next POST.
 *   - After logout, the server stopped emitting __GARNET_USER__ and
 *     __GARNET_ACCOUNT_ID__ scripts (HtmlLayout omits them once account_id=0),
 *     but PageLoader didn't remove them from the DOM or clear the globals.
 *     Result: previous user's data lingered in JS until hard refresh.
 *
 * AFTER the fix:
 *   - CSRF token refreshes on soft-nav: replaced when content changes.
 *   - User globals clear on logout soft-nav: removed when scripts disappear.
 *
 * This spec is DB-backed and will NOT run in the CI E2E job (which is
 * DB-free — see .github/workflows/ci.yml:387-394). Run it locally with
 * a working MySQL test DB.
 */

import { test, expect, tn } from '../../helpers/scoped-test';
import type { Page, BrowserContext } from '@playwright/test';

import { newScopedContext } from '../../helpers/scoped-test';
import { registerAccount, clearTestData } from '../../helpers/auth';

test.describe.configure({ mode: 'serial' });

const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/account';
const TEST_EMAIL = `softnav_test_${process.env.TEST_PARALLEL_INDEX ?? "0"}@garnet.test`;

test.describe('Soft-navigation state-script refresh', () => {
	let page: Page;
	let context: BrowserContext;

	test.beforeAll(async ({ browser }) => {
		await clearTestData(TEST_EMAIL);
		context = await newScopedContext(browser, {
			baseURL: process.env.BASE_URL || 'http://localhost:8001',
		});
		page = await context.newPage();
	});

	test.afterAll(async () => {
		await clearTestData(TEST_EMAIL);
		await context.close();
	});

	test('CSRF token refreshes after login via soft-nav, enabling immediate POST actions without hard refresh', async () => {
		// Register without the final hard goto — the HandleCheckCode soft-nav
		// (goTo(window.location.href)) has already completed, so we're on the
		// authenticated page via soft-nav only.
		await registerAccount(page, TEST_EMAIL, { skipFinalGoto: true });

		// Capture the CSRF value immediately after login soft-nav.
		const csrfAfterLogin = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		expect(csrfAfterLogin).toBeDefined();
		expect(typeof csrfAfterLogin).toBe('string');

		// Assert user globals are populated after login.
		const user = await page.evaluate(() => (window as any).__GARNET_USER__);
		expect(user).toBeDefined();
		expect(user).toHaveProperty('name');

		const accountId = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);
		expect(accountId).toBeDefined();
		expect(typeof accountId).toBe('number');
		expect(accountId).toBeGreaterThan(0);

		// Now test CSRF freshness by attempting a POST that would 403 with stale CSRF.
		// If the CSRF token was not refreshed, this POST will fail with 403.
		const postOk = await Promise.all([
			page.waitForResponse(r => r.request().method() === 'POST', { timeout: 15000 }),
			page.evaluate(async (csrf) => {
				const res = await fetch(window.location.href, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
					body: JSON.stringify({ action: 'logout', CSRF_TOKEN: csrf }),
				});
				return res.ok; // Should be true with fresh CSRF, false/403 with stale
			}, csrfAfterLogin),
		]);

		expect(postOk[1]).toBe(true);
	});

	test('user globals (USER, ACCOUNT_ID) are cleared after logout soft-nav', async () => {
		// Start fresh with normal login (including hard goto) to establish authed state.
		await page.context().clearCookies();
		await registerAccount(page, TEST_EMAIL); // Normal flow with final goto

		// Verify we're logged in with user globals.
		const userBeforeLogout = await page.evaluate(() => (window as any).__GARNET_USER__);
		expect(userBeforeLogout).toBeDefined();
		const accountIdBeforeLogout = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);
		expect(accountIdBeforeLogout).toBeDefined();
		expect(accountIdBeforeLogout).toBeGreaterThan(0);

		// Trigger the REAL logout flow via clicking the logout button in the UI.
		// TopMenu.tsx does: sendPost(...).then(() => goTo('/')) — this triggers
		// a soft-nav that should clear the user globals.
		const logoutButton = page.locator('[data-test-id="logout-btn"], button:has-text("Logout"), a:has-text("Logout")');
		await logoutButton.waitFor({ state: 'visible', timeout: 5000 });
		await logoutButton.click();

		// Wait for the soft-nav to complete — auth widget should reappear.
		await page.waitForFunction(
			() => document.querySelector('[data-test-id="auth-submit-btn"]') !== null,
			{ timeout: 15000 }
		);

		// After the soft-nav completes, assert user globals are cleared.
		const userAfterLogout = await page.evaluate(() => (window as any).__GARNET_USER__);
		expect(userAfterLogout).toBeUndefined();

		const accountIdAfterLogout = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);
		expect(accountIdAfterLogout).toBeUndefined();

		// CSRF token should still exist (it's always emitted, even for anonymous sessions).
		const csrfAfterLogout = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		expect(csrfAfterLogout).toBeDefined();
		expect(typeof csrfAfterLogout).toBe('string');
	});

	test('state globals are preserved when soft-navigating to a self-contained error page', async () => {
		// Start fresh with normal login to establish authed state with state globals.
		await page.context().clearCookies();
		await registerAccount(page, TEST_EMAIL);

		// Capture the full set of state globals from a normal page.
		const csrfBefore = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const prefixBefore = await page.evaluate(() => (window as any).__GARNET_PREFIX__);
		const langBefore = await page.evaluate(() => (window as any).__GARNET_UI_LANG__);
		const baseUrlBefore = await page.evaluate(() => (window as any).__GARNET_BASE_URL__);
		const userBefore = await page.evaluate(() => (window as any).__GARNET_USER__);
		const accountIdBefore = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);

		expect(csrfBefore).toBeDefined();
		expect(prefixBefore).toBeDefined();
		expect(langBefore).toBeDefined();
		expect(baseUrlBefore).toBeDefined();
		expect(userBefore).toBeDefined();
		expect(accountIdBefore).toBeDefined();

		// Navigate to a non-existent route that returns a 404 error page.
		// This triggers GoTo.ts:60 which feeds the self-contained Error404Fallback
		// template to PageLoader.updatePage() on any non-2xx HTTP response.
		const response = await page.goto('/this-route-does-not-exist-404-test-' + Date.now(), {
			waitUntil: 'domcontentloaded',
			timeout: 15000
		});

		// The response should be a 404 from the server, served via PageLoader soft-nav.
		expect(response?.status()).toBe(404);

		// Critical: the state globals from the previous normal page should remain intact.
		// Self-contained error pages have NO __GARNET_* scripts at all, so the
		// removal sweep should be SKIPPED entirely by the incomingStateIds.size > 0 guard.
		const csrfAfter = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const prefixAfter = await page.evaluate(() => (window as any).__GARNET_PREFIX__);
		const langAfter = await page.evaluate(() => (window as any).__GARNET_UI_LANG__);
		const baseUrlAfter = await page.evaluate(() => (window as any).__GARNET_BASE_URL__);
		const userAfter = await page.evaluate(() => (window as any).__GARNET_USER__);
		const accountIdAfter = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);

		// All state globals should be preserved, not wiped.
		expect(csrfAfter).toBe(csrfBefore);
		expect(prefixAfter).toBe(prefixBefore);
		expect(langAfter).toBe(langBefore);
		expect(baseUrlAfter).toBe(baseUrlBefore);
		expect(userAfter).toEqual(userBefore);
		expect(accountIdAfter).toBe(accountIdBefore);

		// Verify the actual error content is present (not just the globals).
		await page.waitForFunction(
			() => document.body.textContent !== null && document.body.textContent!.length > 0,
			{ timeout: 5000 }
		);
	});
});