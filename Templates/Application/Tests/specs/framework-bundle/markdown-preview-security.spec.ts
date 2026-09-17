/**
 * Security guard for the admin markdown preview.
 *
 * The preview uses the same converter as the application source and must not
 * execute raw HTML or unsafe link protocols in an editor's browser.
 */
import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {transformSync} from 'esbuild';

function frameworkRoot(): string {
    if (process.env.GARNET_FRAMEWORK_PATH) return process.env.GARNET_FRAMEWORK_PATH;

    const candidates = [
        resolve(process.cwd(), '../../../'),
        resolve(process.cwd(), '../vendor/phpcraftdream/garnet-framework'),
    ];

    for (const candidate of candidates) {
        if (readFile(candidate, 'Bundle/Front/Common/Utils/Ui/markdownToHtml.ts')) return candidate;
    }

    throw new Error(`Could not find framework root. Tried: ${candidates.join(', ')}`);
}

function readFile(root: string, relative: string): boolean {
    try {
        readFileSync(resolve(root, relative), 'utf8');
        return true;
    } catch {
        return false;
    }
}

function browserCode(root: string): string {
    const source = readFileSync(resolve(root, 'Bundle/Front/Common/Utils/Ui/markdownToHtml.ts'), 'utf8')
        .replace(/import \{appUrl\} from ['"]@common\/Utils\/Url\/appUrl['"];\r?\n/, 'const appUrl = (path: string): string => path;\n')
        .replace(/import DOMPurify from ['"]dompurify['"];\r?\n/, 'const DOMPurify = (globalThis as any).DOMPurify;\n')
        .replace('export function markdownToHtml', 'function markdownToHtml');

    return transformSync(`${source}\n(globalThis as any).markdownToHtml = markdownToHtml;`, {
        loader: 'ts',
        format: 'iife',
        target: 'es2020',
    }).code;
}

test('markdown preview strips executable HTML and unsafe links', async ({page}) => {
    const root = frameworkRoot();
    const purify = readFileSync(resolve(root, 'FrontBuilder/node_modules/dompurify/dist/purify.min.js'), 'utf8');

    await page.setContent('<!doctype html><html><body></body></html>');
    await page.addScriptTag({content: purify});
    await page.addScriptTag({content: browserCode(root)});

    const result = await page.evaluate(() => {
        const convert = (globalThis as any).markdownToHtml as (value: string) => string;
        const html = convert('<img src=x onerror="window.__markdownXss = 1">\n\n[click](javascript:window.__markdownXss = 2)');
        document.body.innerHTML = html;
        return {
            html,
            xss: (window as any).__markdownXss ?? 0,
            hasEventHandler: Boolean(document.querySelector('[onerror]')),
            hasJavascriptUrl: Array.from(document.querySelectorAll('a')).some(a => a.href.startsWith('javascript:')),
        };
    });

    expect(result.xss).toBe(0);
    expect(result.hasEventHandler).toBe(false);
    expect(result.hasJavascriptUrl).toBe(false);
    expect(result.html).not.toContain('<img');
    expect(result.html).not.toContain('javascript:');
});
