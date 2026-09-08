/**
 * The upload ceiling PHP actually enforces on this host, published by the
 * layout as `window.__GARNET_UPLOAD_MAX__`.
 *
 * Read from the server rather than written as a constant here. A number typed
 * into the client is a promise the server has not made: this project's
 * production host allows 2 MB while the code said 5, so a 3 MB photo was
 * dropped by the SAPI before a single check of ours ever ran — and the
 * resulting empty request read as "delete my photo".
 *
 * Zero means the server did not say, in which case the client does not guess:
 * callers skip the size check and let the server answer.
 */
export function uploadMaxBytes(): number {
    const value = (window as unknown as {__GARNET_UPLOAD_MAX__?: number}).__GARNET_UPLOAD_MAX__;

    return typeof value === 'number' && value > 0 ? value : 0;
}

/** Whole megabytes, rounded down — never promise more room than there is. */
export function megabytes(bytes: number): number {
    return Math.floor(bytes / (1024 * 1024));
}
