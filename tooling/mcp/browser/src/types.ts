// ── Shared types for garnet-browser-mcp ──────────────────────────────

export interface PageSnapshot {
  url: string;
  title: string;
  mutations: number;
  logSize: number;
  testids: string[];
  forms: { id: string | null; fields: Record<string, string> }[];
  toasts: string[];
  recentErrors: LogEntry[];
}

export interface LogEntry {
  t: number;
  cat: string;
  src: string;
  msg: string;
  data?: string;
}

export interface DiffResult {
  url?: { from: string; to: string };
  title?: { from: string; to: string };
  addedTestids: string[];
  removedTestids: string[];
  addedForms: string[];
  removedForms: string[];
  changedFields: { form: string; field: string; from: string; to: string }[];
  newToasts: string[];
  newErrors: LogEntry[];
  mutations: number;
  networkSummary: string;
}

export interface TimelineEntry {
  session: string;
  t: number;
  cat: string;
  src: string;
  msg: string;
}

export interface SessionState {
  context: import('playwright').BrowserContext;
  page: import('playwright').Page;
  prevState: PageSnapshot | null;
  /** Per-session base URL override. Falls back to EnvConfig.baseUrl. */
  baseUrl?: string;
}

export interface EnvConfig {
  baseUrl: string;
  authDir: string;
  appDir: string;
  phpErrorLog: string;
  debugToken: string;
  /** The data attribute name used for test IDs (default: "data-test-id"). Set via TESTID_ATTR env var. */
  testidAttr: string;
}

/** MCP tool content block — text */
export interface TextContent {
  type: 'text';
  text: string;
}

/** MCP tool content block — image */
export interface ImageContent {
  type: 'image';
  data: string;
  mimeType: string;
}

export type ToolContent = TextContent | ImageContent;

export interface ToolResult {
  content: ToolContent[];
  isError?: boolean;
  [key: string]: unknown;
}

/** Tool definition for MCP ListTools */
export interface ToolDef {
  name: string;
  description: string;
  inputSchema: Record<string, unknown>;
}
