import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'describe',
    description:
      'Get a compact human-readable description of the current page (~150 tokens). ' +
      'Includes headings, tables, forms, modals, toasts, and error count.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'testids',
    description:
      'List all test ID values currently present on the page. ' +
      'Useful for discovering clickable/interactive elements.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
    },
  },
  {
    name: 'react_tree',
    description:
      'Inspect the React/Preact component tree from a given element. ' +
      'Shows component names, key props, and error state. Useful for debugging React crashes.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        selector: {
          type: 'string',
          description: 'CSS selector for the root element (default: #root or body)',
        },
        depth: {
          type: 'number',
          description: 'Max tree depth (default 4)',
        },
      },
      required: ['session'],
    },
  },
  {
    name: 'page_state',
    description:
      'Get the full structured page state (URL, title, testids, forms, toasts, errors). ' +
      'Returns JSON. Use "describe" for a more compact overview.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
      },
      required: ['session'],
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
      case 'describe':
        return await handleDescribe(args, sm);
      case 'testids':
        return await handleTestids(args, sm);
      case 'react_tree':
        return await handleReactTree(args, sm);
      case 'page_state':
        return await handlePageState(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

async function handleDescribe(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const description = await page.evaluate(() => (window as any).__describePage());
  return textResult(description as string);
}

async function handleTestids(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const page = sm.getPage(session);
  const attr = sm.testidAttr;
  const testids = await page.evaluate((a: string) =>
    [...document.querySelectorAll(`[${a}]`)].map(
      (el) => el.getAttribute(a),
    ),
    attr,
  );

  if (testids.length === 0) {
    return textResult(`No ${attr} elements found on page`);
  }

  return textResult(testids.join('\n'));
}

async function handleReactTree(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const selector = (args.selector as string) || undefined;
  const depth = (args.depth as number) || 4;

  const page = sm.getPage(session);
  const tree = await page.evaluate(
    ({ sel, d }: { sel?: string; d: number }) =>
      (window as any).__getReactTree(sel, d),
    { sel: selector, d: depth },
  );

  return textResult(tree as string);
}

async function handlePageState(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  if (!session) return textResult('Missing required parameter: session', true);

  const state = await sm.captureState(session);
  return textResult(JSON.stringify(state, null, 2));
}
