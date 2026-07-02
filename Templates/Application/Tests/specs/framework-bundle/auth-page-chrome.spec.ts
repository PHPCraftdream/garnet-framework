/**
 * Auth page chrome unified with the rest of the site.
 *
 * Static pages (`/page/view~slug`) render the site nav/footer through
 * `StaticPagesService` and set `bare_main=true` so the host `<main>`
 * doesn't add an extra p-4 / lg:p-6 inset. `HtmlLayout::render` auto-forces
 * `bare_main=true` whenever the body already carries the site chrome
 * (`sp-nav` / `sp-footer` markers), regardless of which controller
 * produced it — the auth page (`/`) goes through the same layout helper,
 * so it must emit the same `<main>` shape.
 *
 * No login needed: this exercises anonymous GET routes only.
 */

import { test, expect } from '../../helpers/scoped-test';
import { newScopedContext } from '../../helpers/scoped-test';

const MAIN_CLASSES = /class="grow flex flex-col min-w-0 main-container"/;
const MAIN_WITH_PADDING = /class="grow flex flex-col min-w-0 main-container p-4 lg:p-6"/;

function extractMainTag(html: string): string | null {
	const match = html.match(/<main[^>]*>/);
	return match ? match[0] : null;
}

test.describe('Auth-page chrome unified with static pages', () => {
	test('/ — no host-layout inset, has sp-nav + sp-footer chrome if present', async ({ browser }) => {
		const context = await newScopedContext(browser);
		const page = await context.newPage();
		try {
			const resp = await page.goto('/');
			expect(resp?.ok()).toBe(true);
			const html = await page.content();

			// Some scaffolded apps may not wire the static-pages chrome onto
			// the auth route — only assert the bare-main invariant when the
			// chrome markers are actually present.
			if (!html.includes('class="sp-nav')) {
				test.skip(true, 'sp-nav chrome not wired on this route — nothing to assert');
				return;
			}
			expect(html).toContain('class="sp-footer');
			// The host `<main>` does NOT carry the regular page padding...
			expect(html).not.toMatch(MAIN_WITH_PADDING);
			// ...and matches the bare-main shape.
			const mainTag = extractMainTag(html);
			expect(mainTag).toMatch(MAIN_CLASSES);
		} finally {
			await context.close();
		}
	});

	test('invariant: any anon page with sp-nav has bare main (no host inset)', async ({ browser }) => {
		// HtmlLayout::render auto-forces bare_main=true when the body already
		// carries the site chrome (sp-nav / sp-footer markers). A regression
		// here means a new code path either grew its own layout wrapper or
		// somehow the auto-detect stopped matching — both are routing-level
		// bugs we want to fail loudly on, on every spec run.
		const context = await newScopedContext(browser);
		const page = await context.newPage();
		try {
			const urls = ['/', '/page/view~terms', '/page/view~privacy'];
			for (const url of urls) {
				const resp = await page.goto(url);
				// Some envs lack the static pages — skip 404s, they have no
				// chrome to assert against.
				if (!resp || resp.status() >= 400) {
					continue;
				}
				const html = await page.content();
				if (!html.includes('class="sp-nav')) {
					continue;
				}
				const mainTag = extractMainTag(html);
				expect(mainTag, `URL ${url}: no <main> tag at all`).not.toBeNull();
				expect(mainTag, `URL ${url}: chrome present but main still has p-4 lg:p-6 inset`).not.toMatch(/p-4 lg:p-6/);
			}
		} finally {
			await context.close();
		}
	});
});
