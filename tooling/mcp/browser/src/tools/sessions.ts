import { existsSync } from 'node:fs';
import { join } from 'node:path';
import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'session_create',
    description:
      'Create a new browser session for a role (e.g. student, teacher, admin). ' +
      'Uses stored auth state if available. Each role gets its own isolated browser context. ' +
      'Pass device or explicit viewport to create a mobile/tablet session.',
    inputSchema: {
      type: 'object',
      properties: {
        role: {
          type: 'string',
          description: 'Session name / role identifier (e.g. "student", "teacher", "admin")',
        },
        storageState: {
          type: 'string',
          description: 'Path to Playwright storageState JSON. If omitted, tries {AUTH_DIR}/{role}.json',
        },
        baseUrl: {
          type: 'string',
          description: 'Base URL override for this session (defaults to BASE_URL env var)',
        },
        device: {
          type: 'string',
          enum: ['mobile', 'tablet', 'desktop'],
          description: 'Viewport preset: mobile (390×844), tablet (768×1024), desktop (1280×800). Overridden by explicit width/height.',
        },
        width: { type: 'number', description: 'Viewport width in CSS px (overrides device preset)' },
        height: { type: 'number', description: 'Viewport height in CSS px (overrides device preset)' },
      },
      required: ['role'],
    },
  },
  {
    name: 'session_list',
    description: 'List all active browser sessions.',
    inputSchema: {
      type: 'object',
      properties: {},
    },
  },
  {
    name: 'session_close',
    description: 'Close a browser session (or all sessions if no role specified).',
    inputSchema: {
      type: 'object',
      properties: {
        role: {
          type: 'string',
          description: 'Session to close. If omitted, closes all sessions.',
        },
      },
    },
  },
];

export async function handle(
  name: string,
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  switch (name) {
    case 'session_create':
      return handleCreate(args, sm);
    case 'session_list':
      return handleList(sm);
    case 'session_close':
      return handleClose(args, sm);
    default:
      return textResult(`Unknown tool: ${name}`, true);
  }
}

async function handleCreate(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const role = args.role as string;
  if (!role) return textResult('Missing required parameter: role', true);

  const config = sm.getConfig();
  const baseUrl = (args.baseUrl as string) || config.baseUrl;

  // Resolve storage state
  let storageState = args.storageState as string | undefined;
  if (!storageState && config.authDir) {
    const candidate = join(config.authDir, `${role}.json`);
    if (existsSync(candidate)) {
      storageState = candidate;
    }
  }

  // Viewport: explicit width/height override device preset
  const DEVICE_PRESETS: Record<string, { width: number; height: number }> = {
    mobile: { width: 390, height: 844 },
    tablet: { width: 768, height: 1024 },
    desktop: { width: 1280, height: 800 },
  };
  const device = args.device as string | undefined;
  const preset = device ? DEVICE_PRESETS[device] : undefined;
  const w = (args.width as number) ?? preset?.width;
  const h = (args.height as number) ?? preset?.height;
  const viewport = w && h ? { width: w, height: h } : undefined;

  await sm.create(role, { storageState, baseUrl, viewport });

  const vpNote = viewport ? ` ${viewport.width}×${viewport.height}` : '';
  const authNote = storageState ? ` (auth: ${storageState})` : ' (no auth state)';
  return textResult(`Session '${role}' ready at ${baseUrl}${vpNote}${authNote}`);
}

function handleList(sm: SessionManager): Promise<ToolResult> {
  const sessions = sm.list();
  if (sessions.length === 0) {
    return Promise.resolve(textResult('No active sessions'));
  }
  return Promise.resolve(textResult(`Active sessions: ${sessions.join(', ')}`));
}

async function handleClose(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const role = args.role as string | undefined;
  await sm.destroy(role);
  if (role) {
    return textResult(`Session '${role}' closed`);
  }
  return textResult('All sessions closed');
}
