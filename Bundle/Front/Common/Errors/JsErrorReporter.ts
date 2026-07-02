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

function fingerprint(p: ErrorPayload): string {
    return `${p.message}|${p.file ?? ''}|${p.line ?? 0}`;
}

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

export function installJsErrorReporter(): void {
    if (typeof window === 'undefined') return;

    window.addEventListener('error', (event: ErrorEvent) => {
        void send({
            message: String(event.message ?? 'Unknown error').slice(0, 1024),
            stack: event.error instanceof Error && event.error.stack ? event.error.stack.slice(0, 8192) : undefined,
            file: event.filename || undefined,
            line: event.lineno || 0,
            col: event.colno || 0,
            url: window.location.href,
        });
    });

    window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
        const reason: unknown = event.reason;
        const msg = reason instanceof Error ? reason.message : String(reason ?? 'Unhandled rejection');
        const stack = reason instanceof Error ? reason.stack : undefined;
        void send({
            message: msg.slice(0, 1024),
            stack: stack ? stack.slice(0, 8192) : undefined,
            file: undefined,
            line: 0,
            col: 0,
            url: window.location.href,
        });
    });
}
