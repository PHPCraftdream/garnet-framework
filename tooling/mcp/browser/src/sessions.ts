import { chromium } from 'playwright';
import type { Browser, BrowserContext, Page } from 'playwright';
import { readFileSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import type { SessionState, PageSnapshot, TimelineEntry, LogEntry, EnvConfig, AuthOutcome, DestroyOutcome } from './types.ts';
import { diffStates, formatDiff } from './diff.ts';
import { formatTimestamp } from './utils.ts';
import { bumpActionCounter, getReminder } from './tools/notes.ts';

const __dirname = dirname(fileURLToPath(import.meta.url));
const COLLECTOR_PATH = resolve(__dirname, '..', 'collector', 'debug-collector.js');

const TIMELINE_MAX = 2000;
const NETWORK_IDLE_TIMEOUT = 5000;

export class SessionManager {
  private browser: Browser | null = null;
  private sessions = new Map<string, SessionState>();
  private _timeline: TimelineEntry[] = [];
  private collectorScript: string;
  private config: EnvConfig;

  constructor(config: EnvConfig) {
    this.config = config;
    this.collectorScript = readFileSync(COLLECTOR_PATH, 'utf-8');
  }

  /** Re-read collector script from disk (hot-reload after edits) */
  reloadCollector(): void {
    this.collectorScript = readFileSync(COLLECTOR_PATH, 'utf-8');
  }

  // ── Lifecycle ──────────────────────────────────────────────────────

  async init(): Promise<Browser> {
    if (!this.browser) {
      this.browser = await chromium.launch({ headless: true });
    }
    return this.browser;
  }

  async close(): Promise<void> {
    for (const [role] of this.sessions) {
      await this.destroy(role);
    }
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
    }
  }

  // ── Session management ─────────────────────────────────────────────

  async create(
    role: string,
    opts: { storageState?: string; baseUrl?: string; viewport?: { width: number; height: number } } = {},
  ): Promise<void> {
    // Chromium is launched lazily, on first use, rather than at server startup.
    const browser = await this.init();

    // Close existing session for this role if any
    if (this.sessions.has(role)) {
      await this.destroy(role);
    }

    // Hot-reload collector script from disk (picks up edits without server restart)
    this.reloadCollector();

    const contextOpts: Record<string, unknown> = {
      ignoreHTTPSErrors: true,
    };

    // Storage state for authentication
    if (opts.storageState) {
      contextOpts.storageState = opts.storageState;
    }

    // Base URL
    if (opts.baseUrl) {
      contextOpts.baseURL = opts.baseUrl;
    }

    // Viewport override (mobile/tablet/desktop testing)
    if (opts.viewport) {
      contextOpts.viewport = opts.viewport;
    }

    const context = await browser.newContext(contextOpts);

    // Define __name no-op — esbuild/tsx wraps functions with __name() which doesn't exist in browser context
    // This must be injected BEFORE any page.evaluate calls run
    await context.addInitScript({
      content: 'if(typeof __name==="undefined"){var __name=function(fn){return fn}}',
    });

    // Inject configuration for collector (testid attribute name)
    await context.addInitScript({
      content: `window.__GARNET_MCP_CONFIG__ = { testidAttr: ${JSON.stringify(this.config.testidAttr)} };`,
    });

    // Inject debug collector into every page
    await context.addInitScript({ content: this.collectorScript });

    const page = await context.newPage();

    // Forward console errors to timeline
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        this.pushTimeline({
          session: role,
          t: Date.now(),
          cat: 'js.error',
          src: 'error',
          msg: msg.text(),
        });
      }
    });

    // Forward page crashes to timeline
    page.on('pageerror', (err) => {
      this.pushTimeline({
        session: role,
        t: Date.now(),
        cat: 'js.error',
        src: 'error',
        msg: err.message,
      });
    });

    // Server-side network tracking (catches requests the in-page collector misses)
    page.on('response', (response) => {
      const req = response.request();
      const resourceType = req.resourceType();
      // Only track document/xhr/fetch — skip images, fonts, stylesheets, scripts
      if (resourceType !== 'document' && resourceType !== 'xhr' && resourceType !== 'fetch') return;

      const method = req.method();
      const url = new URL(req.url());
      const path = url.pathname + url.search;
      const status = response.status();
      const cat = status >= 200 && status < 400 ? 'net.ok' : 'net.fail';
      this.pushTimeline({
        session: role,
        t: Date.now(),
        cat,
        src: 'net',
        msg: `${method} ${path} → ${status}`,
      });
    });

    page.on('requestfailed', (request) => {
      const resourceType = request.resourceType();
      if (resourceType !== 'document' && resourceType !== 'xhr' && resourceType !== 'fetch') return;

      const method = request.method();
      const url = new URL(request.url());
      const path = url.pathname + url.search;
      const failure = request.failure()?.errorText || 'unknown';
      this.pushTimeline({
        session: role,
        t: Date.now(),
        cat: 'net.error',
        src: 'net',
        msg: `${method} ${path} → FAILED: ${failure}`,
      });
    });

    this.sessions.set(role, { context, page, prevState: null, baseUrl: opts.baseUrl });
  }

  /** Resolve the effective base URL for a session (override → global). */
  getBaseUrl(role: string): string {
    return this.sessions.get(role)?.baseUrl || this.config.baseUrl;
  }

  /**
   * Path a persisted storageState for `role` would live at, namespaced by
   * this process's personaId so concurrent MCP instances (e.g. separate UAT
   * persona agents sharing one project-wide AUTH_DIR) never collide on the
   * same file. Returns null when AUTH_DIR isn't configured (persistence
   * silently no-ops — same as today's load-only behaviour when unset).
   */
  private authPath(role: string): string | null {
    if (!this.config.authDir) return null;
    const safeRole = role.replace(/[^a-zA-Z0-9_-]/g, '_') || 'default';
    return join(this.config.authDir, this.config.personaId, `${safeRole}.json`);
  }

  /**
   * Snapshot `role`'s current cookies/localStorage to disk (default), or
   * delete any previously-saved snapshot (`discard: true`) — the explicit
   * "log out for real" path a session can only reach by choosing it, never
   * as a side effect of the MCP process itself exiting between turns.
   * A save/delete failure never blocks the session from closing — but unlike
   * the old fire-and-forget version, the caller finds out about it instead of
   * a tool response that claims success no matter what actually happened.
   */
  private async persistAuth(role: string, session: SessionState, discard: boolean): Promise<AuthOutcome> {
    const path = this.authPath(role);
    if (!path) return { kind: 'skipped' };

    if (discard) {
      try {
        unlinkSync(path);
        return { kind: 'discarded' };
      } catch (err) {
        // ENOENT (nothing to discard) is a normal outcome, not a failure.
        if ((err as NodeJS.ErrnoException).code === 'ENOENT') return { kind: 'discarded' };
        return { kind: 'error', message: err instanceof Error ? err.message : String(err) };
      }
    }

    try {
      const state = await session.context.storageState();
      mkdirSync(dirname(path), { recursive: true });
      writeFileSync(path, JSON.stringify(state));
      return { kind: 'saved', path };
    } catch (err) {
      return { kind: 'error', message: err instanceof Error ? err.message : String(err) };
    }
  }

  /**
   * Closes session(s) and reports, per role actually found and closed, what
   * happened to its auth state. An empty array back for an explicit `role`
   * means there was no such session — distinct from a successful close.
   */
  async destroy(role?: string, opts: { discardAuth?: boolean } = {}): Promise<DestroyOutcome[]> {
    const discard = opts.discardAuth ?? false;
    const results: DestroyOutcome[] = [];

    if (role) {
      const session = this.sessions.get(role);
      if (session) {
        const auth = await this.persistAuth(role, session, discard);
        await session.context.close().catch(() => {});
        this.sessions.delete(role);
        results.push({ role, auth });
      }
    } else {
      // Close all sessions
      for (const [name, session] of this.sessions) {
        const auth = await this.persistAuth(name, session, discard);
        await session.context.close().catch(() => {});
        this.sessions.delete(name);
        results.push({ role: name, auth });
      }
    }

    return results;
  }

  getPage(role: string): Page {
    const session = this.sessions.get(role);
    if (!session) {
      throw new Error(`No session '${role}'. Active sessions: ${this.list().join(', ') || '(none)'}`);
    }
    return session.page;
  }

  list(): string[] {
    return [...this.sessions.keys()];
  }

  hasSession(role: string): boolean {
    return this.sessions.has(role);
  }

  // ── State capture ──────────────────────────────────────────────────

  async captureState(role: string): Promise<PageSnapshot> {
    const page = this.getPage(role);
    try {
      const state = await page.evaluate(() => (window as any).__collectPageState());
      return state as PageSnapshot;
    } catch (err) {
      // Page might have navigated or crashed — return a minimal snapshot
      return {
        url: page.url(),
        title: '',
        mutations: 0,
        logSize: 0,
        testids: [],
        forms: [],
        toasts: [],
        recentErrors: [],
      };
    }
  }

  async captureAndDiff(role: string): Promise<{ diff: import('./types.js').DiffResult; formatted: string }> {
    const session = this.sessions.get(role);
    if (!session) {
      throw new Error(`No session '${role}'`);
    }

    // Capture network activity before snapshot (for networkSummary)
    let networkEntries: LogEntry[] = [];
    try {
      networkEntries = await session.page.evaluate(() =>
        (window as any).__queryLog({ cat: 'net.*', last: 20 }),
      ) as LogEntry[];
    } catch { /* ignore */ }

    const newState = await this.captureState(role);
    const diff = diffStates(session.prevState, newState);

    // Build network summary from recent entries since prev state
    if (networkEntries.length > 0) {
      const prevLogSize = session.prevState?.logSize ?? 0;
      // Filter to entries that are "new" (simple heuristic: more log entries than before)
      const recentNet = networkEntries.slice(-5);
      if (recentNet.length > 0) {
        diff.networkSummary = recentNet.map(e => e.msg).join('; ');
      }
    }

    // Merge browser log entries into cross-session timeline
    try {
      const recentLogs = await session.page.evaluate(() =>
        (window as any).__queryLog({ last: 10 }),
      ) as LogEntry[];
      for (const entry of recentLogs) {
        this.pushTimeline({
          session: role,
          t: entry.t,
          cat: entry.cat,
          src: entry.src,
          msg: entry.msg,
        });
      }
    } catch { /* ignore */ }

    session.prevState = newState;

    // Bump action counter for test-case reminder system
    bumpActionCounter();
    const reminder = getReminder();

    return { diff, formatted: formatDiff(diff) + reminder };
  }

  /**
   * Wait for network idle with a timeout. Does not throw on timeout.
   */
  async waitForIdle(role: string): Promise<void> {
    const page = this.getPage(role);
    try {
      await page.waitForLoadState('networkidle', { timeout: NETWORK_IDLE_TIMEOUT });
    } catch {
      // Timeout is acceptable — page may have long-polling or streaming
    }
  }

  // ── Timeline ───────────────────────────────────────────────────────

  get timeline(): TimelineEntry[] {
    return this._timeline;
  }

  pushTimeline(entry: TimelineEntry): void {
    if (this._timeline.length >= TIMELINE_MAX) {
      this._timeline.shift();
    }
    this._timeline.push(entry);
  }

  clearTimeline(): void {
    this._timeline.length = 0;
  }

  getConfig(): EnvConfig {
    return this.config;
  }

  /** Build CSS selector for test ID attribute. Uses configured TESTID_ATTR. */
  testidSelector(testid: string): string {
    return `[${this.config.testidAttr}="${testid}"]`;
  }

  /** Get the configured test ID attribute name */
  get testidAttr(): string {
    return this.config.testidAttr;
  }
}
