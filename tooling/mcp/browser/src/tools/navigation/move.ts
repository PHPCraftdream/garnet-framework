/**
 * Переходы: адрес, размер окна, назад и вперёд.
 */

import type { SessionManager } from '../../sessions.ts';
import type { ToolResult } from '../../types.ts';
import { textResult } from '../../utils.ts';
import { toNativePath } from './paths.ts';

export async function handleNavigate(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const url = args.url as string;
  if (!session || !url) return textResult('Missing required parameters: session, url', true);

  const page = sm.getPage(session);
  // Resolve against the session's own base URL when one was set at create
  // time (e.g. `session_create(role, { baseUrl: 'https://example.com' })`),
  // not the global default — otherwise prod-session navigates went to
  // http://localhost:8001 instead.
  const fullUrl = url.startsWith('http') ? url : sm.getBaseUrl(session) + url;

  await page.goto(fullUrl, { waitUntil: 'domcontentloaded' });
  await sm.waitForIdle(session);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(formatted);
}

const DEVICE_PRESETS: Record<string, { width: number; height: number }> = {
  mobile: { width: 390, height: 844 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1280, height: 800 },
};

export async function handleViewport(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const device = args.device as string | undefined;
  const preset = device ? DEVICE_PRESETS[device] : undefined;
  const width = (args.width as number) ?? preset?.width;
  const height = (args.height as number) ?? preset?.height;
  if (!width || !height) {
    return textResult('Provide width+height or a device preset (mobile/tablet/desktop)', true);
  }

  const page = sm.getPage(session);
  await page.setViewportSize({ width, height });
  // Let media queries settle and any resize handlers run before snapshotting.
  await page.waitForTimeout(150);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(`Viewport set to ${width}×${height}${device ? ` (${device})` : ''}\n${formatted}`);
}

export async function handleBack(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const response = await page.goBack({ waitUntil: 'domcontentloaded' });
  if (response === null) {
    return textResult('No previous page in history — already at the first entry.');
  }
  await sm.waitForIdle(session);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(formatted);
}

export async function handleForward(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const response = await page.goForward({ waitUntil: 'domcontentloaded' });
  if (response === null) {
    return textResult('No next page in history — already at the latest entry.');
  }
  await sm.waitForIdle(session);
  const { formatted } = await sm.captureAndDiff(session);
  return textResult(formatted);
}
