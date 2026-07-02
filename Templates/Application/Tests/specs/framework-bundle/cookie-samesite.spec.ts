/**
 * Regression: auth cookies must be SameSite=Lax so they survive the cross-site
 * top-level navigation from a webmail magic-link click (e.g. gmail.com → the app).
 *
 * The Cookie class defaults to SameSite=Strict. The session cookie overrides
 * that to Lax, but a regression on the CSRF_TOKEN cookie (or any future auth
 * cookie added without SameSite=Lax) means: on an email-link click the
 * session arrives while the CSRF cookie is dropped, and the page's token
 * disagrees with the cookie the browser replays on the next same-site POST →
 * "CSRF token validation failed", and sign-in from email becomes impossible.
 *
 * This guards every auth cookie at the HTTP level (the actual Set-Cookie the
 * server emits), so a future cookie added without SameSite=Lax — or a
 * regression back to Strict — fails immediately.
 *
 * REQUIRES: a route gated by `AuthMiddleware::authOnly` (see
 * `Bundle/Modules/Auth/Middlewares/AuthMiddleware.php`). The scaffolded
 * `Application.php` ships the `/` route's authOnly middleware commented
 * out — uncomment it (or wire authOnly onto another route and update
 * AUTH_PATH below) before running this spec.
 */
import { test, expect } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8001';
const WORKER = process.env.TEST_PARALLEL_INDEX ?? '0';
const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/account';

// Cookies that ride the auth flow and therefore MUST be Lax (not Strict/None),
// or a magic-link click from webmail drops them.
const MUST_BE_LAX = ['session', 'CSRF_TOKEN'];

test('auth cookies are SameSite=Lax (survive webmail magic-link navigation)', async ({ request }) => {
    // The consent "start-session" POST is the point where both the session and
    // the CSRF cookie are (re)minted.
    const res = await request.post(`${BASE}${AUTH_PATH}`, {
        form: { action: 'start-session', consent_pd: '1' },
        headers: { 'X-Test-Worker': WORKER },
    });

    const setCookies = res.headersArray()
        .filter((h) => h.name.toLowerCase() === 'set-cookie')
        .map((h) => h.value);

    for (const name of MUST_BE_LAX) {
        const cookie = setCookies.find((c) => new RegExp(`^${name}=`).test(c));
        expect(cookie, `${name} cookie should be set by start-session`).toBeTruthy();
        expect(cookie!, `${name} must be SameSite=Lax`).toMatch(/;\s*SameSite=Lax/i);
        expect(cookie!, `${name} must NOT be SameSite=Strict (drops on email-link nav)`).not.toMatch(/SameSite=Strict/i);
    }
});
