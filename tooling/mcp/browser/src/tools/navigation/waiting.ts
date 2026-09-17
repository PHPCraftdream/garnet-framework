/**
 * Ожидание, диалоги браузера и прокрутка.
 */

import type { SessionManager } from '../../sessions.ts';
import type { ToolResult } from '../../types.ts';
import { textResult } from '../../utils.ts';

export async function handleWait(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const testid = args.testid as string;
  const timeout = (args.timeout as number) ?? 5000;
  if (!session || !testid) return textResult('Missing required parameters: session, testid', true);

  const page = sm.getPage(session);
  const start = Date.now();

  try {
    await page.waitForSelector(sm.testidSelector(testid), { timeout });
    const elapsed = Date.now() - start;
    return textResult(`Found: ${testid} after ${elapsed}ms`);
  } catch {
    return textResult(`Timeout: ${testid} not found after ${timeout}ms`, true);
  }
}

export async function handleDialog(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const action = args.action as string;
  if (!session || !action) return textResult('Missing required parameters: session, action', true);

  const page = sm.getPage(session);
  const promptText = args.promptText as string | undefined;

  page.once('dialog', async (dialog) => {
    try {
      if (action === 'accept') {
        await dialog.accept(promptText);
      } else {
        await dialog.dismiss();
      }
    } catch { /* dialog may have been handled already */ }
  });

  return textResult(`Dialog handler set: will ${action} next dialog`);
}

export async function handleScroll(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const testid = args.testid as string | undefined;
  const selector = args.selector as string | undefined;

  // Scroll element into view
  if (testid || selector) {
    const sel = testid ? sm.testidSelector(testid) : selector!;
    await page.locator(sel).first().scrollIntoViewIfNeeded({ timeout: 5000 });
    const { formatted } = await sm.captureAndDiff(session);
    return textResult(`Scrolled to: ${sel}\n${formatted}`);
  }

  // Directional scroll
  const direction = (args.direction as string) || 'down';
  const vp = page.viewportSize();
  const viewportH = vp?.height ?? 720;
  const defaultPx = Math.round(viewportH * 0.8);
  const pixels = (args.pixels as number) || defaultPx;

  switch (direction) {
    case 'down':
      await page.mouse.wheel(0, pixels);
      break;
    case 'up':
      await page.mouse.wheel(0, -pixels);
      break;
    case 'top':
      await page.evaluate(() => window.scrollTo(0, 0));
      break;
    case 'bottom':
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      break;
  }

  // Small delay for scroll animations
  await page.waitForTimeout(200);

  const scrollY = await page.evaluate(() => Math.round(window.scrollY));
  const scrollMax = await page.evaluate(() => Math.round(document.body.scrollHeight - window.innerHeight));
  return textResult(`Scrolled ${direction}. Position: ${scrollY}/${scrollMax}px`);
}
