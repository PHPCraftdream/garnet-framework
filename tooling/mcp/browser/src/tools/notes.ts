import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult, formatTimestamp } from '../utils.ts';

/**
 * In-memory note accumulator for test cases, bugs, TODOs.
 *
 * During interactive debugging, the agent saves observations as notes.
 * At the end of a session, `notes_list` returns all accumulated notes
 * that can be converted into proper test files.
 *
 * Notes are tagged with category (test, bug, todo, observation)
 * and optionally linked to a session/URL for context.
 */

interface Note {
  id: number;
  t: number;
  category: string;
  text: string;
  session?: string;
  url?: string;
  steps?: string[];
}

let notes: Note[] = [];
let nextId = 1;

// Counters for the reminder system
let actionsSinceLastNote = 0;
const REMINDER_THRESHOLD = 15;

/** Called by other tool modules to bump the action counter */
export function bumpActionCounter(): void {
  actionsSinceLastNote++;
}

/** Returns reminder text if it's time, otherwise empty string */
export function getReminder(): string {
  if (actionsSinceLastNote >= REMINDER_THRESHOLD && notes.length === 0) {
    return '\n[Reminder: No test cases noted yet. Use note_add to save important scenarios.]';
  }
  if (actionsSinceLastNote >= REMINDER_THRESHOLD * 2) {
    const last = notes.length > 0 ? notes[notes.length - 1] : null;
    const ago = last ? Math.round((Date.now() - last.t) / 60000) : 0;
    return `\n[Reminder: ${notes.length} notes saved, last ${ago}min ago. Consider noting new test cases.]`;
  }
  return '';
}

export const tools: ToolDef[] = [
  {
    name: 'note_add',
    description:
      'Save a test case, bug observation, or TODO for later. ' +
      'Notes accumulate during the session. Use notes_list to review all at the end. ' +
      'Categories: test (test scenario), bug (discovered bug), todo (follow-up work), observation.',
    inputSchema: {
      type: 'object',
      properties: {
        category: {
          type: 'string',
          enum: ['test', 'bug', 'todo', 'observation'],
          description: 'Note category',
        },
        text: {
          type: 'string',
          description: 'Description of the test case / bug / todo',
        },
        session: {
          type: 'string',
          description: 'Related session (optional, for context)',
        },
        steps: {
          type: 'array',
          items: { type: 'string' },
          description: 'Test steps or reproduction steps (optional)',
        },
      },
      required: ['category', 'text'],
    },
  },
  {
    name: 'notes_list',
    description:
      'List all accumulated notes (test cases, bugs, TODOs). ' +
      'Filter by category. Use at the end of a debugging session to generate test files.',
    inputSchema: {
      type: 'object',
      properties: {
        category: {
          type: 'string',
          enum: ['test', 'bug', 'todo', 'observation', 'all'],
          description: 'Filter by category (default: all)',
        },
        format: {
          type: 'string',
          enum: ['summary', 'playwright'],
          description: 'Output format: summary (default) or playwright (generates test code skeleton)',
        },
      },
    },
  },
  {
    name: 'notes_clear',
    description: 'Clear all accumulated notes. Use after exporting them.',
    inputSchema: {
      type: 'object',
      properties: {},
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
      case 'note_add':
        return handleNoteAdd(args, sm);
      case 'notes_list':
        return handleNotesList(args);
      case 'notes_clear':
        return handleNotesClear();
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

function handleNoteAdd(args: Record<string, unknown>, sm: SessionManager): ToolResult {
  const category = (args.category as string) || 'observation';
  const text = args.text as string;
  if (!text) return textResult('Missing required parameter: text', true);

  const session = args.session as string | undefined;
  const steps = args.steps as string[] | undefined;

  // Try to get current URL from session
  let url: string | undefined;
  if (session && sm.hasSession(session)) {
    try {
      url = sm.getPage(session).url();
    } catch { /* ignore */ }
  }

  const note: Note = {
    id: nextId++,
    t: Date.now(),
    category,
    text,
    session,
    url,
    steps,
  };

  notes.push(note);
  actionsSinceLastNote = 0;

  const icon = { test: 'T', bug: 'B', todo: 'D', observation: 'O' }[category] || '?';
  return textResult(
    `[${icon}${note.id}] ${text}` +
    (steps ? `\n  Steps: ${steps.length}` : '') +
    `\nTotal notes: ${notes.length} (${countByCategory()})`
  );
}

function handleNotesList(args: Record<string, unknown>): ToolResult {
  const category = (args.category as string) || 'all';
  const format = (args.format as string) || 'summary';

  const filtered = category === 'all'
    ? notes
    : notes.filter(n => n.category === category);

  if (filtered.length === 0) {
    return textResult(
      category === 'all'
        ? 'No notes yet. Use note_add to save test cases and observations during debugging.'
        : `No ${category} notes.`
    );
  }

  if (format === 'playwright') {
    return textResult(generatePlaywright(filtered));
  }

  // Summary format
  const lines = filtered.map(n => {
    const icon = { test: 'TEST', bug: 'BUG', todo: 'TODO', observation: 'OBS' }[n.category] || '?';
    const ts = formatTimestamp(n.t);
    const session = n.session ? ` [${n.session}]` : '';
    const url = n.url ? ` @ ${new URL(n.url).pathname}` : '';
    let line = `#${n.id} ${icon}${session}${url} — ${n.text}`;
    if (n.steps?.length) {
      line += '\n' + n.steps.map((s, i) => `  ${i + 1}. ${s}`).join('\n');
    }
    return line;
  });

  return textResult(
    `=== Notes (${filtered.length}) ===\n${countByCategory()}\n\n${lines.join('\n\n')}`
  );
}

function handleNotesClear(): ToolResult {
  const count = notes.length;
  notes = [];
  nextId = 1;
  actionsSinceLastNote = 0;
  return textResult(`Cleared ${count} notes`);
}

// ── Helpers ─────────────────────────────────────────────────────

function countByCategory(): string {
  const counts: Record<string, number> = {};
  for (const n of notes) {
    counts[n.category] = (counts[n.category] || 0) + 1;
  }
  return Object.entries(counts).map(([k, v]) => `${v} ${k}`).join(', ');
}

function generatePlaywright(filtered: Note[]): string {
  const testNotes = filtered.filter(n => n.category === 'test');
  const bugNotes = filtered.filter(n => n.category === 'bug');
  const otherNotes = filtered.filter(n => n.category !== 'test' && n.category !== 'bug');

  const lines: string[] = [];
  lines.push('// Auto-generated test skeletons from debugging session');
  lines.push('// Review and complete these before committing');
  lines.push('');
  lines.push("import { test, expect } from '@playwright/test';");
  lines.push('');

  for (const n of testNotes) {
    const name = n.text.replace(/'/g, "\\'");
    lines.push(`test('${name}', async ({ page }) => {`);
    if (n.steps?.length) {
      for (const step of n.steps) {
        lines.push(`  // ${step}`);
      }
    }
    if (n.url) {
      const path = new URL(n.url).pathname;
      lines.push(`  await page.goto('${path}');`);
    }
    lines.push('  // TODO: implement');
    lines.push('});');
    lines.push('');
  }

  for (const n of bugNotes) {
    const name = `regression: ${n.text}`.replace(/'/g, "\\'");
    lines.push(`test('${name}', async ({ page }) => {`);
    if (n.steps?.length) {
      for (const step of n.steps) {
        lines.push(`  // ${step}`);
      }
    }
    lines.push('  // TODO: verify fix');
    lines.push('});');
    lines.push('');
  }

  if (otherNotes.length > 0) {
    lines.push('/*');
    lines.push(' * Other observations:');
    for (const n of otherNotes) {
      lines.push(` * [${n.category.toUpperCase()}] ${n.text}`);
    }
    lines.push(' */');
  }

  return lines.join('\n');
}
