// Single source of truth for displaying unix-timestamp values.
//
// Resolution order for the timezone:
//   1. Explicit opts.tz argument.
//   2. window.__GARNET_USER__?.timezone, set by the PHP base layout for
//      authenticated users (HtmlLayout::render -> __GARNET_USER__ inline
//      script). Pulled from the user's `time_zone` Account field.
//   3. Browser timezone via Intl.DateTimeFormat().resolvedOptions() — used
//      for unauthenticated visitors and as a final fallback.
//
// All time-bearing columns in the DB are stored as INT unix seconds; this
// helper is the only place in the frontend that converts those numbers to
// human-readable strings.

interface GarnetUser {
    accountId?: number;
    name?: string;
    timezone?: string;
}

const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;

function appLocale(): string {
    const lang = (typeof window !== 'undefined' && (window as Window & {__GARNET_UI_LANG__?: string}).__GARNET_UI_LANG__) || 'RU';
    return lang === 'RU' ? 'ru-RU' : 'en-US';
}

function userTz(): string | undefined {
    if (typeof window === 'undefined') return undefined;
    const user = (window as Window & {__GARNET_USER__?: GarnetUser}).__GARNET_USER__;
    const tz = user?.timezone;
    return tz && tz.length > 0 ? tz : undefined;
}

export interface FormatTsOptions {
    dateOnly?: boolean;
    tz?: string;
    weekday?: boolean;
}

export function formatTs(ts: number | string | null | undefined, opts?: FormatTsOptions): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        ...(opts?.weekday ? {weekday: 'short'} : {}),
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(opts?.dateOnly ? {} : {hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}),
    });
    return fmt.format(new Date(n * 1000));
}

/**
 * Time-only `HH:mm` formatter using the same TZ resolution as formatTs.
 * Use when you need just the hours and minutes (e.g. slot card start/end).
 */
export function formatTime(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    return fmt.format(new Date(n * 1000));
}

/**
 * Short date `dd.MM` formatter using the same TZ resolution.
 */
export function formatDateShort(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        day: '2-digit',
        month: '2-digit',
    });
    return fmt.format(new Date(n * 1000));
}

/**
 * Long-form date with weekday + month name (e.g. "Monday, 12 May 2026").
 * Uses TZ resolution chain identical to formatTs.
 */
export function formatDateLong(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    return fmt.format(new Date(n * 1000));
}

// ── Input-value helpers ─────────────────────────────────────────────────────
// Convert a unix-second timestamp into the parts an <input type="date|time|
// datetime-local"> expects, using the user's TZ (not the browser's). Pair
// with backend `DateTime::createFromFormat(..., new DateTimeZone($userTz))`
// for the inverse conversion — keeps round-trip stable across timezones.

interface TsParts {
    year: string;
    month: string;
    day: string;
    hour: string;
    minute: string;
}

function tsToParts(ts: number | string, tz: string): TsParts {
    const n = Number(ts);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date(n * 1000));
    const get = (type: string): string => parts.find(p => p.type === type)?.value ?? '00';
    return {
        year: get('year'),
        month: get('month'),
        day: get('day'),
        hour: get('hour') === '24' ? '00' : get('hour'),
        minute: get('minute'),
    };
}

/** Returns `YYYY-MM-DD` in the user's TZ — for `<input type="date">`. */
export function tsToInputDate(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    return `${p.year}-${p.month}-${p.day}`;
}

/** Returns `HH:mm` in the user's TZ — for `<input type="time">`. */
export function tsToInputTime(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    return `${p.hour}:${p.minute}`;
}

/** Returns `YYYY-MM-DDTHH:mm` in the user's TZ — for `<input type="datetime-local">`. */
export function tsToInputDateTime(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
}

/** Hour-of-day (0–23) of a timestamp in the user's TZ. */
export function tsToHour(ts: number | string | null | undefined, opts?: { tz?: string }): number {
    const n = Number(ts);
    if (!n) return 0;
    const tz = opts?.tz ?? userTz() ?? browserTz;
    return Number(tsToParts(n, tz).hour);
}

// ── Week-aware helpers (TZ-aware, Sunday-anchored) ──────────────────────────
// Week starts on Sunday — the day after Shabbat. See AGENTS.md §12.
//
// All week math goes through `weekStartTs` and `addDaysTs`, both of which
// resolve in the user's TZ via `tzWallToUnix`. Never roll your own
// `setDate(getDate() - getDay())` — that runs in the browser's TZ and can
// land the user on the wrong week boundary when their account TZ differs.

/**
 * Convert a wall-clock {Y-M-D h:m:s} in `tz` into the corresponding unix
 * timestamp (seconds). Handles DST without falling back to manual offsets.
 */
function tzWallToUnix(y: number, m: number, d: number, h: number, mi: number, s: number, tz: string): number {
    const utcGuess = Date.UTC(y, m - 1, d, h, mi, s);
    const wallParts = tsToParts(Math.floor(utcGuess / 1000), tz);
    const wallAsUtc = Date.UTC(
        Number(wallParts.year),
        Number(wallParts.month) - 1,
        Number(wallParts.day),
        Number(wallParts.hour),
        Number(wallParts.minute),
        0,
    );
    const offsetMs = wallAsUtc - utcGuess;
    return Math.floor((utcGuess - offsetMs) / 1000);
}

/** Day-of-week (0=Sunday … 6=Saturday) in the user's TZ. */
export function tsToDow(ts: number | string | null | undefined, opts?: { tz?: string }): number {
    const n = Number(ts);
    if (!n) return 0;
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    // Reconstruct the wall-clock as if it were UTC and read getUTCDay():
    const utc = Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day), 0, 0, 0);
    return new Date(utc).getUTCDay();
}

/**
 * Returns the unix-timestamp of the most recent Sunday 00:00 in the user's
 * TZ — i.e. the start of the calendar week (post-Shabbat) containing `ts`.
 */
export function weekStartTs(ts?: number, opts?: { tz?: string }): number {
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const n = ts ?? Math.floor(Date.now() / 1000);
    const p = tsToParts(n, tz);
    const dow = tsToDow(n, {tz});
    // Walk back `dow` calendar days using UTC math (so we don't accidentally
    // re-introduce browser-TZ semantics), then convert that wall-clock back
    // to unix via tzWallToUnix.
    const back = new Date(Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day)));
    back.setUTCDate(back.getUTCDate() - dow);
    return tzWallToUnix(back.getUTCFullYear(), back.getUTCMonth() + 1, back.getUTCDate(), 0, 0, 0, tz);
}

/**
 * Add `days` calendar days to `ts`, anchored to the wall-clock in the user's
 * TZ. Correctly handles DST transitions: the time-of-day in the user's TZ
 * stays put, so "Sunday 09:00 + 1 day" is "Monday 09:00" even when the unix
 * delta is 23h or 25h.
 */
export function addDaysTs(ts: number, days: number, opts?: { tz?: string }): number {
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(ts, tz);
    const d = new Date(Date.UTC(
        Number(p.year), Number(p.month) - 1, Number(p.day),
        Number(p.hour), Number(p.minute), 0,
    ));
    d.setUTCDate(d.getUTCDate() + days);
    return tzWallToUnix(
        d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate(),
        d.getUTCHours(), d.getUTCMinutes(), 0, tz,
    );
}

/**
 * Resolved user timezone — the same string `formatTs` would use to format.
 * Useful for the timezone-notice banner.
 */
export function resolvedUserTz(): string {
    return userTz() ?? browserTz;
}

/** Returns the browser's IANA TZ (always present, even without a user). */
export function resolvedBrowserTz(): string {
    return browserTz;
}

/** True when the user has an explicit TZ that differs from the browser's. */
export function userTzDiffersFromBrowser(): boolean {
    const u = userTz();
    return !!u && u !== browserTz;
}

/**
 * Offset (in minutes) from the browser's wall-clock to `tz`'s wall-clock
 * at unix-time `ts`. Positive when `tz` is ahead of the browser, negative
 * when behind. Handles DST since both sides go through Intl parts.
 */
export function tzOffsetMinutes(ts: number, tz: string): number {
    const a = tsToParts(ts, tz);
    const b = tsToParts(ts, browserTz);
    const wallA = Date.UTC(Number(a.year), Number(a.month) - 1, Number(a.day), Number(a.hour), Number(a.minute), 0);
    const wallB = Date.UTC(Number(b.year), Number(b.month) - 1, Number(b.day), Number(b.hour), Number(b.minute), 0);
    return Math.round((wallA - wallB) / 60000);
}
