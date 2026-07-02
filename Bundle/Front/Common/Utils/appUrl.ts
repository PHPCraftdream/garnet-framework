/**
 * Generate a URL path with the right scope automatically applied.
 *
 * Default behaviour:
 *  - The path is checked against window.__GARNET_NO_PREFIX_PATHS__.
 *    If it matches a registered no-prefix path (e.g. /page) it is
 *    returned as-is — no system prefix attached.
 *  - Otherwise the system prefix is prepended.
 *
 * Override:
 *  - Pass {noPrefix: true} to force a clean URL even if the path isn't
 *    normally registered as no-prefix.
 *
 * Examples:
 *   appUrl('/bookings')                  → '/system/bookings'
 *   appUrl('/page/view~home')            → '/page/view~home'
 *   appUrl('/help', {noPrefix: true})    → '/help'
 */
export function appUrl(path: string, opts: {noPrefix?: boolean} = {}): string {
    const prefix: string = (window as any).__GARNET_PREFIX__ || '';
    if (!prefix) return path;
    if (opts.noPrefix) return path;
    if (isNoPrefixPath(path)) return path;
    if (path === '/') return prefix + '/';
    return prefix + path;
}

function isNoPrefixPath(path: string): boolean {
    const list: unknown = (window as any).__GARNET_NO_PREFIX_PATHS__;
    if (!Array.isArray(list) || list.length === 0) return false;
    const norm = '/' + path.replace(/^\/+|\/+$/g, '');
    for (const p of list as unknown[]) {
        if (typeof p !== 'string' || p === '' || p === '/') continue;
        if (norm === p || norm.startsWith(p + '/')) return true;
    }
    return false;
}
