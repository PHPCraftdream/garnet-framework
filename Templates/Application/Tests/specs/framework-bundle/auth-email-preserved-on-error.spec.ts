/**
 * Auth form — email must NOT be cleared when the request fails.
 *
 * Regression coverage for `Bundle/Front/auth/Auth2.tsx`: when the
 * request-code POST fails for any reason (network error, 500, a
 * server-side JSON error surfaced through a thrown error), the widget
 * must NOT wipe the email the user already typed. Clearing must only
 * happen on the success branch (when the phase actually advances to
 * INPUT_CODE), so a retry doesn't force the user to retype the address.
 *
 * Strategy: intercept the auth POST via page.route() and respond with an
 * error status. Then assert the input still holds the email after the
 * React state transitions to the error phase.
 */

import { test, expect } from '../../helpers/scoped-test';
import { tickPdConsent } from '../../helpers/auth';

test.describe.configure({ mode: 'serial' });

// The scaffold's `/` route is deliberately public — EmailAuthMiddleware is
// wired onto /account instead (see Application.php::runWebApp). Override
// via PW_AUTH_PATH if your app gates a different route.
const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/account';

const EMAIL = 'auth-error-preserves@garnet.test';

test.describe('Auth form — email value survives a failed request', () => {
    test('500 on POST keeps the email in the input + lets the user retry', async ({ page }) => {
        // Block ALL POSTs with 500. We don't know the exact action
        // endpoint up front (it goes back to the same URL the user
        // landed on), so we install the route before navigation and
        // gate by method only.
        let postCount = 0;
        await page.route('**/*', async (route, request) => {
            if (request.method() === 'POST') {
                // The consent-gated CSRF flow fires a `start-session` POST when
                // the PD checkbox is ticked. That call must succeed so the
                // submit button enables; only the actual request-code submit
                // should land in the 500 mock.
                const body = request.postData() ?? '';
                if (body.includes('"action":"start-session"')) {
                    await route.continue();
                    return;
                }
                postCount++;
                await route.fulfill({ status: 500, body: 'simulated error' });
                return;
            }
            await route.continue();
        });

        await page.goto(AUTH_PATH);

        const input = page.locator('[data-test-id="auth-login-input"]');
        await expect(input).toBeVisible({ timeout: 10000 });

        await input.fill(EMAIL);
        await tickPdConsent(page);
        await page.locator('[data-test-id="auth-submit-btn"]').click();

        // Wait until at least one POST was intercepted (the form
        // submitted), then give React a tick to flush state.
        await expect.poll(() => postCount, { timeout: 5000 }).toBeGreaterThanOrEqual(1);
        await page.waitForTimeout(150);

        // The whole point of the regression: email must still be there.
        await expect(input).toHaveValue(EMAIL);

        // And the form must still be the email phase — i.e. the input
        // is still semantically an email field, not the code field.
        // Placeholder/type guarantees this without depending on i18n strings.
        await expect(input).toHaveAttribute('type', 'email');
    });

    test('429 with {message} surfaces the server message in the hint', async ({ page }) => {
        const SERVER_MSG = 'Too many code requests. Please try again later.';
        await page.route('**/*', async (route, request) => {
            if (request.method() === 'POST') {
                const body = request.postData() ?? '';
                if (body.includes('"action":"start-session"')) {
                    await route.continue();
                    return;
                }
                await route.fulfill({
                    status: 429,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: SERVER_MSG }),
                });
                return;
            }
            await route.continue();
        });

        await page.goto(AUTH_PATH);
        const input = page.locator('[data-test-id="auth-login-input"]');
        await expect(input).toBeVisible({ timeout: 10000 });
        await input.fill(EMAIL);
        await tickPdConsent(page);
        await page.locator('[data-test-id="auth-submit-btn"]').click();

        // The hint paragraph sits right below the input as a sibling div
        // inside the same form wrapper. Scope the locator to "any
        // visible text containing the server message" — robust to layout
        // changes.
        await expect(page.locator('text=' + SERVER_MSG)).toBeVisible({ timeout: 5000 });

        // Email still preserved (regression coverage for the previous fix).
        await expect(input).toHaveValue(EMAIL);
    });

    test('retry after error: email persists, second request also fires', async ({ page }) => {
        let postCount = 0;
        await page.route('**/*', async (route, request) => {
            if (request.method() === 'POST') {
                const body = request.postData() ?? '';
                if (body.includes('"action":"start-session"')) {
                    await route.continue();
                    return;
                }
                postCount++;
                await route.fulfill({ status: 500, body: 'simulated error' });
                return;
            }
            await route.continue();
        });

        await page.goto(AUTH_PATH);
        const input = page.locator('[data-test-id="auth-login-input"]');
        const submit = page.locator('[data-test-id="auth-submit-btn"]');

        await expect(input).toBeVisible({ timeout: 10000 });
        await input.fill(EMAIL);
        await tickPdConsent(page);
        await submit.click();

        await expect.poll(() => postCount, { timeout: 5000 }).toBe(1);
        await page.waitForTimeout(150);

        // Email survived the first error.
        await expect(input).toHaveValue(EMAIL);

        // Click submit again without retyping — handleRequestCode should
        // pick up the same value and fire another POST.
        await submit.click();
        await expect.poll(() => postCount, { timeout: 5000 }).toBe(2);

        // Still preserved after the second failure too.
        await page.waitForTimeout(150);
        await expect(input).toHaveValue(EMAIL);
    });
});
