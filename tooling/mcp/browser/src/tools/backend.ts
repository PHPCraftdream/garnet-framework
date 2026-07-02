import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { execFile } from 'node:child_process';
import type { SessionManager } from '../sessions.ts';
import type { ToolDef, ToolResult } from '../types.ts';
import { textResult } from '../utils.ts';

const PHP_BIN = process.env.PHP_BIN || 'php';
const RUNNER = resolve(import.meta.dirname, '..', '..', 'db-runner.php');

function runPhp(appDir: string, sql: string, params: unknown[]): Promise<{ rows?: Record<string, unknown>[]; affected?: number; insertId?: number; error?: string }> {
  const input = JSON.stringify({ sql, params });
  return new Promise((res, rej) => {
    execFile(PHP_BIN, [RUNNER, input], {
      env: { ...process.env, GARNET_APP_DIR: appDir },
      timeout: 30_000,
    }, (err, stdout, stderr) => {
      if (err && !stdout) return rej(new Error(stderr || err.message));
      try {
        res(JSON.parse(stdout));
      } catch {
        rej(new Error(`Invalid PHP output: ${stdout.slice(0, 200)}`));
      }
    });
  });
}

export const tools: ToolDef[] = [
  {
    name: 'db_query',
    description:
      'Execute a SELECT query against the application database. Returns rows as JSON.',
    inputSchema: {
      type: 'object',
      properties: {
        sql: {
          type: 'string',
          description: 'SQL SELECT query to execute',
        },
        params: {
          type: 'array',
          description: 'Query parameters for prepared statement (optional)',
          items: {},
        },
      },
      required: ['sql'],
    },
  },
  {
    name: 'db_exec',
    description:
      'Execute an INSERT, UPDATE, or DELETE query. Returns affected row count.',
    inputSchema: {
      type: 'object',
      properties: {
        sql: {
          type: 'string',
          description: 'SQL statement to execute',
        },
        params: {
          type: 'array',
          description: 'Query parameters for prepared statement (optional)',
          items: {},
        },
      },
      required: ['sql'],
    },
  },
  {
    name: 'php_errors',
    description:
      'Read recent PHP error log entries. Reads from PHP_ERROR_LOG env var path.',
    inputSchema: {
      type: 'object',
      properties: {
        last: {
          type: 'number',
          description: 'Number of recent lines to return (default 20)',
        },
        search: {
          type: 'string',
          description: 'Filter lines containing this text (case-insensitive)',
        },
      },
    },
  },
  {
    name: 'evaluate',
    description:
      'Execute arbitrary JavaScript in the browser page context. Returns the result.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        js: {
          type: 'string',
          description: 'JavaScript code to evaluate in the page context',
        },
      },
      required: ['session', 'js'],
    },
  },
  {
    name: 'api_call',
    description:
      'Make an HTTP request to the app API using the session cookies/auth. ' +
      'Returns status, headers, and response body. Useful for inspecting API responses.',
    inputSchema: {
      type: 'object',
      properties: {
        session: { type: 'string', description: 'Session role name' },
        url: { type: 'string', description: 'URL path (appended to base URL)' },
        method: { type: 'string', description: 'HTTP method (default GET)' },
        body: { type: 'string', description: 'Request body as JSON string (for POST/PUT)' },
      },
      required: ['session', 'url'],
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
      case 'db_query':
        return await handleDbQuery(args, sm);
      case 'db_exec':
        return await handleDbExec(args, sm);
      case 'php_errors':
        return handlePhpErrors(args, sm);
      case 'evaluate':
        return await handleEvaluate(args, sm);
      case 'api_call':
        return await handleApiCall(args, sm);
      default:
        return textResult(`Unknown tool: ${name}`, true);
    }
  } catch (err) {
    return textResult(`Error: ${err instanceof Error ? err.message : String(err)}`, true);
  }
}

async function handleDbQuery(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const sql = args.sql as string;
  if (!sql) return textResult('Missing required parameter: sql', true);

  // Safety: only allow SELECT/SHOW/DESCRIBE/EXPLAIN
  const normalized = sql.trim().toUpperCase();
  if (!normalized.startsWith('SELECT') &&
      !normalized.startsWith('SHOW') &&
      !normalized.startsWith('DESCRIBE') &&
      !normalized.startsWith('EXPLAIN')) {
    return textResult('db_query only allows SELECT/SHOW/DESCRIBE/EXPLAIN. Use db_exec for mutations.', true);
  }

  const params = (args.params as (string | number | null)[]) ?? [];
  const result = await runPhp(sm.getConfig().appDir, sql, params);
  if (result.error) return textResult(`DB error: ${result.error}`, true);

  return textResult(JSON.stringify(result.rows ?? [], null, 2));
}

async function handleDbExec(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const sql = args.sql as string;
  if (!sql) return textResult('Missing required parameter: sql', true);

  const params = (args.params as (string | number | null)[]) ?? [];
  const result = await runPhp(sm.getConfig().appDir, sql, params);
  if (result.error) return textResult(`DB error: ${result.error}`, true);

  return textResult(
    JSON.stringify({
      affectedRows: result.affected ?? 0,
      insertId: result.insertId ?? 0,
    }),
  );
}

function handlePhpErrors(
  args: Record<string, unknown>,
  sm: SessionManager,
): ToolResult {
  const config = sm.getConfig();
  if (!config.phpErrorLog) {
    return textResult('PHP_ERROR_LOG env var not set', true);
  }

  const last = (args.last as number) ?? 20;
  const search = args.search as string | undefined;

  let logPath = config.phpErrorLog;

  // If it's a directory, find the most recently modified .log file
  if (existsSync(logPath) && statSync(logPath).isDirectory()) {
    const files = readdirSync(logPath)
      .filter((f) => f.endsWith('.log'))
      .map((f) => ({ name: f, mtime: statSync(join(logPath, f)).mtimeMs }))
      .sort((a, b) => b.mtime - a.mtime);

    if (files.length === 0) {
      return textResult(`No .log files found in ${logPath}`);
    }
    logPath = join(logPath, files[0].name);
  }

  if (!existsSync(logPath)) {
    return textResult(`Log file not found: ${logPath}`, true);
  }

  // Read file and get tail lines
  const content = readFileSync(logPath, 'utf-8');
  let lines = content.split('\n').filter((line) => line.trim());

  // Apply search filter
  if (search) {
    const lc = search.toLowerCase();
    lines = lines.filter((line) => line.toLowerCase().includes(lc));
  }

  // Take last N lines
  lines = lines.slice(-last);

  if (lines.length === 0) {
    return textResult('(no matching entries)');
  }

  return textResult(lines.join('\n'));
}

async function handleApiCall(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const url = args.url as string;
  if (!session || !url) return textResult('Missing required parameters: session, url', true);

  const method = ((args.method as string) || 'GET').toUpperCase();
  const body = args.body as string | undefined;
  const config = sm.getConfig();
  const fullUrl = url.startsWith('http') ? url : config.baseUrl + url;

  const page = sm.getPage(session);

  const result = await page.evaluate(
    async ({ url, method, body }: { url: string; method: string; body?: string }) => {
      const opts: RequestInit = { method, credentials: 'same-origin' };
      if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        opts.body = body;
        opts.headers = { 'Content-Type': 'application/json' };
      }

      const res = await fetch(url, opts);
      const ct = res.headers.get('content-type') || '';
      let responseBody: string;
      if (ct.indexOf('json') !== -1 || ct.indexOf('text') !== -1) {
        responseBody = await res.text();
        if (responseBody.length > 2000) {
          responseBody = responseBody.substring(0, 2000) + '... (truncated)';
        }
      } else {
        responseBody = `(binary: ${ct}, ${res.headers.get('content-length') || '?'} bytes)`;
      }

      return {
        status: res.status,
        statusText: res.statusText,
        contentType: ct,
        body: responseBody,
      };
    },
    { url: fullUrl, method, body },
  );

  const output = `${method} ${url} → ${result.status} ${result.statusText}\nContent-Type: ${result.contentType}\n\n${result.body}`;
  return textResult(output);
}

async function handleEvaluate(
  args: Record<string, unknown>,
  sm: SessionManager,
): Promise<ToolResult> {
  const session = args.session as string;
  const js = args.js as string;
  if (!session || !js) return textResult('Missing required parameters: session, js', true);

  const page = sm.getPage(session);

  // We use page.evaluate with a function constructor so the user can write
  // return statements or multi-line code
  const result = await page.evaluate((code: string) => {
    // eslint-disable-next-line no-new-func
    return new Function(code)();
  }, js);

  if (result === undefined) {
    return textResult('(undefined)');
  }

  const output = typeof result === 'string' ? result : JSON.stringify(result, null, 2);
  return textResult(output);
}
