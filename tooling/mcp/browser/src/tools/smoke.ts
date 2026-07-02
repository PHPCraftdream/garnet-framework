import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult, LogEntry } from '../types.ts';
import { textResult } from '../utils.ts';

export const tools: ToolDef[] = [
  {
    name: 'smoke',
    description:
      'Run a quick smoke test across one or more sessions. ' +
      'Navigates to each URL, checks for errors, and reports status.',
    inputSchema: {
      type: 'object',
      properties: {
        checks: {
          type: 'array',
          description: 'List of {session, url} pairs to check',
          items: {
            type: 'object',
            properties: {
              session: { type: 'string', description: 'Session role name' },
              url: { type: 'string', description: 'URL path to check' },
            },
            required: ['session', 'url'],
          },
        },
      },
      required: ['checks'],
    },
  },
];

interface SmokeCheck {
  session: string;
  url: string;
}

interface CheckResult {
  session: string;
  url: string;
  ok: boolean;
  status: number;
  time: number;
  errors: string[];
}

export async function handle(
  name: string,
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  if (name !== 'smoke') {
    return textResult(`Unknown tool: ${name}`, true);
  }

  try {
    return await handleSmoke(args, sm);
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

async function handleSmoke(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const checks = args.checks as SmokeCheck[];
  if (!checks || !Array.isArray(checks) || checks.length === 0) {
    return textResult('Missing or empty checks array', true);
  }

  const config = sm.getConfig();
  const results: CheckResult[] = [];

  // Group checks by session for reporting
  for (const check of checks) {
    const start = Date.now();
    let status = 0;
    const errors: string[] = [];

    try {
      if (!sm.hasSession(check.session)) {
        errors.push(`session '${check.session}' not found`);
        results.push({ session: check.session, url: check.url, ok: false, status: 0, time: 0, errors });
        continue;
      }

      const page = sm.getPage(check.session);
      const fullUrl = check.url.startsWith('http') ? check.url : config.baseUrl + check.url;

      // Clear log before navigation
      await page.evaluate(() => (window as any).__clearLog()).catch(() => {});

      const response = await page.goto(fullUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
      status = response?.status() ?? 0;

      await sm.waitForIdle(check.session);

      // Check for JS errors
      const jsErrors = await page.evaluate(() =>
        (window as any).__queryLog({ cat: 'js.*' }),
      ).catch(() => []) as LogEntry[];

      for (const err of jsErrors) {
        errors.push(`${err.cat}: "${err.msg}"`);
      }

      // Check for network errors
      const netErrors = await page.evaluate(() =>
        (window as any).__queryLog({ cat: 'net.fail' }),
      ).catch(() => []) as LogEntry[];

      for (const err of netErrors) {
        errors.push(`${err.cat}: "${err.msg}"`);
      }
    } catch (err) {
      errors.push(err instanceof Error ? err.message : String(err));
    }

    const time = Date.now() - start;
    const ok = status >= 200 && status < 400 && errors.length === 0;
    results.push({ session: check.session, url: check.url, ok, status, time, errors });
  }

  return textResult(formatSmokeReport(results));
}

function formatSmokeReport(results: CheckResult[]): string {
  // Group by session
  const bySession = new Map<string, CheckResult[]>();
  for (const r of results) {
    const list = bySession.get(r.session) ?? [];
    list.push(r);
    bySession.set(r.session, list);
  }

  const lines: string[] = [];

  for (const [session, checks] of bySession) {
    const allOk = checks.every((c) => c.ok);
    const mark = allOk ? 'OK' : 'FAIL';
    const details = checks
      .map((c) => {
        if (c.ok) {
          return `${c.url} OK (${c.status}, ${c.time}ms)`;
        }
        const errSummary = c.errors.length > 0
          ? ` (${c.errors.length} error(s): ${c.errors[0]})`
          : '';
        return `${c.url} ERROR${c.status ? ` (${c.status})` : ''}${errSummary}`;
      })
      .join(', ');

    lines.push(`${allOk ? '+' : '!'} ${session}: ${details}`);
  }

  return lines.join('\n');
}
