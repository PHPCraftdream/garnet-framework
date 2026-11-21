#!/usr/bin/env node
// Every tracked .tsx must be seen by oxlint, tsgo and carburetor-lint.
// Files matched by .carburetorrc.json "ignore" are the only allowed gap.
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const fb = join(root, 'FrontBuilder');
const posix = (p) => p.split(sep).join('/');
const rel = (p) => posix(relative(root, resolve(root, p)));

function run(cmd, args, cwd) {
    const r = spawnSync(process.execPath, [cmd, ...args], { cwd, encoding: 'utf8', maxBuffer: 1 << 26 });
    if (r.error || !r.stdout) {
        throw new Error(`${cmd} ${args.join(' ')} failed: ${r.error?.message ?? r.stderr}`);
    }
    return r.stdout.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
}

const globRe = (g) => new RegExp('^' + g
    .replace(/[.+^${}()|[\]\\]/g, '\\$&')
    .replace(/\*\*\//g, '\u0000').replace(/\/\*\*/g, '\u0001').replace(/\*/g, '[^/]*')
    .replace(/\u0000/g, '(?:.*/)?').replace(/\u0001/g, '/.*') + '$');

const git = spawnSync('git', ['ls-files', '*.tsx'], { cwd: root, encoding: 'utf8' });
if (git.status !== 0) {
    throw new Error(`git ls-files failed: ${git.stderr}`);
}
const ignores = (JSON.parse(readFileSync(join(root, '.carburetorrc.json'), 'utf8')).ignore ?? []).map(globRe);
const expected = git.stdout.split(/\r?\n/).filter(Boolean).filter((f) => !ignores.some((re) => re.test(f)));

const scriptRoots = (name, bin) => {
    const cmd = JSON.parse(readFileSync(join(fb, 'package.json'), 'utf8')).scripts[name];
    const at = cmd.indexOf(bin + ' ');
    if (at < 0) throw new Error(`package.json script "${name}" does not run ${bin}`);
    return cmd.slice(at + bin.length + 1).split(/\s+/).filter((a) => a && !a.startsWith('-')).map(rel);
};
const under = (file, roots) => roots.some((r) => file === r || file.startsWith(r + '/'));

const oxRoots = scriptRoots('lint', 'oxlint');
const oxSeen = new Set(run(join(fb, 'node_modules/oxlint/bin/oxlint'), ['--debug=files', ...oxRoots], root).map(rel));
const tsgoSeen = new Set(run(join(fb, 'node_modules/@typescript/native-preview/bin/tsgo.js'),
    ['--listFilesOnly', '-p', 'tsconfig.tsgo.json'], fb).map((f) => rel(resolve(fb, f))));
const carbRoots = scriptRoots('lint:carburetor', 'carburetor-lint');

const gaps = [];
for (const f of expected) {
    if (!oxSeen.has(f)) gaps.push(`oxlint        ${f}`);
    if (!tsgoSeen.has(f)) gaps.push(`tsgo          ${f}`);
    if (!under(f, carbRoots)) gaps.push(`carburetor    ${f}`);
}

console.log(`tsx coverage: ${expected.length} tracked (${git.stdout.split('\n').filter(Boolean).length - expected.length} ignored); ` +
    `oxlint ${expected.filter((f) => oxSeen.has(f)).length}, tsgo ${expected.filter((f) => tsgoSeen.has(f)).length}, ` +
    `carburetor ${expected.filter((f) => under(f, carbRoots)).length}`);
if (gaps.length > 0) {
    console.error(`not covered:\n${gaps.join('\n')}`);
    process.exit(1);
}
