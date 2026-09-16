/**
 * Decides WHEN a link shows enough intent to be worth prefetching. Knows
 * nothing about what "prefetch" actually does — that's PrefetchCache (#382)
 * plus whatever wires it (#384). This layer only turns hover/focus/press
 * into two calls: `onIntent` (start it) and `onCancelIntent` (the user moved
 * on before it fired, or before a click consumed it).
 *
 * Delegated on a container, same pattern as ClassClick/HotClickInit — one
 * listener set, not one per link, so it stays cheap on pages with long lists.
 */

export interface IntentTriggerCallbacks {
    onIntent: (link: HTMLAnchorElement) => void;
    onCancelIntent: (link: HTMLAnchorElement) => void;
}

export interface IntentTriggerOptions {
    /** Debounce before a hover counts as intent, so a cursor passing through
     * a list doesn't fire one request per row. Keyboard focus and
     * pointerdown/touchstart skip this — they're already explicit. */
    hoverDelayMs?: number;
}

const DEFAULT_HOVER_DELAY_MS = 300;

/**
 * Same eligibility question HotClickInit asks for hot navigation, plus one
 * more: a link that opts OUT of hot navigation (`.no-hot`/[data-no-hot])
 * does a real page reload on click, so prefetching its document would never
 * be consumed — skip those too, instead of wasting the request.
 */
const isPrefetchEligible = (a: HTMLAnchorElement): boolean => {
    if (a.classList.contains('no-hot') || a.hasAttribute('data-no-hot')) {
        return false;
    }
    if (a.hasAttribute('data-no-prefetch')) {
        return false;
    }
    if (a.target && a.target !== '' && a.target !== '_self') {
        return false; // new tab/window — not what the current page will consume
    }
    if (a.hasAttribute('download') || /\bexternal\b/i.test(a.getAttribute('rel') || '')) {
        return false;
    }

    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || /^[a-z]+:/i.test(href) && !/^https?:/i.test(href)) {
        return false;
    }

    let url: URL;
    try {
        url = new URL(href, window.location.href);
    } catch {
        return false;
    }
    if (url.origin !== window.location.origin) {
        return false;
    }
    if (/\.[a-z0-9]{1,6}$/i.test(url.pathname)) {
        return false; // asset/file
    }

    return true;
};

/**
 * A user on a metered connection, or one who's told the browser they want
 * less data, doesn't want the app guessing extra requests on their behalf.
 * Checked live (not cached at init) since Save-Data can flip mid-session.
 */
const prefersNoPrefetch = (): boolean => {
    const connection = (navigator as unknown as {connection?: {saveData?: boolean}}).connection;
    if (connection?.saveData) return true;
    if (typeof window.matchMedia === 'function') {
        try {
            if (window.matchMedia('(prefers-reduced-data: reduce)').matches) return true;
        } catch {
            // matchMedia throwing on an unsupported media feature — treat as "no opinion".
        }
    }
    return false;
};

const closestEligibleLink = (target: EventTarget | null): HTMLAnchorElement | null => {
    const el = target as Element | null;
    if (!el || typeof el.closest !== 'function') return null;
    const a = el.closest('a[href]') as HTMLAnchorElement | null;
    return a && isPrefetchEligible(a) ? a : null;
};

/**
 * Initialise intent triggers within `container`. Returns a dispose function
 * — call it on unmount so timers don't fire after the DOM they touch is gone.
 */
export const initIntentTriggers = (
    container: HTMLElement,
    callbacks: IntentTriggerCallbacks,
    options: IntentTriggerOptions = {},
): (() => void) => {
    const hoverDelayMs = options.hoverDelayMs ?? DEFAULT_HOVER_DELAY_MS;
    const pendingHoverTimers = new Map<HTMLAnchorElement, ReturnType<typeof setTimeout>>();
    // Links intent has already fired for — mouseleave/blur on these calls
    // onCancelIntent; PrefetchCache.cancel() is itself a safe no-op for
    // anything already consumed by a click, so no extra bookkeeping needed
    // here about whether a click happened in between.
    const firedFor = new Set<HTMLAnchorElement>();

    const clearHoverTimer = (link: HTMLAnchorElement): void => {
        const timer = pendingHoverTimers.get(link);
        if (timer !== undefined) {
            clearTimeout(timer);
            pendingHoverTimers.delete(link);
        }
    };

    // Single gate for "has intent already fired for this link" — the hover
    // debounce timer and an explicit focus/press can both target the same
    // link; whichever gets here first wins, the other is a no-op. Without
    // this, focusing a link with a hover timer still pending on it would
    // fire onIntent twice once the timer catches up.
    const fireIntent = (link: HTMLAnchorElement): void => {
        if (firedFor.has(link) || prefersNoPrefetch()) return;
        firedFor.add(link);
        callbacks.onIntent(link);
    };

    const cancelIntent = (link: HTMLAnchorElement): void => {
        clearHoverTimer(link);
        if (firedFor.has(link)) {
            firedFor.delete(link);
            callbacks.onCancelIntent(link);
        }
    };

    const onMouseOver = (event: MouseEvent): void => {
        const link = closestEligibleLink(event.target);
        if (!link || pendingHoverTimers.has(link) || firedFor.has(link)) return;
        // Delegated hover-enter: bail if we're still inside the same link
        // (moving between its descendants re-fires mouseover/mouseout).
        const related = event.relatedTarget as Node | null;
        if (related && link.contains(related)) return;

        const timer = setTimeout(() => {
            pendingHoverTimers.delete(link);
            fireIntent(link);
        }, hoverDelayMs);
        pendingHoverTimers.set(link, timer);
    };

    const onMouseOut = (event: MouseEvent): void => {
        const link = closestEligibleLink(event.target);
        if (!link) return;
        const related = event.relatedTarget as Node | null;
        if (related && link.contains(related)) return;
        cancelIntent(link);
    };

    const onFocusIn = (event: FocusEvent): void => {
        const link = closestEligibleLink(event.target);
        if (!link || firedFor.has(link)) return;
        fireIntent(link); // keyboard intent is already explicit — no debounce
    };

    const onFocusOut = (event: FocusEvent): void => {
        const link = closestEligibleLink(event.target);
        if (!link) return;
        cancelIntent(link);
    };

    const onPressStart = (event: PointerEvent | TouchEvent): void => {
        if ('button' in event && event.button !== 0) return; // right/middle click — not a real intent to navigate
        const link = closestEligibleLink(event.target);
        if (!link || firedFor.has(link)) return;
        clearHoverTimer(link); // press supersedes any still-pending hover debounce
        fireIntent(link);
    };

    container.addEventListener('mouseover', onMouseOver);
    container.addEventListener('mouseout', onMouseOut);
    container.addEventListener('focusin', onFocusIn);
    container.addEventListener('focusout', onFocusOut);
    container.addEventListener('pointerdown', onPressStart);
    container.addEventListener('touchstart', onPressStart, {passive: true});

    return () => {
        container.removeEventListener('mouseover', onMouseOver);
        container.removeEventListener('mouseout', onMouseOut);
        container.removeEventListener('focusin', onFocusIn);
        container.removeEventListener('focusout', onFocusOut);
        container.removeEventListener('pointerdown', onPressStart);
        container.removeEventListener('touchstart', onPressStart);
        for (const timer of pendingHoverTimers.values()) {
            clearTimeout(timer);
        }
        pendingHoverTimers.clear();
        firedFor.clear();
    };
};
