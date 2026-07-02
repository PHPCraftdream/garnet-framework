/**
 * Magic-link verify flow (non-.test email, normal code-sending path).
 *
 * Exercises the real `EmailAuthMiddleware` state machine end to end:
 *
 *   POST /  { auth_email: <real email> } → 200, code minted + emailed
 *   (assert: no account row exists yet — the account is only created on
 *    verify success, not on the request-code step)
 *   read auth_code back from session_data (mirrors what the mailed code
 *    would contain — see helpers/auth.ts's readAuthCodeFromDb)
 *   GET /#token=<code>  (Auth2 island reads the hash, POSTs verify)
 *   (assert: verify POST returns success=true)
 *   (assert: the SPA-replaced body is fully populated, not truncated)
 *   (assert: the account row now exists)
 */

import { test, expect, tn } from '../../helpers/scoped-test';
import { newScopedContext } from '../../helpers/scoped-test';
import { withConnection } from '../../helpers/db';
import { tickPdConsent } from '../../helpers/auth';

test.describe.configure({ mode: 'serial' });

// The scaffold's `/` route is deliberately public — EmailAuthMiddleware is
// wired onto /account instead (see Application.php::runWebApp). Override
// via PW_AUTH_PATH if your app gates a different route.
const AUTH_PATH = process.env.PW_AUTH_PATH ?? '/account';

const PIDX = process.env.TEST_PARALLEL_INDEX ?? '0';
// Unique per run — request-code rate-limit outlives test cleanup, so a
// fixed email can go 429 on rerun.
const EMAIL = `magic_${PIDX}_${Date.now()}@external.example.com`;

async function fetchLatestAuthCode(email: string): Promise<string | null> {
    return withConnection(async (conn) => {
        const [rows] = await conn.execute<any[]>(
            `SELECT sd.value FROM ${tn('session_data')} sd
             JOIN ${tn('session')} s ON s.id = sd.sessionId
             JOIN ${tn('session_data')} sd2 ON sd2.sessionId = s.id AND sd2.param = 'auth_login'
             WHERE sd.param = 'auth_code' AND sd2.value = ?
             ORDER BY sd.id DESC LIMIT 1`,
            [email],
        );
        return rows[0]?.value ?? null;
    });
}

async function countAccounts(email: string): Promise<number> {
    return withConnection(async (conn) => {
        const [rows] = await conn.execute<any[]>(
            `SELECT COUNT(*) AS n FROM ${tn('accounts')} WHERE login = ?`,
            [email],
        );
        return Number(rows[0]?.n ?? 0);
    });
}

async function cleanup(email: string) {
    await withConnection(async (conn) => {
        await conn.execute(
            `DELETE ad FROM ${tn('accounts_data')} ad JOIN ${tn('accounts')} a ON a.id = ad.account_id WHERE a.login = ?`,
            [email],
        );
        await conn.execute(`DELETE FROM ${tn('accounts')} WHERE login = ?`, [email]);
    });
}

test.describe('Magic-link verify — code path + SPA-replace', () => {
    test.beforeAll(async () => {
        await cleanup(EMAIL);
    });

    test.afterAll(async () => {
        await cleanup(EMAIL);
    });

    test('full flow: request-code does NOT create account, verify does, no white screen', async ({ browser }) => {
        const context = await newScopedContext(browser);
        const page = await context.newPage();

        // ── 1. Request-code POST ─────────────────────────────────────────
        await page.goto(AUTH_PATH);
        await expect(page.locator('[data-test-id="auth-login-input"]')).toBeVisible({ timeout: 10000 });
        await page.locator('[data-test-id="auth-login-input"]').fill(EMAIL);
        await tickPdConsent(page);

        const [requestResponse] = await Promise.all([
            page.waitForResponse(
                r => r.request().method() === 'POST' && r.url().includes('/'),
                { timeout: 15000 },
            ),
            page.locator('[data-test-id="auth-submit-btn"]').click(),
        ]);
        if (!requestResponse.ok()) {
            const body = await requestResponse.text();
            throw new Error(`request-code POST failed: ${requestResponse.status()} ${body}`);
        }
        const requestBody = await requestResponse.json();
        expect(requestBody.message).toBeTruthy();
        expect(requestBody.codeLifeTime).toBeGreaterThan(0);

        // ── 2. Account must NOT exist yet — only created on verify success ──
        expect(await countAccounts(EMAIL)).toBe(0);

        // ── 3. Read the code back from session_data ──────────────────────
        const code = await fetchLatestAuthCode(EMAIL);
        expect(code, `auth_code not found for ${EMAIL}`).toBeTruthy();

        // ── 4. Navigate via magic link (Auth2 reads #token from hash) ────
        //     Open a fresh page in the SAME context — session cookie carries
        //     over, but a fresh page guarantees a full reload (page.goto on
        //     a same-origin URL that only changes the hash does NOT reload).
        const linkPage = await context.newPage();
        const [verifyResponse] = await Promise.all([
            linkPage.waitForResponse(
                r => r.request().method() === 'POST' && r.url().includes('/'),
                { timeout: 15000 },
            ),
            linkPage.goto(`${AUTH_PATH}#token=${code}`),
        ]);
        expect(verifyResponse.ok()).toBe(true);
        const verifyBody = await verifyResponse.json();
        expect(verifyBody.success).toBe(true);

        // ── 5. SPA-replaced body must be fully populated, not truncated ──
        //     Belt-and-braces regression guard: the real body content size
        //     is well above a truncated-body footprint.
        const bodyText = await linkPage.locator('body').innerHTML();
        expect(bodyText.length).toBeGreaterThan(200);

        // ── 6. Account now exists ─────────────────────────────────────────
        expect(await countAccounts(EMAIL)).toBe(1);

        await context.close();
    });
});
