import {RuleTester} from 'oxlint/plugins-dev';
import plugin from './garnet-tsx-rules.js';

const tester = new RuleTester({languageOptions: {parserOptions: {ecmaFeatures: {jsx: true}}}});

const oneExport = plugin.rules['one-export-per-component'];
const maxLines = plugin.rules['max-component-lines'];
const maxNesting = plugin.rules['max-component-nesting'];

try {
    tester.run('one-export-per-component', oneExport, {
        valid: [
            {code: 'export const Foo = () => <div />;', filename: 'Foo.tsx'},
            {code: 'export default function Foo() { return <div />; }', filename: 'Foo.tsx'},
            {
                code: 'interface Props { x: number }\nexport const Foo = (p: Props) => <div />;',
                filename: 'Foo.tsx',
            },
            {
                code: 'export type Props = { x: number };\nexport const Foo = (p: Props) => <div />;',
                filename: 'Foo.tsx',
            },
            {
                code: 'export type { Bar } from "./bar";\nexport const Foo = () => <div />;',
                filename: 'Foo.tsx',
            },
            // Non-.tsx files are out of scope for this rule.
            {code: 'export const a = 1;\nexport const b = 2;', filename: 'utils.ts'},
        ],
        invalid: [
            {
                code: 'export const Foo = () => <div />;\nexport const Bar = () => <div />;',
                filename: 'Foo.tsx',
                errors: 1,
            },
            {
                code: 'export function Foo() { return <div />; }\nexport function helper() { return 1; }',
                filename: 'Foo.tsx',
                errors: 1,
            },
        ],
    });

    tester.run('max-component-lines', maxLines, {
        valid: [
            {code: 'export const Foo = () => <div />;', filename: 'Foo.tsx'},
            {code: Array.from({length: 500}, () => '//').join('\n'), filename: 'Foo.tsx'},
        ],
        invalid: [
            {code: Array.from({length: 501}, () => '//').join('\n'), filename: 'Foo.tsx', errors: 1},
        ],
    });

    tester.run('max-component-nesting', maxNesting, {
        valid: [
            // 2 levels of components — within the 3-level limit.
            {
                code: 'export const Foo = () => <A><B /></A>;',
                filename: 'Foo.tsx',
            },
            // Deeply nested HTML tags don't count toward component depth.
            {
                code: 'export const Foo = () => <div><div><div><div><div /></div></div></div></div>;',
                filename: 'Foo.tsx',
            },
            // Exactly 3 levels — at the limit, not over it.
            {
                code: 'export const Foo = () => <A><B><C /></B></A>;',
                filename: 'Foo.tsx',
            },
        ],
        invalid: [
            // 4 levels of components — one past the limit.
            {
                code: 'export const Foo = () => <A><B><C><D /></C></B></A>;',
                filename: 'Foo.tsx',
                errors: 1,
            },
        ],
    });

    console.log('All garnet-tsx-rules tests passed.');
} catch (err) {
    console.error('garnet-tsx-rules test FAILED:', err);
    process.exitCode = 1;
}
