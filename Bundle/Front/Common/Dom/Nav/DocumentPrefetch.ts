/**
 * Public API for prefetching page documents ahead of a hot-click navigation
 * (#381's "class A" — see docs/design/2026-09-15-prefetch-contract.md in the
 * app repo). This is the ONLY piece application code and GoTo.ts should
 * touch; PrefetchCache/IntentTriggers are internals.
 *
 * SAFETY RULE, enforced here, not by caller discipline: this module only
 * ever calls getHtml() — a plain GET. There is no method parameter anywhere
 * in this file's public surface, so a mutation cannot be routed through it
 * even by mistake. Never wire `onIntent`/`prefetchDocument` to anything that
 * isn't a `<a href>` navigation.
 */
import {PrefetchCache} from '@common/Api/PrefetchCache';
import {getHtml} from '@common/Api/Get/getHtml';
import {initIntentTriggers} from '@common/Dom/Nav/IntentTriggers';

const documentCache = new PrefetchCache<string>();

// Same string GoTo.ts's caller (HotClickInit) already uses as the navigation
// target: the raw `href` attribute, not the resolved `.href` IDL property.
// Priming the cache under the resolved absolute URL while goTo() looks it up
// under the raw (often root-relative) attribute would silently miss on
// every single click — same value, different string.
const linkKey = (link: HTMLAnchorElement): string => link.getAttribute('href') || '';

export const prefetchDocument = (url: string): void => {
    documentCache.prefetch(url, (signal) => getHtml(url, signal));
};

/**
 * Click-time consumption. The caller (GoTo.ts) never needs to know whether
 * a prefetch actually happened:
 *   - never prefetched / TTL-expired → consume() has nothing cached, starts
 *     a fresh fetch itself (requirement 1: behaves exactly like a plain
 *     getHtml() call would).
 *   - prefetch still in flight → returns that same in-flight promise.
 *   - prefetch finished but with an error → NOT handed to the click as-is
 *     (requirement 2). The user didn't cause that background failure and
 *     seeing it instantly, with no chance to retry, is worse than the plain
 *     not-prefetched click they'd have gotten without hover/focus firing at
 *     all. One transparent retry, same call GoTo.ts would have made anyway.
 */
export const fetchDocumentPrefetched = (url: string): Promise<string> =>
    documentCache.consume(url, (signal) => getHtml(url, signal)).catch(() => getHtml(url));

/**
 * Wire hover/focus/press intent on every eligible link inside `container`
 * to prefetchDocument. Call once per container next to hotClickInit(container)
 * — same element, same lifetime.
 *
 * onCancelIntent is deliberately a no-op: an earlier version aborted +
 * dropped the in-flight fetch the moment the pointer left the link, so
 * re-hovering the same link (a normal back-and-forth over a menu, not a
 * changed mind) started a genuinely new request every time — the TTL never
 * got a chance to do its job. Leaving a started prefetch alone once it's
 * running means PrefetchCache's own freshEntry() check (in-flight, or
 * settled within ttlMs) is what dedupes repeat hovers, not cancellation.
 */
export const initDocumentPrefetchTriggers = (container: HTMLElement): (() => void) =>
    initIntentTriggers(container, {
        onIntent: (link) => {
            const key = linkKey(link);
            if (key) prefetchDocument(key);
        },
        onCancelIntent: () => {},
    });
