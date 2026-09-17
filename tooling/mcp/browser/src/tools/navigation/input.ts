/**
 * Ввод: клик, заполнение, выбор в списке, клавиатура, наведение,
 * загрузка файла.
 */

import type { SessionManager } from '../../sessions.ts';
import type { ToolResult } from '../../types.ts';
import { textResult } from '../../utils.ts';
import { toNativePath } from './paths.ts';

export async function handleClick(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const testid = args.testid as string;
  const waitForUrl = args.waitForUrl as string | undefined;
  if (!session || !testid) return textResult('Missing required parameters: session, testid', true);

  const page = sm.getPage(session);
  const selector = sm.testidSelector(testid);

  // Register a one-shot dialog handler in case the click triggers a dialog
  let dialogMsg: string | null = null;
  const dialogHandler = (dialog: import('playwright').Dialog) => {
    dialogMsg = `${dialog.type()}: "${dialog.message()}"`;
    dialog.accept().catch(() => {});
  };
  page.once('dialog', dialogHandler);

  let matchedResponse: { status: number; url: string; body: string } | null = null;

  try {
    if (waitForUrl) {
      // Wait for a specific network response while clicking
      const [response] = await Promise.all([
        page.waitForResponse(
          (res) => res.url().includes(waitForUrl),
          { timeout: 15000 },
        ),
        page.click(selector, { timeout: 10000 }),
      ]);

      // Capture response details
      const ct = response.headers()['content-type'] || '';
      let body = '';
      if (ct.includes('json') || ct.includes('text')) {
        try {
          body = await response.text();
          if (body.length > 1000) body = body.substring(0, 1000) + '...';
        } catch { body = '(could not read body)'; }
      }
      matchedResponse = {
        status: response.status(),
        url: new URL(response.url()).pathname,
        body,
      };

      await sm.waitForIdle(session);
    } else {
      await page.click(selector, { timeout: 10000 });
      await sm.waitForIdle(session);
    }
  } finally {
    page.removeListener('dialog', dialogHandler);
  }

  const { formatted } = await sm.captureAndDiff(session);
  const parts: string[] = [];
  if (dialogMsg) parts.push(`dialog ${dialogMsg}`);
  if (matchedResponse) {
    parts.push(`response: ${matchedResponse.url} → ${matchedResponse.status}`);
    if (matchedResponse.body) parts.push(matchedResponse.body);
  }
  parts.push(formatted);
  return textResult(parts.join('\n'));
}

export async function handleFill(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const testid = args.testid as string;
  const value = args.value as string;
  if (!session || !testid || value === undefined) {
    return textResult('Missing required parameters: session, testid, value', true);
  }

  const page = sm.getPage(session);
  await page.fill(sm.testidSelector(testid), value, { timeout: 5000 });
  return textResult(`Filled: ${testid} = "${value}"`);
}

export async function handleSelectOption(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const testid = args.testid as string;
  const value = args.value as string;
  if (!session || !testid || !value) {
    return textResult('Missing required parameters: session, testid, value', true);
  }

  const page = sm.getPage(session);
  const selector = sm.testidSelector(testid);

  // Try by value first, then by label
  const selected = await page.selectOption(selector, { value }, { timeout: 5000 }).catch(
    () => page.selectOption(selector, { label: value }, { timeout: 5000 }),
  );

  return textResult(`Selected: ${testid} = "${value}" (${selected.length} option(s))`);
}

export async function handleKeyboard(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const key = args.key as string;
  if (!session || !key) return textResult('Missing required parameters: session, key', true);

  const page = sm.getPage(session);
  await page.keyboard.press(key);
  await sm.waitForIdle(session);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(`Pressed: ${key}\n${formatted}`);
}

export async function handleHover(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const testid = args.testid as string;
  if (!session || !testid) return textResult('Missing required parameters: session, testid', true);

  const page = sm.getPage(session);
  await page.hover(sm.testidSelector(testid), { timeout: 5000 });
  // Short delay for CSS transitions / tooltip animations
  await page.waitForTimeout(300);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(`Hovered: ${testid}\n${formatted}`);
}

export async function handleUpload(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const files = args.files as string[];
  if (!session || !files?.length) {
    return textResult('Missing required parameters: session, files (non-empty array)', true);
  }

  const testid = args.testid as string | undefined;
  const selector = args.selector as string | undefined;
  const sel = testid ? sm.testidSelector(testid) : selector;
  if (!sel) {
    return textResult('Provide testid or selector to target the <input type="file">', true);
  }

  const page = sm.getPage(session);
  await page.setInputFiles(sel, files.map(toNativePath), { timeout: 5000 });
  await sm.waitForIdle(session);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(`Uploaded ${files.length} file(s) to ${sel}\n${formatted}`);
}
