/**
 * Per-worker isolation setup pipeline.
 *
 * Active when `PW_WORKER_ISOLATION` is unset or `"1"`. Builds N
 * independent DB "namespaces" (table prefixes `test_worker_0_*` …
 * `test_worker_${N-1}_*`) via a one-time template:
 *
 *   1. Drop any leftover `test_worker_*` tables.
 *   2. Migrate the template prefix `test_worker_template`
 *      (`php garnet migration init` + `migrate`).
 *   3. Clone every template table into `test_worker_${i}_*` for each
 *      worker — fast: `CREATE TABLE LIKE` + `INSERT SELECT`.
 *
 * Registering test accounts (via the real email-code auth flow) is left
 * to individual specs / their own setup — the bare framework has no
 * fixed set of seed accounts the way a business app does. Wire your
 * own account-provisioning step here once your app has real roles;
 * see role-login.ts for the two built-in login paths (generic account
 * + admin token).
 *
 * This pipeline mirrors `Bundle/Middlewares/WorkerScopeMiddleware.php`'s
 * dev-context fan-out (`test_worker_template`, `test_worker_0..N`).
 */
import * as path from 'node:path';
import { spawnSync } from 'node:child_process';
import mysql from 'mysql2/promise';
import { DB as DB_CONFIG } from './db';

const APP_DIR = process.env.PW_APP_DIR ?? process.env.GARNET_APP_DIR ?? path.resolve(__dirname, '..', '..');
const TEMPLATE_PREFIX = 'test_worker_template';

function workerCount(explicit?: number): number {
    if (typeof explicit === 'number' && explicit > 0) {
        return explicit;
    }
    const raw = process.env.PW_WORKERS;
    const n = raw ? parseInt(raw, 10) : 1;
    if (Number.isNaN(n) || n < 1) return 1;
    return n;
}

function runCli(prefix: string, args: string[]) {
    const env = { ...process.env, DB_PREFIX_OVERRIDE: prefix };
    const res = spawnSync('php', ['garnet', ...args], {
        cwd: APP_DIR,
        env,
        encoding: 'utf-8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    if (res.status !== 0) {
        if (res.stdout) process.stdout.write(res.stdout);
        if (res.stderr) process.stderr.write(res.stderr);
        throw new Error(`CLI [${args.join(' ')}] for ${prefix} failed (exit ${res.status})`);
    }
}

async function dropAllWorkerTables(): Promise<void> {
    const conn = await mysql.createConnection(DB_CONFIG);
    try {
        const [rows] = await conn.execute<any[]>(
            `SELECT TABLE_NAME FROM information_schema.tables
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE 'test_worker_%'`,
            [DB_CONFIG.database]
        );
        await conn.execute('SET FOREIGN_KEY_CHECKS = 0');
        for (const r of rows) {
            await conn.execute(`DROP TABLE IF EXISTS \`${r.TABLE_NAME}\``);
        }
        await conn.execute('SET FOREIGN_KEY_CHECKS = 1');
    } finally {
        await conn.end();
    }
}

async function listTemplateTables(): Promise<string[]> {
    const conn = await mysql.createConnection(DB_CONFIG);
    try {
        const [rows] = await conn.execute<any[]>(
            `SELECT TABLE_NAME FROM information_schema.tables
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?`,
            [DB_CONFIG.database, `${TEMPLATE_PREFIX}_%`]
        );
        return rows.map((r) => r.TABLE_NAME as string);
    } finally {
        await conn.end();
    }
}

async function cloneTemplateTo(workerIndex: number): Promise<void> {
    const tables = await listTemplateTables();
    const targetPrefix = `test_worker_${workerIndex}`;
    const conn = await mysql.createConnection(DB_CONFIG);
    try {
        for (const src of tables) {
            const tgt = src.replace(`${TEMPLATE_PREFIX}_`, `${targetPrefix}_`);
            await conn.execute(`CREATE TABLE \`${tgt}\` LIKE \`${src}\``);
            // Skip generated/virtual columns when copying rows — MySQL
            // refuses INSERT VALUES against them. Read the real column
            // list from information_schema and project explicitly.
            const [colRows] = await conn.execute<any[]>(
                `SELECT COLUMN_NAME FROM information_schema.columns
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                       AND (EXTRA NOT LIKE '%GENERATED%' OR EXTRA IS NULL)
                 ORDER BY ORDINAL_POSITION`,
                [DB_CONFIG.database, src]
            );
            if (colRows.length === 0) continue;
            const colList = colRows.map((r) => `\`${r.COLUMN_NAME}\``).join(', ');
            await conn.execute(`INSERT INTO \`${tgt}\` (${colList}) SELECT ${colList} FROM \`${src}\``);
        }
    } finally {
        await conn.end();
    }
}

export async function isolationSetup(workers?: number): Promise<void> {
    workers = workerCount(workers);
    console.log(`[isolation] preparing ${workers} worker(s) — template + clone`);

    const t0 = Date.now();

    console.log('[isolation] dropping leftover test_worker_* tables');
    await dropAllWorkerTables();

    console.log('[isolation] migrating template');
    runCli(TEMPLATE_PREFIX, ['migration', 'init']);
    // `migration init` creates the tracker table and sets version=1,
    // assuming the v1 schema is already present. For a fresh prefix it
    // isn't — push the tracker back to 0 so the migrate loop applies
    // every migration from the start.
    {
        const conn = await mysql.createConnection(DB_CONFIG);
        try {
            await conn.execute(
                `UPDATE \`${TEMPLATE_PREFIX}_migration\` SET version = '0' WHERE id = 1000`
            );
        } finally {
            await conn.end();
        }
    }
    runCli(TEMPLATE_PREFIX, ['migration', 'migrate']);

    console.log(`[isolation] cloning template → test_worker_0..${workers - 1}`);
    // Parallel clone: CREATE TABLE LIKE + INSERT SELECT on distinct
    // target prefixes are independent — no shared state, InnoDB
    // handles concurrent DDL fine.
    await Promise.all(
        Array.from({ length: workers }, (_, i) => cloneTemplateTo(i))
    );

    const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
    console.log(`[isolation] setup complete in ${elapsed}s`);
}

export async function isolationTeardown(): Promise<void> {
    console.log('[isolation] dropping all test_worker_* tables');
    await dropAllWorkerTables();
}
