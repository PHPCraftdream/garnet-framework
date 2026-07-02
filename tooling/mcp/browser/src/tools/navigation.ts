import { resolve as pathResolve } from 'node:path';
import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult } from '../utils.ts';

/**
 * Normalise a file path so `/tmp/x.png`, `D:\tmp\x.png`, `D:/tmp/x.png`
 * and `C:\Users\...` all resolve to the correct native absolute path on
 * the current OS. Handles Git-Bash/MSYS `/c/Users/…` → `C:\Users\…` on
 * Windows and is a no-op on POSIX for already-absolute paths.
 */
function toNativePath(p: string): string {
  // Git-Bash / MSYS mount: /c/Users/… → C:\Users\…
  if (process.platform === 'win32' && /^\/[a-zA-Z]\//.test(p)) {
    p = p[1].toUpperCase() + ':' + p.slice(2);
  }
  return pathResolve(p);
}

export const tools: ToolDef[] = [
  {
    name: 'navigate',
    description:
      'Navigate to a URL in the specified session. Returns a diff of page state changes.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        url: { type: 'string', description: 'URL path to navigate to (appended to base URL)' },
      },
      required: ['session', 'url'],
    },
  },
  {
    name: 'viewport',
    description:
      'Resize the session viewport to test responsive layouts. Pass an explicit width/height, ' +
      'or a device preset (mobile 390×844, tablet 768×1024, desktop 1280×800). Explicit width/height ' +
      'override the preset. Returns a page-state diff after the resize.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        device: {
          type: 'string',
          enum: ['mobile', 'tablet', 'desktop'],
          description: 'Preset viewport. Overridden by explicit width/height.',
        },
        width: { type: 'number', description: 'Viewport width in CSS px' },
        height: { type: 'number', description: 'Viewport height in CSS px' },
      },
      required: ['session'],
    },
  },
  {
    name: 'back',
    description:
      'Navigate back in the session history (browser Back button). Returns a diff of page state changes. ' +
      'No-op (returns a notice) when there is no previous page in history.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'forward',
    description:
      'Navigate forward in the session history (browser Forward button). Returns a diff of page state changes. ' +
      'No-op (returns a notice) when there is no next page in history.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'click',
    description:
      'Click an element by its test ID. Waits for network idle then returns page state diff. ' +
      'Optionally wait for a specific network response pattern and include its body in the result.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value of the element to click' },
        waitForUrl: {
          type: 'string',
          description: 'URL substring to wait for in network response (e.g. "~createTicket"). If set, includes response body in result.',
        },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'fill',
    description: 'Fill a form field identified by test ID with a value.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value of the input element' },
        value: { type: 'string', description: 'Value to type into the field' },
      },
      required: ['session', 'testid', 'value'],
    },
  },
  {
    name: 'select_option',
    description: 'Select an option in a <select> element by value or label.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the <select> element' },
        value: { type: 'string', description: 'Option value or visible text to select' },
      },
      required: ['session', 'testid', 'value'],
    },
  },
  {
    name: 'wait',
    description: 'Wait for an element with a specific test ID to appear on the page.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID value to wait for' },
        timeout: { type: 'number', description: 'Max wait time in ms (default 5000)' },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'dialog',
    description:
      'Set up a handler for the next browser dialog (alert/confirm/prompt). ' +
      'Call this BEFORE the action that triggers the dialog.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        action: {
          type: 'string',
          enum: ['accept', 'dismiss'],
          description: 'Whether to accept or dismiss the dialog',
        },
        promptText: {
          type: 'string',
          description: 'Text to enter for prompt dialogs (optional)',
        },
      },
      required: ['session', 'action'],
    },
  },
  {
    name: 'scroll',
    description:
      'Scroll the page or scroll to a specific element. ' +
      'Use direction for relative scroll, or testid/selector to scroll an element into view.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        direction: {
          type: 'string',
          enum: ['up', 'down', 'top', 'bottom'],
          description: 'Scroll direction. up/down scroll by ~80% viewport height. top/bottom go to extremes.',
        },
        pixels: {
          type: 'number',
          description: 'Override scroll distance in pixels (for up/down)',
        },
        testid: {
          type: 'string',
          description: 'Scroll element with this test ID into view (overrides direction)',
        },
        selector: {
          type: 'string',
          description: 'Scroll element matching CSS selector into view (overrides direction)',
        },
      },
      required: ['session'],
    },
  },
  {
    name: 'keyboard',
    description:
      'Press a key or key combination. Examples: "Enter", "Escape", "Tab", "ArrowDown", "Control+a", "Shift+Tab".',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        key: {
          type: 'string',
          description: 'Key or combo: Enter, Escape, Tab, ArrowDown, ArrowUp, Control+a, Shift+Tab, etc.',
        },
      },
      required: ['session', 'key'],
    },
  },
  {
    name: 'hover',
    description:
      'Hover over an element by test ID. Triggers CSS :hover states, tooltips, dropdowns. Returns page diff.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the element to hover' },
      },
      required: ['session', 'testid'],
    },
  },
  {
    name: 'upload',
    description:
      'Set files on a <input type="file"> element (simulates a file-picker selection). ' +
      'Target the input by testid or CSS selector. Pass one or more file paths. ' +
      'Returns a page-state diff after the files are set.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        testid: { type: 'string', description: 'Test ID of the <input type="file">' },
        selector: {
          type: 'string',
          description: 'CSS selector of the <input type="file"> (used when testid is not set)',
        },
        files: {
          type: 'array',
          items: { type: 'string' },
          description: 'Absolute path(s) to the file(s) to upload',
        },
      },
      required: ['session', 'files'],
    },
  },
];

export async function handle(
  name: string,
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  try {
    switch (name) {
      case 'navigate':
        return await handleNavigate(args, sm);
      case 'viewport':
        return await handleViewport(args, sm);
      case 'back':
        return await handleBack(args, sm);
      case 'forward':
        return await handleForward(args, sm);
      case 'click':
        return await handleClick(args, sm);
      case 'fill':
        return await handleFill(args, sm);
      case 'select_option':
        return await handleSelectOption(args, sm);
      case 'wait':
        return await handleWait(args, sm);
      case 'dialog':
        return await handleDialog(args, sm);
      case 'scroll':
        return await handleScroll(args, sm);
      case 'keyboard':
        return await handleKeyboard(args, sm);
      case 'hover':
        return await handleHover(args, sm);
      case 'upload':
        return await handleUpload(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

async function handleNavigate(
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

async function handleViewport(
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

async function handleBack(
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

async function handleForward(
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

async function handleClick(
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

async function handleFill(
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

async function handleSelectOption(
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

async function handleWait(
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

async function handleDialog(
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

async function handleScroll(
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

async function handleKeyboard(
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

async function handleHover(
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

async function handleUpload(
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
