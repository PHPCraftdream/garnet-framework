// Global JS error reporter — sends client-side errors to /js-error/~report.
// Dedupe + throttle: same fingerprint not re-sent within FLUSH_THROTTLE_MS.
// Backend additionally throttles same-hash updates within 5 sec window.

const ENDPOINT = '/js-error/~report';
const FLUSH_THROTTLE_MS = 1000;
const sentHashes = new Map<string, number>();

interface ErrorPayload {
    message: string;
    stack?: string;
    file?: string;
    line?: number;
    col?: number;
    url: string;
}

/** Returns the fingerprint used to deduplicate an error payload. */
function fingerprint(p: ErrorPayload): string {
    return `${p.message}|${p.file ?? ''}|${p.line ?? 0}`;
}

/** Sends the payload unless the same error was reported recently. */
async function send(payload: ErrorPayload): Promise<void> {
    const fp = fingerprint(payload);
    const now = Date.now();
    const last = sentHashes.get(fp) ?? 0;
    if (now - last < FLUSH_THROTTLE_MS) return;
    sentHashes.set(fp, now);
    try {
        await fetch(ENDPOINT, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(payload),
            keepalive: true,
        });
    } catch {
        /* swallow — never let logging failures cause loops */
    }
}

/**
 * Report a failure the code handled itself (so it never reaches
 * `window.onerror`) through the same throttled, deduped channel.
 *
 * Exists for the cases where swallowing is the right call for the user but
 * silence is not right for us: an island whose chunk never arrived, for one.
 * A generic `unhandledrejection` would say "Loading chunk 1052 failed" and
 * nothing about WHICH island stayed unmounted.
 */
export function reportHandledError(message: string, cause?: unknown): void {
    if (typeof window === 'undefined') return;

    const err = cause instanceof Error ? cause : undefined;
    void send({
        message: message.slice(0, 1024),
        stack: err?.stack ? err.stack.slice(0, 8192) : undefined,
        file: undefined,
        line: 0,
        col: 0,
        url: window.location.href,
    });
}

/** Installs global listeners for uncaught errors and promise rejections. */
