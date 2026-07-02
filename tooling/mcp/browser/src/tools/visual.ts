import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult, imageResult } from '../utils.ts';

// Shared image options schema — reused by screenshot and inspect
const imageOptions = {
  selector: {
    type: 'string',
    description: 'CSS selector to capture only that element (e.g. "[data-test-id=\\"grid\\"]", ".main-content", "table")',
  },
  fullPage: {
    type: 'boolean',
    description: 'Capture the full scrollable page (default false). Ignored if selector is set.',
  },
  format: {
    type: 'string',
    enum: ['png', 'jpeg'],
    description: 'Image format (default png). Use jpeg with low quality for token savings.',
  },
  quality: {
    type: 'number',
    description: 'JPEG quality 1-100 (default 80). Lower = smaller = fewer tokens. Only for jpeg format.',
  },
  grayscale: {
    type: 'boolean',
    description: 'Convert to grayscale. Saves tokens when colors are irrelevant.',
  },
  contrast: {
    type: 'number',
    description: 'CSS contrast filter value (default 1.0). Use >1 to boost, <1 to fade. Combine with grayscale for document-like output.',
  },
  scale: {
    type: 'number',
    description: 'Scale factor 0.1-1.0 (default 1.0). Use 0.5 for half-size, saves ~75% tokens.',
  },
  colors: {
    type: 'number',
    description: 'Total color count: 8, 16, 32, 64, 128, 256, 512, 4096 etc (default: full). Converted to per-channel levels via cube root. Drastically reduces image size.',
  },
  preset: {
    type: 'string',
    enum: ['wire', 'fast', 'std', 'detail'],
    description: 'Preset: wire (2-color wireframe ~3KB), fast (grayscale 64-color ~15KB), std (jpeg q60 ~40KB), detail (full PNG ~200KB). Individual options override preset values.',
  },
} as const;

export const tools: ToolDef[] = [
  {
    name: 'screenshot',
    description:
      'Take a screenshot (default: JPEG q60 — token-efficient). ' +
      'IMPORTANT: Always minimize image size. Prefer preset:"wire" or preset:"fast" unless you specifically need color or detail. ' +
      'Use selector to capture only the relevant element instead of the full page. ' +
      'Only use format:"png" when pixel-perfect accuracy is required. ' +
      'Presets: wire (~5KB), fast (~15KB), std (~40KB), detail (~200KB).',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        ...imageOptions,
      },
      required: ['session'],
    },
  },
  {
    name: 'text',
    description:
      'Extract visible text content from the page or a specific CSS selector.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        selector: {
          type: 'string',
          description: 'CSS selector to extract text from (default "main", falls back to "body")',
        },
        maxLen: {
          type: 'number',
          description: 'Maximum text length to return (default 2000)',
        },
      },
      required: ['session'],
    },
  },
  {
    name: 'inspect',
    description:
      'Capture a screenshot along with a page description in one call. ' +
      'Without selector: returns page describe + screenshot. ' +
      'With selector: returns element HTML + element screenshot. ' +
      'IMPORTANT: Always use preset:"wire" or preset:"fast" unless color is needed. ' +
      'Always use selector to capture only the relevant area.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        ...imageOptions,
        htmlDepth: {
          type: 'number',
          description: 'Max HTML nesting depth when selector is set (default 3)',
        },
      },
      required: ['session'],
    },
  },
  {
    name: 'dom',
    description:
      'Get the outer HTML of an element, optionally truncated by depth. ' +
      'Useful for inspecting DOM structure around a specific element.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        selector: {
          type: 'string',
          description: 'CSS selector for the element',
        },
        depth: {
          type: 'number',
          description: 'Max nesting depth to include (default 3)',
        },
        maxLen: {
          type: 'number',
          description: 'Max output length in characters (default 3000)',
        },
      },
      required: ['session', 'selector'],
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
      case 'screenshot':
        return await handleScreenshot(args, sm);
      case 'inspect':
        return await handleInspect(args, sm);
      case 'text':
        return await handleText(args, sm);
      case 'dom':
        return await handleDom(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

// ── Image capture helper ──────────────────────────────────────────

interface ImageOpts {
  selector?: string;
  fullPage?: boolean;
  format?: string;
  quality?: number;
  grayscale?: boolean;
  contrast?: number;
  scale?: number;
  colors?: number;
}

// Presets for common screenshot modes — override individual options
const PRESETS: Record<string, Partial<ImageOpts>> = {
  // Wireframe: just shapes and text, maximum compression
  wire:    { format: 'jpeg', quality: 15, grayscale: true, colors: 8, contrast: 2.0 },
  // Fast: low quality grayscale, good enough for layout checks
  fast:    { format: 'jpeg', quality: 25, grayscale: true, colors: 64 },
  // Balanced: readable with moderate compression (default-like)
  std:     { format: 'jpeg', quality: 60 },
  // Detailed: full quality PNG
  detail:  { format: 'png' },
};

function parseImageOpts(args: Record<string, unknown>): ImageOpts {
  // Apply preset first, then individual overrides
  const preset = args.preset as string | undefined;
  const presetOpts = preset && PRESETS[preset] ? PRESETS[preset] : {};

  return {
    selector: args.selector as string | undefined,
    fullPage: (args.fullPage as boolean) ?? presetOpts.fullPage ?? false,
    format: (args.format as string) ?? presetOpts.format ?? 'jpeg',
    quality: (args.quality as number | undefined) ?? presetOpts.quality,
    grayscale: (args.grayscale as boolean | undefined) ?? presetOpts.grayscale ?? false,
    contrast: (args.contrast as number | undefined) ?? presetOpts.contrast,
    scale: (args.scale as number | undefined) ?? presetOpts.scale,
    colors: (args.colors as number | undefined) ?? presetOpts.colors,
  };
}

async function captureImage(
  page: import('playwright').Page,
  opts: ImageOpts,
): Promise<{ base64: string; mimeType: string; info: string }> {
  // Always JPEG — no PNG option, saves tokens
  const type = 'jpeg' as const;
  const quality = opts.quality ?? 60;
  const scale = opts.scale && opts.scale > 0 && opts.scale < 1 ? opts.scale : undefined;

  // Convert total color count to per-channel levels via cube root
  // colors:8 → 2 per channel, colors:64 → 4, colors:256 → ~6, colors:512 → 8, colors:4096 → 16
  let channelLevels: number | undefined;
  if (opts.colors !== undefined && opts.colors >= 2) {
    channelLevels = Math.max(2, Math.min(256, Math.round(Math.cbrt(opts.colors))));
  }

  // Apply CSS filters and color quantization
  const needsFilter = opts.grayscale || (opts.contrast !== undefined && opts.contrast !== 1);
  const needsPosterize = channelLevels !== undefined && channelLevels >= 2 && channelLevels < 256;
  const needsModify = needsFilter || needsPosterize;

  if (needsModify) {
    // Build filter CSS + optional SVG posterize string on the server side
    // to avoid tsx __name issues with complex functions in page.evaluate
    const filterParts: string[] = [];
    if (opts.grayscale) filterParts.push('grayscale(1)');
    if (opts.contrast !== undefined && opts.contrast !== 1) filterParts.push(`contrast(${opts.contrast})`);

    let svgHtml = '';
    if (needsPosterize && channelLevels) {
      const steps: number[] = [];
      for (let i = 0; i < channelLevels; i++) {
        steps.push(Math.round((i / (channelLevels - 1)) * 1000) / 1000);
      }
      const table = steps.join(' ');
      svgHtml = `<svg xmlns="http://www.w3.org/2000/svg"><filter id="__garnet-posterize"><feComponentTransfer>` +
        `<feFuncR type="discrete" tableValues="${table}"/>` +
        `<feFuncG type="discrete" tableValues="${table}"/>` +
        `<feFuncB type="discrete" tableValues="${table}"/>` +
        `</feComponentTransfer></filter></svg>`;
      filterParts.push('url(#__garnet-posterize)');
    }

    const filterCss = filterParts.join(' ');
    await page.evaluate(
      ([css, svg]) => {
        if (svg) {
          var old = document.getElementById('__garnet-posterize-svg');
          if (old) old.remove();
          var div = document.createElement('div');
          div.id = '__garnet-posterize-svg';
          div.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;pointer-events:none';
          div.innerHTML = svg;
          document.body.appendChild(div);
        }
        document.documentElement.style.filter = css;
      },
      [filterCss, svgHtml] as [string, string],
    );
  }

  // Apply scaling via viewport resize
  let origViewport: { width: number; height: number } | null = null;
  if (scale) {
    const vp = page.viewportSize();
    if (vp) {
      origViewport = vp;
      await page.setViewportSize({
        width: Math.round(vp.width * scale),
        height: Math.round(vp.height * scale),
      });
    }
  }

  try {
    let buffer: Buffer;

    if (opts.selector) {
      // Element screenshot
      const locator = page.locator(opts.selector).first();
      buffer = await locator.screenshot({ type, quality, timeout: 5000 });
    } else {
      // Full page or viewport
      buffer = await page.screenshot({
        fullPage: opts.fullPage,
        type,
        quality,
      });
    }

    const base64 = buffer.toString('base64');
    const mimeType = type === 'jpeg' ? 'image/jpeg' : 'image/png';

    // Build info string
    const parts: string[] = [];
    parts.push(type.toUpperCase());
    if (quality) parts.push(`q${quality}`);
    if (opts.grayscale) parts.push('grayscale');
    if (opts.contrast !== undefined && opts.contrast !== 1) parts.push(`contrast:${opts.contrast}`);
    if (channelLevels) parts.push(`${channelLevels}lvl/ch (${channelLevels ** 3} colors, requested ${opts.colors})`);
    if (scale) parts.push(`scale:${scale}`);
    if (opts.selector) parts.push(`selector:${opts.selector}`);
    const sizeKb = Math.round(buffer.length / 1024);
    parts.push(`${sizeKb}KB`);

    // Feedback: nudge AI to reduce size when image is large
    let hint = '';
    if (sizeKb > 50 && !opts.selector) {
      hint = '\n⚠ Large image. Use selector to capture only the relevant element.';
    }
    if (sizeKb > 30 && type !== 'jpeg') {
      hint += '\n⚠ Use format:"jpeg" or preset:"fast" to reduce size.';
    }
    if (sizeKb > 30 && !opts.grayscale && opts.colors === undefined) {
      hint += '\n⚠ Add grayscale:true or colors:64 to reduce size.';
    }

    return { base64, mimeType, info: parts.join(' ') + hint };
  } finally {
    // Restore filter and remove posterize SVG
    if (needsModify) {
      await page.evaluate(() => {
        document.documentElement.style.filter = '';
        const svg = document.getElementById('__garnet-posterize-svg');
        if (svg) svg.remove();
      }).catch(() => {});
    }
    // Restore viewport
    if (origViewport) {
      await page.setViewportSize(origViewport).catch(() => {});
    }
  }
}

// ── Tool handlers ─────────────────────────────────────────────────

async function handleScreenshot(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const opts = parseImageOpts(args);
  const { base64, mimeType, info } = await captureImage(page, opts);

  return {
    content: [
      { type: 'text' as const, text: info },
      { type: 'image' as const, data: base64, mimeType },
    ],
  };
}

async function handleInspect(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const opts = parseImageOpts(args);
  const htmlDepth = (args.htmlDepth as number) ?? 3;

  // If selector is provided, return element HTML + screenshot
  // If no selector, return page describe + screenshot
  if (opts.selector) {
    // Get HTML first, then screenshot (sequential to avoid __name issues with Promise.all)
    const html = await page.evaluate(
      `(function(){ return window.__getDomHtml(${JSON.stringify(opts.selector)}, ${htmlDepth}); })()`,
    ) as string;
    const image = await captureImage(page, opts);

    const truncatedHtml = html.length > 3000 ? html.slice(0, 3000) + '\n... (truncated)' : html;

    return {
      content: [
        { type: 'text' as const, text: `${truncatedHtml}\n\n${image.info}` },
        { type: 'image' as const, data: image.base64, mimeType: image.mimeType },
      ],
    };
  }

  // No selector — page-level inspect
  const [description, image] = await Promise.all([
    page.evaluate(() => (window as any).__describePage()) as Promise<string>,
    captureImage(page, opts),
  ]);

  return {
    content: [
      { type: 'text' as const, text: `${description}\n\n${image.info}` },
      { type: 'image' as const, data: image.base64, mimeType: image.mimeType },
    ],
  };
}

async function handleText(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const selector = (args.selector as string) || 'main';
  const maxLen = (args.maxLen as number) ?? 2000;

  let text: string;
  try {
    text = await page.locator(selector).innerText({ timeout: 3000 });
  } catch {
    // Fall back to body if 'main' doesn't exist
    if (selector === 'main') {
      text = await page.locator('body').innerText({ timeout: 3000 });
    } else {
      return textResult(`Element not found: ${selector}`, true);
    }
  }

  if (text.length > maxLen) {
    text = text.slice(0, maxLen) + `\n... (truncated, ${text.length} total chars)`;
  }

  return textResult(text);
}

async function handleDom(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const selector = args.selector as string;
  if (!session || !selector) {
    return textResult('Missing required parameters: session, selector', true);
  }

  const depth = (args.depth as number) ?? 3;
  const maxLen = (args.maxLen as number) ?? 3000;

  const page = sm.getPage(session);

  const html = await page.evaluate(
    `(function(){ return window.__getDomHtml(${JSON.stringify(selector)}, ${depth}); })()`,
  );

  const htmlStr = html as string | null;
  if (htmlStr === null) {
    return textResult(`Element not found: ${selector}`, true);
  }

  let output = htmlStr;
  if (output.length > maxLen) {
    output = output.slice(0, maxLen) + '\n... (truncated)';
  }

  return textResult(output);
}
