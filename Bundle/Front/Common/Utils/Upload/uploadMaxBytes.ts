/** Returns the server-published upload size limit, or zero when unspecified. */
export function uploadMaxBytes(): number {
    const value = (window as unknown as {__GARNET_UPLOAD_MAX__?: number}).__GARNET_UPLOAD_MAX__;

    return typeof value === 'number' && value > 0 ? value : 0;
}
