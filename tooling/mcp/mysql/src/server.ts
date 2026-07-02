#!/usr/bin/env node

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import { execFile } from 'node:child_process';
import { resolve } from 'node:path';

// ── Config ──────────────────────────────────────────────────────────

const ROOT_DIR = process.env.GARNET_ROOT || resolve(import.meta.dirname, '..', '..');
const APP_DIR = process.env.GARNET_APP_DIR || resolve(ROOT_DIR, 'Apps', 'App');
const PHP_BIN = process.env.PHP_BIN || 'php';
const RUNNER = resolve(import.meta.dirname, '..', 'db-runner.php');

// ── PHP runner ──────────────────────────────────────────────────────

function runPhp(sql: string, params: unknown[] = []): Promise<Record<string, unknown>> {
  const input = JSON.stringify({ sql, params });

  return new Promise((resolve, reject) => {
    execFile(
      PHP_BIN,
      [RUNNER, input],
      {
        env: { ...process.env, GARNET_APP_DIR: APP_DIR },
        maxBuffer: 10 * 1024 * 1024, // 10MB
        timeout: 30000,
      },
      (error, stdout, stderr) => {
        if (stderr) {
          console.error('[garnet-mysql-mcp]', stderr);
        }
        try {
          const result = JSON.parse(stdout || '{}');
          if (result.error) {
            reject(new Error(result.error));
          } else {
            resolve(result);
          }
        } catch {
          reject(new Error(error?.message || `PHP returned invalid JSON: ${stdout}`));
        }
      },
    );
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
      'Uses the framework DB connection. Returns {affected, insertId}.',
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

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  const sql = (args?.sql as string) || '';
  const params = (args?.params as unknown[]) || [];

  if (!sql) {
    return { content: [{ type: 'text', text: 'Error: sql is required' }] };
  }

  // Safety: block dangerous operations
  const first = sql.trim().split(/\s+/)[0].toUpperCase();
  if (['DROP', 'TRUNCATE'].includes(first)) {
    return {
      content: [{ type: 'text', text: `Blocked: ${first} is not allowed via MCP` }],
    };
  }

  try {
    const result = await runPhp(sql, params);

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
