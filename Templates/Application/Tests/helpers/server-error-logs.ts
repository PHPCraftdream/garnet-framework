/**
 * Server-side PHP error logs guard.
 *
 * Locations the PHP stack writes to on disk:
 *   - WorkDir/LogJournal/Errors/<YYYY-MM-DD>/ERROR_LOGGER-*.log
 *     — structured per-error files written by the app's ErrorLogger.
 *   - Public/errors.log — the legacy plain-text aggregate.
 *   - vendor/phpcraftdream/garnet-framework/errors.log — framework-level
 *     catch-all, reachable through the composer path-repo symlink.
 *
 * Before a run wipe all three so the post-run check starts clean. After
 * the run collect anything that landed; non-empty means PHP raised at
 * least one exception or warning during the suite — fail the run and
 * print a summary.
 *
 * Only invoke from global-setup / global-teardown — never from a spec.
 */
import * as fs from 'node:fs';
import * as path from 'node:path';

// This file lives at `<app>/Tests/helpers/`, so ROOT is the app dir.
const ROOT = path.resolve(__dirname, '../..');

const ERROR_DIRS = [
    path.join(ROOT, 'WorkDir/LogJournal/Errors'),
];

const ERROR_FILES = [
    path.join(ROOT, 'Public/errors.log'),
    path.join(ROOT, 'errors.log'),
    path.join(ROOT, 'vendor/phpcraftdream/garnet-framework/errors.log'),
];

export function clearServerErrorLogs(): void {
    for (const dir of ERROR_DIRS) {
        if (!fs.existsSync(dir)) continue;
        // Wipe everything under the Errors dir but keep the dir itself
        // so the logger doesn't have to recreate it on first write.
        for (const entry of fs.readdirSync(dir)) {
            const full = path.join(dir, entry);
            fs.rmSync(full, { recursive: true, force: true });
        }
    }
    for (const file of ERROR_FILES) {
        if (!fs.existsSync(file)) continue;
        // Truncate, don't unlink — the running php-cgi pool may hold
        // a write handle on it; recreating the file would orphan the
        // handle and subsequent writes would go nowhere visible.
        fs.writeFileSync(file, '');
    }
}

export interface ServerError {
    source: string;        // path relative to repo root
    excerpt: string;       // first ~10 lines, trimmed
}

export function collectServerErrors(): ServerError[] {
    const out: ServerError[] = [];

    // Per-file structured logs under LogJournal/Errors/<date>/*.log
    for (const dir of ERROR_DIRS) {
        if (!fs.existsSync(dir)) continue;
        const stack: string[] = [dir];
        while (stack.length) {
            const d = stack.pop()!;
            for (const entry of fs.readdirSync(d, { withFileTypes: true })) {
                const full = path.join(d, entry.name);
                if (entry.isDirectory()) {
                    stack.push(full);
                } else if (entry.isFile() && entry.name.endsWith('.log')) {
                    const content = fs.readFileSync(full, 'utf-8').trim();
                    if (!content) continue;
                    out.push({
                        source: path.relative(ROOT, full).replace(/\\/g, '/'),
                        excerpt: content.split('\n').slice(0, 10).join('\n'),
                    });
                }
            }
        }
    }

    // Plain aggregate logs — anything non-empty is a hit.
    for (const file of ERROR_FILES) {
        if (!fs.existsSync(file)) continue;
        const content = fs.readFileSync(file, 'utf-8').trim();
        if (!content) continue;
        out.push({
            source: path.relative(ROOT, file).replace(/\\/g, '/'),
            excerpt: content.split('\n').slice(-20).join('\n'),
        });
    }

    return out;
}

export function formatServerErrors(errors: ServerError[]): string {
    return errors.map((e, i) =>
        `  ${i + 1}. ${e.source}\n` +
        e.excerpt.split('\n').map((l) => '       ' + l).join('\n')
    ).join('\n\n');
}
