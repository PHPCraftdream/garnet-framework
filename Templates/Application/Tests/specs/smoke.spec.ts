import { test, expect } from '@playwright/test';

// Baseline smoke tests for a fresh Garnet app. Grow this suite as you add
// pages and features. Run against `php garnet serve` (see ../README.md).

test('homepage responds', async ({ page }) => {
	const resp = await page.goto('/');
	expect(resp?.status()).toBeLessThan(500);
});

test('homepage renders an HTML document with a title', async ({ page }) => {
	await page.goto('/');
	await expect(page).toHaveTitle(/.+/);
	// The framework always emits a valid <html lang="…"> (never "auto").
	const lang = await page.locator('html').getAttribute('lang');
	expect(lang).toBeTruthy();
	expect(lang).not.toBe('auto');
});

test('unknown route returns 404, not a 500', async ({ page }) => {
	const resp = await page.goto('/__definitely_not_a_real_route__');
	expect(resp?.status()).toBe(404);
});
