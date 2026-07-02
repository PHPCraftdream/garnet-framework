/**
 * Regression guard (deterministic, static): the low-level API layer must NOT
 * transitively import a React component module.
 *
 * Background: importing a React/JSX component module (GlobalToast.tsx) from
 * the API layer (via maintenance503 → asyncJsonThen / sendPostFormData)
 * drags React into the deepest shared chunk and crashes island hydration on
 * pages that mount the API layer before React is ready. The framework's
 * `Common/Api/*` files must stay React-free; UI code that needs to react to
 * API events imports the event contract (`toastEvent.ts`) instead of the
 * component. This test walks the import graph from the API entrypoints and
 * fails if GlobalToast.tsx (the React component) becomes reachable again —
 * catching the regression at the source, without depending on a flaky load
 * race.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

// This spec ships inside the app template (`Templates/Application/Tests/
// specs/framework-bundle/`) and gets copied into every app scaffolded via
// `garnet app:create`. In that (real) context the framework's `Bundle/Front`
// is only reachable through the composer path-repo symlink at
// `vendor/phpcraftdream/garnet-framework/`, never by walking up the
// directory tree — the app's location relative to the framework checkout is
// not guaranteed (cross-drive `app:create --target=` is explicitly
// supported).
const REPO = process.env.PW_APP_DIR ?? path.resolve(__dirname, '..', '..', '..');
const FRONT = path.join(REPO, 'vendor', 'phpcraftdream', 'garnet-framework', 'Bundle', 'Front');

function resolveSpec(spec: string, fromFile: string): string | null {
    let base: string;
    if (spec.startsWith('@common/')) base = path.join(FRONT, 'Common', spec.slice('@common/'.length));
    else if (spec.startsWith('@framework/')) base = path.join(FRONT, spec.slice('@framework/'.length));
    else if (spec.startsWith('.')) base = path.resolve(path.dirname(fromFile), spec);
    else return null; // bare node_modules import — not a source module we track

    for (const cand of [`${base}.ts`, `${base}.tsx`, path.join(base, 'index.ts'), path.join(base, 'index.tsx'), base]) {
        if (fs.existsSync(cand) && fs.statSync(cand).isFile()) return cand;
    }
    return null;
}

function importSpecs(file: string): string[] {
    const src = fs.readFileSync(file, 'utf8');
    const specs: string[] = [];
    const re = /(?:\bimport\b|\bexport\b)[^'"]*?\bfrom\s*['"]([^'"]+)['"]|\bimport\(\s*['"]([^'"]+)['"]\s*\)/g;
    let m: RegExpExecArray | null;
    while ((m = re.exec(src)) !== null) specs.push(m[1] || m[2]);
    return specs;
}

test('low-level API layer never imports the GlobalToast React component (React #130 regression)', () => {
    const entries = [
        'Common/Api/asyncJsonThen.ts',
        'Common/Api/asyncTextThen.ts',
        'Common/Api/sendPostFormData.ts',
        'Common/Api/sendPost.ts',
        'Common/Api/maintenance503.ts',
    ].map((p) => path.join(FRONT, p));

    const banned = path.join(FRONT, 'Common', 'Components', 'GlobalToast.tsx');
    expect(fs.existsSync(banned), 'GlobalToast.tsx should exist').toBe(true);

    const seen = new Set<string>();
    const stack = [...entries];
    while (stack.length > 0) {
        const file = stack.pop()!;
        if (seen.has(file)) continue;
        seen.add(file);
        for (const spec of importSpecs(file)) {
            const resolved = resolveSpec(spec, file);
            if (resolved && !seen.has(resolved)) stack.push(resolved);
        }
    }

    const reachesGlobalToast = seen.has(banned);
    expect(
        reachesGlobalToast,
        'The API layer transitively imports GlobalToast.tsx (a React component) — this pulls React/JSX into the\n' +
        'deepest shared chunk and crashes island hydration on any page that mounts the API layer before React is\n' +
        'ready. Import the toast event contract from @common/Components/toastEvent instead.',
    ).toBe(false);
});
