#!/usr/bin/env node

import { resolve } from 'node:path';
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

import { SessionManager } from './sessions.ts';
import { readToken } from './utils.ts';
import type { EnvConfig, ToolDef, ToolResult } from './types.ts';

import * as sessionTools from './tools/sessions.ts';
import * as navigationTools from './tools/navigation.ts';
import * as understandingTools from './tools/understanding.ts';
import * as loggingTools from './tools/logging.ts';
import * as smokeTools from './tools/smoke.ts';
import * as visualTools from './tools/visual.ts';
import * as backendTools from './tools/backend.ts';
import * as actionTools from './tools/actions.ts';
import * as noteTools from './tools/notes.ts';

// ── Tool registry ────────────────────────────────────────────────────

interface ToolModule {
  tools: ToolDef[];
  handle: (name: string, args: Record<string, unknown>, sm: SessionManager) => Promise<ToolResult>;
}

const modules: ToolModule[] = [
  sessionTools,
  navigationTools,
  understandingTools,
  loggingTools,
  smokeTools,
  visualTools,
  backendTools,
  actionTools,
  noteTools,
];

// Build lookup: tool name -> handler
const allTools: ToolDef[] = [];
const handlers = new Map<string, (args: Record<string, unknown>, sm: SessionManager) => Promise<ToolResult>>();

for (const mod of modules) {
  for (const tool of mod.tools) {
    allTools.push(tool);
    handlers.set(tool.name, (args, sm) => mod.handle(tool.name, args, sm));
  }
}

// ── Configuration ────────────────────────────────────────────────────

function loadConfig(): EnvConfig {
  const env = process.env;

  // Read debug token (required)
  let debugToken: string;
  try {
    debugToken = readToken();
  } catch {
    // Allow running without token for initial setup
    debugToken = env.GARNET_DEBUG_TOKEN || '';
    if (!debugToken) {
      console.error(
        'Warning: No debug token found. Create .garnet_debug_token or set GARNET_DEBUG_TOKEN env var.',
      );
    }
  }

  const rootDir = resolve(import.meta.dirname, '..', '..');
  return {
    baseUrl: env.BASE_URL || env.GARNET_BASE_URL || 'http://localhost',
    authDir: env.AUTH_DIR || env.GARNET_AUTH_DIR || '',
    appDir: env.GARNET_APP_DIR || resolve(rootDir, 'Apps', 'App'),
    phpErrorLog: env.PHP_ERROR_LOG || env.GARNET_PHP_ERROR_LOG || '',
    debugToken,
    testidAttr: env.TESTID_ATTR || 'data-test-id',
  };
}

// ── Main ─────────────────────────────────────────────────────────────

async function main(): Promise<void> {
  const config = loadConfig();
  const sessionManager = new SessionManager(config);

  // Initialize browser
  await sessionManager.init();

  // Create MCP server
  const server = new Server(
    { name: 'garnet-browser-mcp', version: '0.2.0' },
    {
      capabilities: {
        tools: {
          // MCP server-level instructions for AI agents
        },
      },
    },
  );

  // ── List tools ───────────────────────────────────────────────────

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: allTools.map((t) => ({
      name: t.name,
      description: t.description,
      inputSchema: t.inputSchema,
    })),
  }));

  // ── Call tool ────────────────────────────────────────────────────

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    const handler = handlers.get(name);

    if (!handler) {
      return {
        content: [{ type: 'text', text: `Unknown tool: ${name}` }],
        isError: true,
      };
    }

    try {
      return await handler((args as Record<string, unknown>) ?? {}, sessionManager);
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      return {
        content: [{ type: 'text', text: `Tool error: ${message}` }],
        isError: true,
      };
    }
  });

  // ── Graceful shutdown ────────────────────────────────────────────

  const cleanup = async () => {
    await sessionManager.close();
    process.exit(0);
  };

  process.on('SIGINT', cleanup);
  process.on('SIGTERM', cleanup);

  // ── Start transport ──────────────────────────────────────────────

  const transport = new StdioServerTransport();
  await server.connect(transport);

  // Log to stderr (stdout is reserved for MCP protocol)
  console.error('garnet-browser-mcp server started');
  console.error(`  Base URL: ${config.baseUrl}`);
  console.error(`  Auth dir: ${config.authDir || '(not set)'}`);
  console.error(`  App dir: ${config.appDir}`);
  console.error(`  Token: ${config.debugToken ? 'loaded' : 'NOT SET'}`);
  console.error(`  Tools: ${allTools.length}`);
}

main().catch((err) => {
  console.error('Fatal error:', err);
  process.exit(1);
});
