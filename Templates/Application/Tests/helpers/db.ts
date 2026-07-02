/**
 * Centralised DB connection config for Playwright specs.
 *
 * Source of truth: `<app>/WorkDir/ConfigDev/db.ini` — the same file the
 * running PHP app reads. Reading the ini here keeps specs in lockstep
 * with the app config instead of hard-coding credentials.
 *
 * App-dir resolution (in order):
 *   1. `PW_APP_DIR` — explicit Playwright-side override.
 *   2. `GARNET_APP_DIR` — set by every `garnet` CLI wrapper and respected
 *      by the framework throughout. The same source of truth.
 *   3. Fallback: two directories up from this file (`<app>/Tests/helpers`
 *      → `<app>`), which is where the scaffolded app's own `WorkDir/`
 *      lives once `garnet app:create` has copied this template out.
 *
 * Per-field overrides: `PW_DB_HOST`, `PW_DB_PORT`, `PW_DB_NAME`,
 * `PW_DB_USER`, `PW_DB_PASSWORD`.
 */
import * as fs from 'node:fs';
import * as path from 'node:path';
import mysql, { Connection } from 'mysql2/promise';

function resolveAppDir(): string {
    const explicit = process.env.PW_APP_DIR ?? process.env.GARNET_APP_DIR;
    if (explicit && explicit !== '') return explicit;

    return path.resolve(__dirname, '..', '..');
}

const APP_DIR = resolveAppDir();
const DEFAULT_INI = path.resolve(APP_DIR, 'WorkDir', 'ConfigDev', 'db.ini');

/** Minimal INI parser — supports `key = "value"` and `key = value`. */
function readIni(file: string): Record<string, string> {
    const text = fs.readFileSync(file, 'utf-8');
    const out: Record<string, string> = {};
    for (const raw of text.split(/\r?\n/)) {
        const line = raw.trim();
        if (!line || line.startsWith(';') || line.startsWith('#') || line.startsWith('[')) continue;
        const eq = line.indexOf('=');
        if (eq < 0) continue;
        const key = line.slice(0, eq).trim();
        let val = line.slice(eq + 1).trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            val = val.slice(1, -1);
        }
        out[key] = val;
    }
    return out;
}

function resolveConfig() {
    const iniPath = process.env.PW_DB_INI ?? DEFAULT_INI;
    const ini = fs.existsSync(iniPath) ? readIni(iniPath) : {};
    return {
        host:     process.env.PW_DB_HOST     ?? ini.dbhost   ?? '127.0.0.1',
        port:     Number(process.env.PW_DB_PORT ?? ini.dbport ?? 3306),
        database: process.env.PW_DB_NAME     ?? ini.dbname   ?? 'app_db',
        user:     process.env.PW_DB_USER     ?? ini.user     ?? 'app_db',
        password: process.env.PW_DB_PASSWORD ?? ini.password ?? 'app_db',
    };
}

export const DB = resolveConfig();

/**
 * Live (non-isolated) table prefix from db.ini, e.g. `db`.
 *
 * Most specs use the worker-scoped prefix via `tn()` / `workerPrefix`, but
 * CLI-driven tests (`php garnet migration`, `php garnet cron`) boot from
 * `consoleInit()` — no HTTP request, so the X-Test-Worker header can't
 * swap the prefix. They need to read/write the live tables directly.
 */
export function liveDbPrefix(): string {
    const iniPath = process.env.PW_DB_INI ?? DEFAULT_INI;
    const ini = fs.existsSync(iniPath) ? readIni(iniPath) : {};
    return ini.prefix ?? 'db';
}

/**
 * Open a connection, run `fn`, and always close — replaces the
 * `try { ... } finally { await conn.end(); }` boilerplate that would
 * otherwise clutter every spec.
 *
 * ```ts
 * const rows = await withConnection(conn =>
 *     conn.execute(`SELECT * FROM ${tn('accounts')} WHERE id = ?`, [id])
 * );
 * ```
 */
export async function withConnection<T>(fn: (conn: Connection) => Promise<T>): Promise<T> {
    const conn = await mysql.createConnection(DB);
    try {
        return await fn(conn);
    } finally {
        await conn.end();
    }
}
