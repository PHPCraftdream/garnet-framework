import { existsSync } from 'node:fs';
import { join } from 'node:path';
import type { SessionManager } from '../sessions.ts';
import type { AuthOutcome, DestroyOutcome, ToolDef, ToolResult } from '../types.ts';
import { textResult } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'session_create',
    description:
      'Create a new browser session for a role (e.g. student, teacher, admin). ' +
      'Auto-loads that role\'s saved auth state if one exists (written by a prior ' +
      'session_close in this same AUTH_DIR/personaId — see session_close), so being ' +
      'logged in survives across separate turns/process restarts without a fresh ' +
      'login each time. Each role gets its own isolated browser context. ' +
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
    description:
      'Close a browser session (or all sessions if no role specified). ' +
      'By default this SAVES the session\'s current auth state to disk first — the ' +
      'next session_create for the same role picks it up automatically, so simply ' +
      'ending a turn (or the whole MCP process exiting between turns) never throws ' +
      'away a login as a side effect. Pass discardAuth:true to actually clear the ' +
      'saved login (e.g. testing a logout flow, or switching this role to a ' +
      'different account) — that is the deliberate, explicit way to wipe it; ' +
      'nothing else does.',
    inputSchema: {
      type: 'object',
      properties: {
        role: {
          type: 'string',
          description: 'Session to close. If omitted, closes all sessions.',
        },
        discardAuth: {
          type: 'boolean',
          description:
            'true = delete this role\'s saved auth state instead of updating it ' +
            '(a deliberate logout). false/omitted = save current cookies for next ' +
            'time, same as today minus the surprise of losing them.',
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

  // Resolve storage state — namespaced by personaId so concurrent MCP
  // instances sharing one AUTH_DIR (e.g. separate UAT persona agents) never
  // read/write each other's {role}.json. Written by session_close (see there).
  let storageState = args.storageState as string | undefined;
  if (!storageState && config.authDir) {
    const safeRole = role.replace(/[^a-zA-Z0-9_-]/g, '_') || 'default';
    const candidate = join(config.authDir, config.personaId, `${safeRole}.json`);
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

function describeAuth(auth: AuthOutcome): string {
  switch (auth.kind) {
    case 'saved':
      return 'auth saved for next time';
    case 'discarded':
      return 'saved auth discarded';
    case 'skipped':
      return 'AUTH_DIR not configured — nothing persisted';
    case 'error':
      return `auth NOT saved — ${auth.message}`;
  }
}

async function handleClose(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const role = args.role as string | undefined;
  const discardAuth = (args.discardAuth as boolean) ?? false;
  const results: DestroyOutcome[] = await sm.destroy(role, { discardAuth });

  if (results.length === 0) {
    // Honest about the no-op: nothing was closed and nothing was persisted —
    // unlike the old unconditional "closed, auth saved" message that fired
    // regardless of whether a session actually existed. Not an error — just
    // not the "closed + saved" outcome the old message always claimed.
    return textResult(
      role ? `No such session '${role}' — nothing to close.` : 'No active sessions — nothing to close.',
    );
  }

  const anyError = results.some((r) => r.auth.kind === 'error');
  const lines = results.map((r) => `'${r.role}' closed (${describeAuth(r.auth)})`);
  const summary = role ? lines[0] : `${lines.length} session(s) closed:\n  ${lines.join('\n  ')}`;
  return textResult(summary, anyError);
}
