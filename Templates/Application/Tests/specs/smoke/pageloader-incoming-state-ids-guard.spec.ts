/**
 * DOM-level regression test for PageLoader.updatePage() guard (commit 6dc0080).
 *
 * The guard: `if (incomingStateIds.size > 0)` skips the removal sweep when
 * the incoming response carries NO state scripts at all (self-contained error/
 * maintenance pages). Without this guard, ALL __GARNET_* globals would be wiped
 * on soft-nav to such pages.
 *
 * This test is CI-able: no MySQL, no live server needed. It uses Playwright's
 * headless Chromium to execute the REAL PageLoader.updatePage() against DOM
 * fixtures.
 *
 * BEFORE the fix (round-5 bug):
 *   - Soft-nav to an error page (zero state scripts) would sweep and clear ALL
 *     __GARNET_* globals from window, breaking subsequent soft-nav requests
 *     that depend on CSRF, user context, etc.
 *
 * AFTER the fix (commit 6dc0080):
 *   - The removal sweep is SKIPPED when incomingStateIds is empty, preserving
 *     all existing globals intact for self-contained pages.
 */

import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { transformSync } from 'esbuild';

// Fixtures representing real HTML responses from the Garnet framework.

// Normal page HTML (like HtmlLayout.en.twig with auth session) - FULL state scripts.
const NORMAL_PAGE_HTML = `
<!doctype html>
<html>
<head>
    <title>Normal Page</title>
    <meta charset="utf-8">
    <script id="__GARNET_CSRF__">window.__GARNET_CSRF__ = "csrf-token-123";</script>
    <script id="__GARNET_PREFIX__">window.__GARNET_PREFIX__ = "/prefix";</script>
    <script id="__GARNET_UI_LANG__">window.__GARNET_UI_LANG__ = "en";</script>
    <script id="__GARNET_BASE_URL__">window.__GARNET_BASE_URL__ = "http://localhost:8001";</script>
    <script id="__GARNET_UPLOAD_DIR__">window.__GARNET_UPLOAD_DIR__ = "/uploads";</script>
    <script id="__GARNET_USER__">window.__GARNET_USER__ = {"id": 1, "name": "Test User", "email": "test@example.com"};</script>
    <script id="__GARNET_ACCOUNT_ID__">window.__GARNET_ACCOUNT_ID__ = 42;</script>
</head>
<body>
    <h1>Normal Page</h1>
    <p>This page has all state scripts.</p>
</body>
</html>
`;

// Error page HTML (like ErrorPage.en.twig) - ZERO state scripts.
const ERROR_PAGE_HTML = `
<!doctype html>
<html lang="auto">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Error 500</title>
<style>.content {display: flex; justify-content: center;} .error {padding: 60px;}</style>
</head>
<body>
<div class="content">
<div class="error">
<h1>Internal Server Error</h1>
<p>Something went wrong.</p>
</div>
</div>
</body>
</html>
`;

// Maintenance page HTML (like Maintenance.en.twig) - ZERO state scripts.
const MAINTENANCE_PAGE_HTML = `
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Site under maintenance</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: sans-serif; display: flex; justify-content: center; min-height: 100vh; }
  .card { text-align: center; padding: 3rem 2rem; }
</style>
</head>
<body>
<div class="card">
  <h1>Site under maintenance</h1>
  <p>We are performing scheduled maintenance. Please check back in a few minutes.</p>
</div>
</body>
</html>
`;

/**
 * Read and transpile the real PageLoader.ts for browser injection.
 * Uses esbuild to transpile TypeScript to JavaScript, then stubs
 * the require() calls and replaces loadStyles/swapBody with no-ops.
 */
function getRealPageLoaderCode(): string {
	// process.cwd() is Templates/Application/Tests
	// Bundle/ is at the framework root (3 levels up from Tests)
	const realPath = resolve(process.cwd(), '../../../Bundle/Front/Common/Dom/PageLoader.ts');
	const tsCode = readFileSync(realPath, 'utf8');

	// Use esbuild to transpile TypeScript to JavaScript
	const result = transformSync(tsCode, {
		loader: 'ts',
		target: 'es2022',
		format: 'iife',
		banner: '// Transpiled from real Bundle/Front/Common/Dom/PageLoader.ts\n',
	});

	let code = result.code;

	// Stub the require() calls that esbuild generated
	// @common/Utils/PageEvents and @common/Enums are not needed for this test
	code = code.replace(
		/var import_PageEvents = require\("@common\/Utils\/PageEvents"\);/g,
		'var import_PageEvents = { init: () => ({ emmit: () => {} }) };'
	);
	code = code.replace(
		/var import_Enums = require\("@common\/Enums"\);/g,
		'var import_Enums = { ECommonEvents: { PAGE_RELOADED: "PAGE_RELOADED" } };'
	);

	// Replace the final loadStyles/swapBody call with no-op
	code = code.replace(
		/return PageLoader\.loadStyles\(stylesToLoad\)\s*\.then\(\(\) => PageLoader\.swapBody\(doc, scriptsToLoad\)\);/g,
		'return Promise.resolve();'
	);

	// Remove the IIFE wrapper and export to window
	code = code.replace(/\(\(\) => \{([\s\S]*?)\}\)\(\);/, '$1\nwindow.PageLoader = PageLoader;');

	return code;
}

const REAL_PAGE_LOADER_CODE = getRealPageLoaderCode();

test.describe('PageLoader.updatePage() — incomingStateIds.size > 0 guard', () => {
	test('normal to error page: state globals are PRESERVED (guard active)', async ({ page }) => {
		// Load the normal page HTML first to establish state globals.
		await page.setContent(NORMAL_PAGE_HTML);

		// Verify initial state globals are set.
		const csrfBefore = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const prefixBefore = await page.evaluate(() => (window as any).__GARNET_PREFIX__);
		const userBefore = await page.evaluate(() => (window as any).__GARNET_USER__);
		const accountIdBefore = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);

		expect(csrfBefore).toBe('csrf-token-123');
		expect(prefixBefore).toBe('/prefix');
		expect(userBefore).toEqual({id: 1, name: "Test User", email: "test@example.com"});
		expect(accountIdBefore).toBe(42);

		// Inject the REAL PageLoader code from Bundle/Front/Common/Dom/PageLoader.ts
		await page.addScriptTag({ content: REAL_PAGE_LOADER_CODE });

		// Call PageLoader.updatePage() with error page HTML (zero state scripts)
		await page.evaluate(async (html: string) => {
			await (window as any).PageLoader.updatePage(html);
		}, ERROR_PAGE_HTML);

		// CRITICAL: All state globals should STILL EXIST and be UNCHANGED.
		// The guard (incomingStateIds.size > 0) skipped the removal sweep.
		const csrfAfter = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const prefixAfter = await page.evaluate(() => (window as any).__GARNET_PREFIX__);
		const userAfter = await page.evaluate(() => (window as any).__GARNET_USER__);
		const accountIdAfter = await page.evaluate(() => (window as any).__GARNET_ACCOUNT_ID__);

		expect(csrfAfter).toBe(csrfBefore);
		expect(prefixAfter).toBe(prefixBefore);
		expect(userAfter).toEqual(userBefore);
		expect(accountIdAfter).toBe(accountIdBefore);
	});

	test('normal to maintenance page: state globals are PRESERVED (guard active)', async ({ page }) => {
		await page.setContent(NORMAL_PAGE_HTML);

		// Inject the REAL PageLoader code
		await page.addScriptTag({ content: REAL_PAGE_LOADER_CODE });

		// Capture initial values
		const csrfBefore = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const userBefore = await page.evaluate(() => (window as any).__GARNET_USER__);

		// Soft-nav to maintenance page
		await page.evaluate(async (html: string) => {
			await (window as any).PageLoader.updatePage(html);
		}, MAINTENANCE_PAGE_HTML);

		// Globals should be preserved
		const csrfAfter = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const userAfter = await page.evaluate(() => (window as any).__GARNET_USER__);

		expect(csrfAfter).toBe(csrfBefore);
		expect(userAfter).toEqual(userBefore);
	});

	test('control: WITHOUT guard, error page WIPES all globals (mutation test harness)', async ({ page }) => {
		// This test verifies the harness is sensitive to the fix: if the guard is
		// removed from the REAL PageLoader.ts, this test SHOULD fail (globals would be wiped).
		//
		// We deliberately disable the guard here by modifying the injected code to prove
		// the test would catch a regression. This is NOT modifying the PageLoader implementation
		// on disk — it's a mutation-test harness.

		await page.setContent(NORMAL_PAGE_HTML);

		// Create a MUTATED version of the PageLoader code with the guard disabled
		const mutatedCode = REAL_PAGE_LOADER_CODE.replace(
			/if \(incomingStateIds\.size > 0\) \{/,
			// Guard disabled: sweep runs even when incomingStateIds is empty
			'// MUTATION: guard disabled for control test\nif (true) {'
		);

		await page.addScriptTag({ content: mutatedCode });

		// Capture initial values (not used in this control test, but captured for symmetry)
		const _csrfBefore = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const _userBefore = await page.evaluate(() => (window as any).__GARNET_USER__);

		// Soft-nav to error page (zero state scripts)
		await page.evaluate(async (html: string) => {
			await (window as any).PageLoader.updatePage(html);
		}, ERROR_PAGE_HTML);

		// WITH GUARD DISABLED: All globals should be WIPED (undefined).
		// This proves the test would fail if the real PageLoader had the bug.
		const csrfAfter = await page.evaluate(() => (window as any).__GARNET_CSRF__);
		const userAfter = await page.evaluate(() => (window as any).__GARNET_USER__);

		expect(csrfAfter).toBeUndefined();
		expect(userAfter).toBeUndefined();
	});
});