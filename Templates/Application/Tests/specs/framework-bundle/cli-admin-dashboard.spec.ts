/**
 * CLI admin console (`/__garnet/`): the hook-free dashboard island renders,
 * its functional-class styles reach the page, and a command streams over
 * SSE (CSRF-checked ticket, then EventSource) through to completion.
 *
 * Needs no DB: login is the one-shot CLI token flow (`adminLogin`).
 */

import type { Page } from '@playwright/test';
import { test, expect } from '../../helpers/scoped-test';
import { newScopedContext } from '../../helpers/scoped-test';
import { adminLogin } from '../../helpers/role-login';

const button = (page: Page, name: string) => page.getByRole('button', { name, exact: true });

// The console is dev-only (GARNET_ENV=dev or an IDE marker directory above the
// app); anywhere else it answers a JSON 404.
async function skipUnlessConsole(page: Page): Promise<void> {
	const resp = await page.request.get('/__garnet/');
	test.skip(resp.status() === 404, 'admin console is dev-only: serve the app with GARNET_ENV=dev');
}

test.describe('CLI admin dashboard', () => {
	test('renders the island with its styles applied', async ({ browser }) => {
		const context = await newScopedContext(browser);
		const page = await context.newPage();
		try {
			await skipUnlessConsole(page);
			await adminLogin(page);
			await expect(page.getByRole('heading', { name: 'Garnet Admin' })).toBeVisible();
			for (const name of ['Build', 'Build:Watch', 'Prepare', 'Migration', 'Logout', 'Clear']) {
				await expect(button(page, name)).toBeVisible();
			}

			const css = (selector: string, prop: string) =>
				page.locator(selector).first().evaluate((el, p) => getComputedStyle(el).getPropertyValue(p), prop);
			// Shape/layout utilities and the palette are all unset without admin.css.
			await expect.poll(() => css('.clidashboard-card', 'border-top-left-radius')).toBe('8px');
			await expect.poll(() => css('.clidashboard-header', 'position')).toBe('sticky');
			await expect.poll(() => css('.clidashboard-page', 'background-color')).not.toBe('rgba(0, 0, 0, 0)');
		} finally {
			await context.close();
		}
	});

	test('a command streams to completion and Clear empties the output', async ({ browser }) => {
		test.setTimeout(120_000);
		const context = await newScopedContext(browser);
		const page = await context.newPage();
		try {
			await skipUnlessConsole(page);
			await adminLogin(page);
			const output = page.locator('pre.clidashboard-output');

			await button(page, 'Migration').click();
			await expect(output).toContainText('$ php garnet migration');
			await expect(output).toContainText(/Done \(exit \d+\)/, { timeout: 90_000 });
			await expect(button(page, 'Migration')).toBeEnabled();

			await button(page, 'Clear').click();
			await expect(output).toHaveText('');
		} finally {
			await context.close();
		}
	});

	test('Logout drops the session', async ({ browser }) => {
		const context = await newScopedContext(browser);
		const page = await context.newPage();
		try {
			await skipUnlessConsole(page);
			await adminLogin(page);
			await button(page, 'Logout').click();
			// Back on the login page: it shows the command instead of the controls.
			await expect(page.locator('code', { hasText: 'php garnet admin' })).toBeVisible();
			await expect(button(page, 'Logout')).toHaveCount(0);
		} finally {
			await context.close();
		}
	});
});
