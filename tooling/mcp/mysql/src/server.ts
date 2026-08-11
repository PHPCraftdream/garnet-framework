#!/usr/bin/env node

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import { spawn } from 'node:child_process';
import { resolve } from 'node:path';

// ── Config ──────────────────────────────────────────────────────────

const ROOT_DIR = process.env.GARNET_ROOT || resolve(import.meta.dirname, '..', '..');
const APP_DIR = process.env.GARNET_APP_DIR || resolve(ROOT_DIR, 'Apps', 'App');
const PHP_BIN = process.env.PHP_BIN || 'php';
const RUNNER = resolve(import.meta.dirname, '..', 'db-runner.php');
const ALLOW_WRITES = process.env.GARNET_MCP_ALLOW_WRITES === '1';
const RUNNER_TIMEOUT_MS = 30000;

// ── PHP runner ──────────────────────────────────────────────────────

function runPhp(sql: string, params: unknown[] = [], allowWrite = false): Promise<Record<string, unknown>> {
  const input = JSON.stringify({ sql, params, allowWrite });

  return new Promise((resolve, reject) => {
    const child = spawn(PHP_BIN, [RUNNER], {
      env: { ...process.env, GARNET_APP_DIR: APP_DIR },
    });

    let stdout = '';
    let stderr = '';
    let killedByTimeout = false;

    child.stdout.on('data', (chunk) => {
      stdout += chunk;
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk;
    });

    child.on('error', (err) => {
      reject(err);
    });

    child.on('close', (code, signal) => {
      if (stderr) {
        console.error('[garnet-mysql-mcp]', stderr);
      }
      if (killedByTimeout || signal) {
        reject(
          new Error(
            `Query timed out after ${RUNNER_TIMEOUT_MS / 1000}s; the statement may have completed on the server despite the timeout — verify manually before assuming success or failure.`,
          ),
        );
        return;
      }
      if (code !== 0 && !stdout) {
        reject(new Error(stderr || `PHP exited with code ${code}`));
        return;
      }
      try {
        const result = JSON.parse(stdout || '{}');
        if (result.error) {
          reject(new Error(result.error));
        } else {
          resolve(result);
        }
      } catch {
        reject(new Error(`PHP returned invalid JSON: ${stdout}`));
      }
    });

    const timer = setTimeout(() => {
      killedByTimeout = true;
      child.kill();
    }, RUNNER_TIMEOUT_MS);
    child.on('close', () => clearTimeout(timer));

    child.stdin.write(input);
    child.stdin.end();
  });
}

// ── Tools ───────────────────────────────────────────────────────────

const tools = [
  {
    name: 'query',
    description:
      'Execute a SELECT/SHOW/DESCRIBE query against the application database. ' +
      'Uses the framework DB connection (db.ini config, prefix, charset). ' +
      'Returns rows as JSON array. Table names include the framework prefix (e.g. db_ir_bookings).',
    inputSchema: {
      type: 'object' as const,
      properties: {
        sql: {
          type: 'string',
          description: 'SQL SELECT query to execute',
        },
        params: {
          type: 'array',
          items: {},
          description: 'Positional parameters for prepared statement (optional)',
        },
      },
      required: ['sql'],
    },
  },
  {
    name: 'exec',
    description:
      'Execute an INSERT/UPDATE/DELETE/ALTER query against the application database. ' +
      'Uses the framework DB connection. Returns {affected, insertId}. ' +
      'Disabled unless the MCP server process was started with GARNET_MCP_ALLOW_WRITES=1, ' +
      'and refused if the DB config does not resolve to a local host.',
    inputSchema: {
      type: 'object' as const,
      properties: {
        sql: {
          type: 'string',
          description: 'SQL statement to execute',
        },
        params: {
          type: 'array',
          items: {},
          description: 'Positional parameters for prepared statement (optional)',
        },
      },
      required: ['sql'],
    },
  },
];

// ── MCP Server ──────────────────────────────────────────────────────

const server = new Server(
  { name: 'garnet-mysql-mcp', version: '1.0.0' },
  { capabilities: { tools: {} } },
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools,
}));

const READ_ONLY_PREFIXES = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];

function formatResult(result: Record<string, unknown>) {
  if ('rows' in result) {
    const rows = result.rows as Record<string, unknown>[];
    const count = rows.length;
    // Compact output for large results
    const text =
      count === 0
        ? '(empty result set)'
        : count <= 50
          ? JSON.stringify(rows, null, 2)
          : JSON.stringify(rows.slice(0, 50), null, 2) + `\n... and ${count - 50} more rows`;
    return { content: [{ type: 'text', text: `${count} row(s):\n${text}` }] };
  }

  return {
    content: [
      {
        type: 'text',
        text: `OK. Affected: ${result.affected ?? 0}, insertId: ${result.insertId ?? 0}`,
      },
    ],
  };
}

async function handleQuery(sql: string, params: unknown[]) {
  const first = sql.trim().split(/\s+/)[0].toUpperCase();
  if (!READ_ONLY_PREFIXES.includes(first)) {
    return {
      content: [
        {
          type: 'text' as const,
          text: `Blocked: 'query' only allows ${READ_ONLY_PREFIXES.join('/')}. Use 'exec' for mutations.`,
        },
      ],
    };
  }

  const result = await runPhp(sql, params, false);
  return formatResult(result);
}

async function handleExec(sql: string, params: unknown[]) {
  if (!ALLOW_WRITES) {
    return {
      content: [
        {
          type: 'text' as const,
          text: "Blocked: 'exec' is disabled. Restart the MCP server with GARNET_MCP_ALLOW_WRITES=1 to enable mutating queries.",
        },
      ],
    };
  }

  const result = await runPhp(sql, params, true);
  return formatResult(result);
}

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  const sql = (args?.sql as string) || '';
  const params = (args?.params as unknown[]) || [];

  if (!sql) {
    return { content: [{ type: 'text', text: 'Error: sql is required' }] };
  }

  try {
    switch (name) {
      case 'query':
        return await handleQuery(sql, params);
      case 'exec':
        return await handleExec(sql, params);
      default:
        return { content: [{ type: 'text', text: `Error: unknown tool '${name}'` }] };
    }
  } catch (e) {
    return {
      content: [{ type: 'text', text: `Error: ${(e as Error).message}` }],
    };
  }
});

// ── Start ───────────────────────────────────────────────────────────

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('[garnet-mysql-mcp] MCP server started (stdio)');
}

main().catch((e) => {
  console.error('[garnet-mysql-mcp] Fatal:', e);
  process.exit(1);
});
