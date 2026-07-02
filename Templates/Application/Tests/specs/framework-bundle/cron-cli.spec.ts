/**
 * `php garnet cron` end-to-end CLI test.
 *
 * The cron entry point goes through `consoleInit()` (not `runWebApp()`),
 * so it boots without an HTTP request. This spec exercises the CLI
 * directly (no browser) against the bare scaffolded template, which
 * registers zero cron tasks by default — apps wire their own tasks via
 * a concrete `FwCronService` subclass (see `Kernel/Io/Cron/README.md`
 * and `FwCronService::registerTasks()`).
 *
 * Asserts the CLI boots cleanly and reports the correct task count in
 * all three cases: `cron`, `cron list`, `cron <unknown-name>`.
 */

import { test, expect } from '../../helpers/scoped-test';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import * as path from 'node:path';

const execFileAsync = promisify(execFile);

const APP_ROOT = path.resolve(__dirname, '..', '..', '..');

async function runGarnet(args: string[]): Promise<{ stdout: string; stderr: string; exitCode: number }> {
    try {
        const result = await execFileAsync('php', ['garnet', ...args], {
            cwd: APP_ROOT,
            timeout: 60000,
            shell: false,
        });
        return { stdout: result.stdout, stderr: result.stderr, exitCode: 0 };
    } catch (err: any) {
        return {
            stdout: err.stdout ?? '',
            stderr: err.stderr ?? '',
            exitCode: err.code ?? 1,
        };
    }
}

test.describe('php garnet cron — CLI boots via consoleInit', () => {
    test('runs with zero registered tasks and exits 0', async () => {
        const result = await runGarnet(['cron']);

        expect(result.stderr).not.toContain('Fatal error');
        expect(result.stdout).toMatch(/Done:\s*0\/0 tasks completed/);
        expect(result.exitCode).toBe(0);
    });

    test('cron list reports no tasks registered', async () => {
        const result = await runGarnet(['cron', 'list']);

        expect(result.exitCode).toBe(0);
        expect(result.stdout).toContain('No cron tasks registered.');
    });

    test('cron <unknown-name> reports the unknown task and exits non-zero', async () => {
        const result = await runGarnet(['cron', 'does-not-exist']);

        expect(result.stdout).toContain('Unknown task: does-not-exist');
        expect(result.exitCode).not.toBe(0);
    });
});
