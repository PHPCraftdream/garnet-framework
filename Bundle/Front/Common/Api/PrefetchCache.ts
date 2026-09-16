/**
 * Generic promise cache for "fetch on intent, consume on click".
 *
 * Agnostic to what's being fetched (HTML document, JSON endpoint) — the
 * caller supplies the fetcher. One instance per resource kind (e.g. one for
 * page documents, one for a given JSON endpoint family) so unrelated keys
 * don't compete for the same size budget.
 */

type Fetcher<T> = (signal: AbortSignal) => Promise<T>;

type Settled<T> =
    | {ok: true; value: T}
    | {ok: false; error: unknown};

interface CacheEntry<T> {
    promise: Promise<T>;
    controller: AbortController;
    /** null while in flight; set once the fetch resolves or rejects. */
    settledAt: number | null;
    /** true once a real caller (not just a hover) has taken this promise. */
    consumed: boolean;
    result: Settled<T> | null;
}

export interface PrefetchCacheOptions {
    /** How long a settled result stays usable after it lands. Default 60s. */
    ttlMs?: number;
    /** Max tracked keys before the oldest is evicted. Default 50. */
    maxEntries?: number;
}

const DEFAULT_TTL_MS = 60000;
const DEFAULT_MAX_ENTRIES = 50;

export class PrefetchCache<T = unknown> {
    private readonly entries = new Map<string, CacheEntry<T>>();
    private readonly ttlMs: number;
    private readonly maxEntries: number;

    constructor(options: PrefetchCacheOptions = {}) {
        this.ttlMs = options.ttlMs ?? DEFAULT_TTL_MS;
        this.maxEntries = options.maxEntries ?? DEFAULT_MAX_ENTRIES;
    }

    /** In flight is always "fresh" (that's the dedup) — TTL only judges settled results. */
    private isFresh(entry: CacheEntry<T>): boolean {
        if (entry.settledAt === null) return true;
        return Date.now() - entry.settledAt < this.ttlMs;
    }

    private evictOldestIfFull(): void {
        if (this.entries.size < this.maxEntries) return;
        const oldestKey = this.entries.keys().next().value;
        if (oldestKey === undefined) return;
        const oldest = this.entries.get(oldestKey);
        // A still-pending, not-yet-consumed entry being evicted for space is
        // never coming back for — abort its request too, not just the
        // bookkeeping. A consumed or already-settled one is left to finish;
        // dropping it from the map doesn't cancel work already handed to a caller.
        if (oldest && !oldest.consumed && oldest.settledAt === null) {
            oldest.controller.abort();
        }
        this.entries.delete(oldestKey);
    }

    private start(key: string, fetcher: Fetcher<T>): CacheEntry<T> {
        const controller = new AbortController();
        const entry: CacheEntry<T> = {
            promise: undefined as unknown as Promise<T>,
            controller,
            settledAt: null,
            consumed: false,
            result: null,
        };

        const promise = fetcher(controller.signal).then(
            (value) => {
                entry.result = {ok: true, value};
                entry.settledAt = Date.now();
                return value;
            },
            (error: unknown) => {
                entry.result = {ok: false, error};
                entry.settledAt = Date.now();
                throw error;
            },
        );
        // A pure prefetch (nobody ever called consume()) must not surface as
        // an unhandled rejection — this attaches a no-op handler directly to
        // the shared promise. A real consumer gets their own independent
        // .then()/.catch() on the same promise via consume()'s return value,
        // which still sees the real rejection: multiple handlers on one
        // promise each fire on their own, this doesn't swallow it for them.
        promise.catch(() => { /* swallowed for the prefetch-only path */ });

        entry.promise = promise;
        this.evictOldestIfFull();
        this.entries.set(key, entry);

        return entry;
    }

    private freshEntry(key: string): CacheEntry<T> | null {
        const existing = this.entries.get(key);
        if (existing && this.isFresh(existing)) return existing;
        if (existing) this.entries.delete(key);
        return null;
    }

    /**
     * Fire-and-forget: start (or reuse) a request for `key`. Call from a
     * hover/focus/mousedown handler — nothing here awaits the result.
     */
    prefetch(key: string, fetcher: Fetcher<T>): void {
        if (this.freshEntry(key)) return;
        this.start(key, fetcher);
    }

    /**
     * Consume: return the in-flight/fresh promise for `key` if one exists,
     * otherwise start a fresh request. Call from the actual click handler.
     * Marks the entry consumed so cancel()/eviction won't abort it out from
     * under the caller.
     */
    consume(key: string, fetcher: Fetcher<T>): Promise<T> {
        const fresh = this.freshEntry(key);
        const entry = fresh ?? this.start(key, fetcher);
        entry.consumed = true;
        return entry.promise;
    }

    /**
     * Cancel an in-flight, not-yet-consumed prefetch — e.g. the pointer left
     * the link before the click. No-op for anything already consumed or
     * already settled: consumed work is never interrupted, settled work has
     * nothing left to cancel.
     */
    cancel(key: string): void {
        const entry = this.entries.get(key);
        if (!entry || entry.consumed || entry.settledAt !== null) return;
        entry.controller.abort();
        this.entries.delete(key);
    }
}
