/**
 * Per-worker scoped test fixture.
 *
 * Every spec that talks to the DB-backed application SHOULD import `test`
 * from this file — NOT from `@playwright/test` directly. Doing so:
 *
 *   1. Guarantees the worker prefix is available as a fixture
 *      (`dbPrefix`) so direct DB queries target the right tables.
 *   2. Wires the `X-Test-Worker` header into every HTTP request the
 *      browser issues, so the server-side `WorkerScopeMiddleware`
 *      (`Bundle/Middlewares/WorkerScopeMiddleware.php`) swaps the table
 *      prefix to `test_worker_N_*` for that request.
 *
 * The fixture is per-worker (not per-test), so the prefix is stable
 * across all tests inside one worker process.
 *
 * Usage:
 * ```ts
 * import { test, expect } from '../helpers/scoped-test';
 * import { tn } from '../helpers/scoped-test';
 * import { withConnection } from '../helpers/db';
 *
 * test('signup lands in DB', async ({ page, dbPrefix }) => {
 *   await page.goto('/');
 *   // ...
 *   const rows = await withConnection(conn =>
 *     conn.execute(`SELECT * FROM ${tn('accounts')} WHERE login = ?`, [login])
 *   );
 * });
 * ```
 *
 * The HTTP path is automatic via `extraHTTPHeaders` in
 * `playwright.config.ts` once worker isolation is wired there — no
 * per-test wiring needed. See isolation-setup.ts for the opt-in pipeline
 * that provisions the per-worker DB scopes this fixture assumes exist.
 */
import { test as base, expect, Browser, BrowserContext, BrowserContextOptions, Page } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { attachConsoleGuards, collectAndResetIssues, formatIssues } from './console-guards';

/**
 * Per-request headers that route a browser context to the isolated test
 * scope. `X-Test-Worker` selects the per-worker prefix on the framework's
 * `WorkerScopeMiddleware`. Centralised here so no call site can forget it.
 */
export function scopeHeaders(workerIndex: string | number): Record<string, string> {
    return { 'X-Test-Worker': String(workerIndex) };
}

/**
 * Resolve the framework table prefix for the current worker.
 *
 * The base prefix is read from `db.ini` (the same file PHP reads), so an
 * app-specific prefix keeps working without spec-level hard-coding.
 * Tests reference tables as `tn('accounts')` only.
 *
 * Two modes (isolation is ON by default):
 *   - default (`PW_WORKER_ISOLATION` unset or = "1"): returns
 *     `'test_worker_${idx}'`. Each worker gets its own table namespace,
 *     race-free.
 *   - `PW_WORKER_ISOLATION=0`: returns the base prefix verbatim (e.g.
 *     `'db'`). Drops back to the shared set — for debugging against
 *     live data only, do NOT combine with `PW_WORKERS>1`.
 *
 * Used by both the `dbPrefix` test fixture (for spec destructuring) AND
 * by the `tn()` helper (for non-fixture helper functions).
 */
function readBasePrefix(): string {
    const override = process.env.PW_DB_PREFIX_BASE;
    if (override && override.length > 0) return override;
    const iniPath = process.env.PW_DB_INI ?? path.resolve(__dirname, '..', '..', 'WorkDir', 'ConfigDev', 'db.ini');
    if (!fs.existsSync(iniPath)) return 'db';
    const text = fs.readFileSync(iniPath, 'utf-8');
    for (const raw of text.split(/\r?\n/)) {
        const line = raw.trim();
        if (!line || line.startsWith(';') || line.startsWith('#') || line.startsWith('[')) continue;
        const eq = line.indexOf('=');
        if (eq < 0) continue;
        const key = line.slice(0, eq).trim();
        if (key !== 'prefix') continue;
        let val = line.slice(eq + 1).trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            val = val.slice(1, -1);
        }
        if (val) return val;
    }
    return 'db';
}

export function getDbPrefix(): string {
    const base = readBasePrefix();
    if (process.env.PW_WORKER_ISOLATION === '0') {
        return base;
    }
    const idx = process.env.TEST_PARALLEL_INDEX ?? '0';
    return `test_worker_${idx}`;
}

/**
 * Compose a fully-qualified table name from the bundle-relative name.
 * Drop-in replacement for hardcoded `db_*` literals in raw SQL:
 *
 * ```ts
 *   `SELECT * FROM ${tn('accounts')} WHERE id = ?`
 * ```
 *
 * Resolves at call time, so isolation toggles via env var without
 * recompiling the spec.
 */
export function tn(name: string): string {
    return `${getDbPrefix()}_${name}`;
}

/**
 * `browser.newContext()` doesn't inherit `extraHTTPHeaders` from
 * `playwright.config.ts → use:` — those only apply to the default
 * `context` fixture. Specs that spin up their own contexts (multi-context
 * flows, isolation-aware probes) must use this helper to keep the
 * X-Test-Worker header attached, otherwise the server falls back to the
 * legacy shared prefix and the test sees the wrong DB.
 */
export async function newScopedContext(
    browser: Browser,
    options: BrowserContextOptions = {}
): Promise<BrowserContext> {
    if (process.env.PW_WORKER_ISOLATION === '0') {
        return browser.newContext(options);
    }
    const idx = process.env.TEST_PARALLEL_INDEX ?? '0';
    const merged: BrowserContextOptions = {
        ...options,
        extraHTTPHeaders: {
            ...(options.extraHTTPHeaders ?? {}),
            ...scopeHeaders(idx),
        },
    };
    const ctx = await browser.newContext(merged);
    attachConsoleGuards(ctx);
    return ctx;
}

/**
 * Convenience: open a single page in a scoped context.
 */
export async function newScopedPage(
    browser: Browser,
    options: BrowserContextOptions = {}
) {
    const context = await newScopedContext(browser, options);
    return context.newPage();
}

type WorkerScope = {
    /** Resolved framework table prefix for this worker. */
    dbPrefix: string;

    /** Numeric worker index, mirrors `testInfo.parallelIndex`. */
    workerIndex: number;
};

const baseTest = base.extend<{}, WorkerScope>({
    workerIndex: [
        async ({}, use, workerInfo) => {
            await use(workerInfo.parallelIndex);
        },
        { scope: 'worker' },
    ],

    dbPrefix: [
        async ({}, use) => {
            await use(getDbPrefix());
        },
        { scope: 'worker' },
    ],
});

/**
 * Scoped test runner. Import as `test` from specs that need the worker
 * DB prefix and/or automatic console-error guarding.
 *
 * Every browser-side warning or uncaught exception during a test fails
 * that test, via the `__consoleGuard` auto-fixture below.
 */
export const test = baseTest.extend<{ __consoleGuard: void }>({
    __consoleGuard: [async ({ context }, use) => {
        attachConsoleGuards(context);
        await use();
        const issues = collectAndResetIssues();
        if (issues.length > 0) {
            throw new Error(
                `Browser console produced ${issues.length} error/warning(s) during this test:\n` +
                formatIssues(issues)
            );
        }
    }, { auto: true }],
});

export { expect };
export type { Page, BrowserContext };
