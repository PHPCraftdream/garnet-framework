import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult, LogEntry, TimelineEntry } from '../types.ts';
import { textResult, formatTimestamp, globToRegex } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'log_tail',
    description:
      'Tail-first: take last N entries, THEN filter. Use for "from the last 50 entries, show me errors". ' +
      'Contrast with log_filter which filters first, then tails.',
    inputSchema: {
      type: 'object',
      properties: {
        session: {
          type: 'string',
          description: 'Session role name. If omitted, reads from cross-session timeline.',
        },
        last: {
          type: 'number',
          description: 'Number of recent entries to take first (default 20)',
        },
        cat: {
          type: 'string',
          description: 'Category glob to filter AFTER taking tail (e.g. "net.*")',
        },
        src: {
          type: 'string',
          description: 'Source filter AFTER taking tail (e.g. "error")',
        },
        search: {
          type: 'string',
          description: 'Text search AFTER taking tail',
        },
      },
    },
  },
  {
    name: 'log_filter',
    description:
      'Filter log entries by category (glob), source, or text search. ' +
      'Categories: js.error, js.promise, net.ok, net.fail, net.error, nav.full, nav.spa, form.submit, perf.slow',
    inputSchema: {
      type: 'object',
      properties: {
        session: {
          type: 'string',
          description: 'Session role name. If omitted, searches cross-session timeline.',
        },
        cat: {
          type: 'string',
          description: 'Category glob pattern (e.g. "net.*", "js.error")',
        },
        src: {
          type: 'string',
          description: 'Source filter (exact match: "error", "net", "nav", "form")',
        },
        search: {
          type: 'string',
          description: 'Full-text search across category, message, and data',
        },
        last: {
          type: 'number',
          description: 'Return only last N matching entries',
        },
      },
    },
  },
  {
    name: 'log_clear',
    description: 'Clear log entries from a browser session and/or the server timeline.',
    inputSchema: {
      type: 'object',
      properties: {
        session: {
          type: 'string',
          description: 'Session to clear. If omitted, clears cross-session timeline.',
        },
      },
    },
  },
  {
    name: 'timeline',
    description:
      'Get the cross-session server-side timeline. Shows entries from all sessions interleaved chronologically.',
    inputSchema: {
      type: 'object',
      properties: {
        last: {
          type: 'number',
          description: 'Number of recent entries to return (default 10)',
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
  try {
    switch (name) {
      case 'log_tail':
        return await handleLogTail(args, sm);
      case 'log_filter':
        return await handleLogFilter(args, sm);
      case 'log_clear':
        return await handleLogClear(args, sm);
      case 'timeline':
        return handleTimeline(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

async function handleLogTail(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string | undefined;
  const last = (args.last as number) ?? 20;
  const cat = args.cat as string | undefined;
  const src = args.src as string | undefined;
  const search = args.search as string | undefined;

  if (session) {
    const page = sm.getPage(session);
    // Tail-first: take last N, then filter
    const entries = await page.evaluate(
      (params: { last: number; cat?: string; src?: string; search?: string }) =>
        (window as any).__tailLog(params.last, { cat: params.cat, src: params.src, search: params.search }),
      { last, cat, src, search },
    ) as LogEntry[];
    return textResult(formatLogEntries(entries));
  }

  // Cross-session timeline: tail-first
  let entries = sm.timeline.slice(-last);
  if (cat) {
    const re = globToRegex(cat);
    entries = entries.filter((e) => re.test(e.cat));
  }
  if (src) {
    entries = entries.filter((e) => e.src === src);
  }
  if (search) {
    const lc = search.toLowerCase();
    entries = entries.filter((e) => (e.cat + ' ' + e.msg).toLowerCase().includes(lc));
  }
  return textResult(formatTimelineEntries(entries));
}

async function handleLogFilter(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string | undefined;
  const cat = args.cat as string | undefined;
  const src = args.src as string | undefined;
  const search = args.search as string | undefined;
  const last = args.last as number | undefined;

  if (session) {
    const page = sm.getPage(session);
    const entries = await page.evaluate(
      (opts: { cat?: string; src?: string; search?: string; last?: number }) =>
        (window as any).__queryLog(opts),
      { cat, src, search, last },
    ) as LogEntry[];
    return textResult(formatLogEntries(entries));
  }

  // Filter cross-session timeline
  let entries = [...sm.timeline];

  if (cat) {
    const re = globToRegex(cat);
    entries = entries.filter((e) => re.test(e.cat));
  }
  if (search) {
    const lc = search.toLowerCase();
    entries = entries.filter((e) =>
      (e.cat + ' ' + e.msg).toLowerCase().includes(lc),
    );
  }
  if (last && last > 0) {
    entries = entries.slice(-last);
  }

  return textResult(formatTimelineEntries(entries));
}

async function handleLogClear(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string | undefined;

  if (session) {
    const page = sm.getPage(session);
    await page.evaluate(() => (window as any).__clearLog());
    return textResult(`Log cleared for session '${session}'`);
  }

  sm.clearTimeline();
  return textResult('Cross-session timeline cleared');
}

function handleTimeline(
  args: Record<string, unknown>,
  sm: SessionManager,
): ToolResult {
  const last = (args.last as number) ?? 10;
  const entries = sm.timeline.slice(-last);
  return textResult(formatTimelineEntries(entries));
}

// ── Formatting ─────────────────────────────────────────────────────

function formatLogEntries(entries: LogEntry[]): string {
  if (entries.length === 0) return '(no entries)';
  return entries
    .map((e) => {
      const ts = formatTimestamp(e.t);
      const data = e.data ? ` | ${e.data.slice(0, 500)}` : '';
      return `${ts} [${e.cat}] ${e.msg}${data}`;
    })
    .join('\n');
}

function formatTimelineEntries(entries: TimelineEntry[]): string {
  if (entries.length === 0) return '(no entries)';
  return entries
    .map((e) => {
      const ts = formatTimestamp(e.t);
      return `[${e.session}] ${ts} ${e.cat}: ${e.msg}`;
    })
    .join('\n');
}
