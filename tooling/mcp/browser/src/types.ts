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

/**
 * What actually happened to a session's persisted auth state when its
 * session_close ran — reported back so the tool response can say the true
 * outcome instead of a blanket "done" regardless of what happened.
 */
export type AuthOutcome =
  | { kind: 'saved'; path: string }
  | { kind: 'discarded' }
  | { kind: 'skipped' } // AUTH_DIR not configured — nothing to persist to.
  | { kind: 'error'; message: string };

export interface DestroyOutcome {
  role: string;
  auth: AuthOutcome;
}

export interface EnvConfig {
  baseUrl: string;
  authDir: string;
  appDir: string;
  phpErrorLog: string;
  /** The data attribute name used for test IDs (default: "data-test-id"). Set via TESTID_ATTR env var. */
  testidAttr: string;
  /**
   * Namespaces persisted storageState files so concurrent MCP instances sharing
   * the same AUTH_DIR (e.g. multiple UAT persona agents, one server process each,
   * all launched from the same project-wide .mcp.json) never collide on the same
   * {role}.json. Defaults to the basename of the spawning process's cwd — for a
   * persistent-agent-tree persona that cwd is its own cell (.agents/uat/<id>/),
   * so this resolves to that persona's id with zero extra config. Override via
   * GARNET_MCP_PERSONA_ID when that default doesn't fit.
   */
  personaId: string;
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
