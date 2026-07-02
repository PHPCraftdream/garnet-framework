/**
 * Live navigation counters — a single shared poller for the whole page.
 *
 * The top menu, mobile menu and the floating message widget each render badges
 * (pending bookings, unread messages, unread support). Those numbers are baked
 * into the server HTML once; this module keeps them fresh by polling a small
 * JSON endpoint (~20s) and broadcasting the result on a window event. Every
 * consumer subscribes to the SAME singleton, so there's exactly one request in
 * flight regardless of how many islands listen.
 */

export interface LiveCounts {
    bookingsPending: number;
    unreadIm: number;
    unreadSupport: number;
}

const EVENT = 'garnet:counts';
const INTERVAL_MS = 20000;
// Cross-tab shared cache key: the first tab to poll in each window writes the
// result here; other tabs reuse it (and get a `storage` event) instead of
// hitting the server themselves. Collapses N tabs to ~1 request per window.
const LS_KEY = 'garnet:counts:shared';

let started = false;
let latest: LiveCounts | null = null;

const countsUrl = (): string | null => {
    const w = window as unknown as {__GARNET_COUNTS_URL__?: string; __GARNET_PREFIX__?: string};
    if (typeof w.__GARNET_COUNTS_URL__ === 'string' && w.__GARNET_COUNTS_URL__ !== '') {
        return w.__GARNET_COUNTS_URL__;
    }
    // Fallback: derive from the route prefix (MainController ~counts route).
    const prefix = typeof w.__GARNET_PREFIX__ === 'string' ? w.__GARNET_PREFIX__ : '';
    return prefix ? `${prefix}/~counts` : null;
};

const toCounts = (raw: unknown): LiveCounts | null => {
    if (!raw || typeof raw !== 'object') return null;
    const r = raw as Record<string, unknown>;
    return {
        bookingsPending: Number(r.bookingsPending) || 0,
        unreadIm: Number(r.unreadIm) || 0,
        unreadSupport: Number(r.unreadSupport) || 0,
    };
};

const emit = (counts: LiveCounts): void => {
    latest = counts;
    window.dispatchEvent(new CustomEvent<LiveCounts>(EVENT, {detail: counts}));
};

const readShared = (): {ts: number; counts: LiveCounts} | null => {
    try {
        const raw = localStorage.getItem(LS_KEY);
        if (!raw) return null;
        const obj = JSON.parse(raw) as {ts?: unknown; counts?: unknown};
        const counts = toCounts(obj?.counts);
        if (!counts || typeof obj.ts !== 'number') return null;
        return {ts: obj.ts, counts};
    } catch {
        return null;
    }
};

const writeShared = (counts: LiveCounts): void => {
    try {
        localStorage.setItem(LS_KEY, JSON.stringify({ts: Date.now(), counts}));
    } catch {
        // localStorage unavailable (private mode / quota) — degrade to per-tab.
    }
};

const poll = (force = false): void => {
    // Hidden/background tabs don't poll at all — they refresh on becoming visible.
    if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
        return;
    }

    // If another tab already polled within this window, reuse its result and
    // skip the network round-trip entirely.
    const shared = readShared();
    if (!force && shared && Date.now() - shared.ts < INTERVAL_MS - 1000) {
        emit(shared.counts);
        return;
    }

    const url = countsUrl();
    if (!url) return;
    fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
        .then(res => (res.ok ? res.json() : null))
        .then(json => {
            const counts = toCounts(json);
            if (!counts) return;
            writeShared(counts); // share with other tabs (fires their `storage` event)
            emit(counts);
        })
        .catch(() => {
            // Network blip — keep the last known values and try again next tick.
        });
};

/**
 * Start the shared poll loop. Idempotent: the first caller wins, the rest are
 * no-ops. Polls every INTERVAL_MS, plus once when the tab becomes visible again
 * (so a backgrounded tab shows fresh numbers the moment the user returns).
 */
export const startLiveCounts = (): void => {
    if (started) return;
    started = true;

    window.setInterval(() => poll(), INTERVAL_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') poll();
    });
    // Another tab refreshed the shared counts — update our badges without a
    // request of our own.
    window.addEventListener('storage', (e) => {
        if (e.key !== LS_KEY || !e.newValue) return;
        const shared = readShared();
        if (shared) emit(shared.counts);
    });
    // Slight delay so the first poll doesn't compete with the initial paint /
    // island hydration.
    window.setTimeout(() => poll(), 2000);
};

/**
 * Subscribe to live counter updates. Fires the callback on every successful
 * poll, and immediately with the last known value if one is already cached.
 * Returns an unsubscribe function.
 */
export const subscribeLiveCounts = (cb: (counts: LiveCounts) => void): (() => void) => {
    const handler = (e: Event): void => cb((e as CustomEvent<LiveCounts>).detail);
    window.addEventListener(EVENT, handler as EventListener);
    if (latest) cb(latest);
    return () => window.removeEventListener(EVENT, handler as EventListener);
};
