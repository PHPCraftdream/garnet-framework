import { defineConfig, devices } from '@playwright/test';

// E2E config for the Application app. Runs against a locally-served
// instance — start it first with `php garnet serve` (Node dev server on
// :8001), then `npm test` from this dir.
//
// Override the target with BASE_URL=… npm test (e.g. a staging box).
const baseURL = process.env.BASE_URL || 'http://localhost:8001';

export default defineConfig({
	testDir: './specs',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.PW_WORKERS ? Number(process.env.PW_WORKERS) : undefined,
	reporter: 'list',
	timeout: 30000,
	expect: { timeout: 5000 },
	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		headless: process.env.HEADLESS !== 'false',
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
	],
});
