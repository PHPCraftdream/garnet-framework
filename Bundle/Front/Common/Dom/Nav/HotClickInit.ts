import {classClick} from '@common/Dom/ClassClick';
import {goTo} from '@common/Dom/Nav/GoTo';

/**
 * Handler for a "hot" click (a navigation without a full page reload).
 *
 * @param event The mouse event.
 * @param element The clicked element.
 */
// The link whose navigation is currently in flight. A fresh click supersedes it:
// we strip its loading state right away (its request result is dropped centrally
// in goTo), so only the most recently clicked link ever shows the spinner/ring.
let activeLink: HTMLElement | null = null;

export const hotClickHandler = (event: MouseEvent, element: HTMLElement): void => {
    const href = element.getAttribute('href');

    // Supersede any previous in-flight navigation's visual state.
    if (activeLink && activeLink !== element) {
        activeLink.classList.remove('hot-clicked');
    }
    activeLink = element;

    element.classList.add('hot-clicked');
    // Immediately start dimming the current page (instant "loading" feedback).
    // PageLoader clears this once the new page is swapped in; on error we clear
    // it right here so the page snaps back to full opacity.
    document.body.classList.add('page-leaving');

    if (href) {
        event.preventDefault();
        event.stopPropagation();

        goTo(href).catch(() => {
            // Request failed — undo the loading state (goTo already surfaced an
            // error toast). No hard reload: keep the user where they are so they
            // can retry. (Superseded navigations resolve, not reject, so this
            // only fires for the latest click.)
            element.classList.remove('hot-clicked');
            document.body.classList.remove('page-leaving');
            if (activeLink === element) {
                activeLink = null;
            }
        });
    }
};

/**
 * Is this a same-origin link we may navigate without a full reload?
 * Bails out for everything that must keep native behaviour: new-tab / download
 * links, non-http schemes (mailto/tel/js), cross-origin URLs, in-page anchors,
 * asset/file URLs (have an extension), and explicit opt-outs (.no-hot /
 * [data-no-hot]). `.hot-click` links are handled by the classClick path above,
 * so they're skipped here too.
 */
const isEligibleInternalLink = (a: HTMLAnchorElement): boolean => {
    if (a.classList.contains('hot-click') || a.classList.contains('no-hot') || a.hasAttribute('data-no-hot')) {
        return false;
    }
    if (a.target && a.target !== '' && a.target !== '_self') {
        return false;
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
        return false; // external
    }
    if (/\.[a-z0-9]{1,6}$/i.test(url.pathname)) {
        return false; // asset/file (has an extension) — let the browser fetch it
    }
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
        return false; // in-page anchor
    }
    return true;
};

/**
 * Delegated handler that turns EVERY eligible internal link into a smooth hot
 * navigation — not just the ones tagged `.hot-click`. Runs in the bubble phase,
 * so any island/React `onClick` that called preventDefault (preview modals,
 * booking buttons, …) is honoured and left alone.
 */
const generalLinkHandler = (event: MouseEvent): void => {
    if (event.defaultPrevented) return;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const target = event.target as Element | null;
    const a = target && typeof target.closest === 'function'
        ? (target.closest('a[href]') as HTMLAnchorElement | null)
        : null;
    if (!a || !isEligibleInternalLink(a)) return;

    hotClickHandler(event, a);
};

/**
 * Initialises hot navigation (no full page reload) within a container: applies
 * to links with the hot-click class and to any other eligible internal link.
 *
 * @param container The container element.
 */
export const hotClickInit = (container: HTMLElement) => {
    classClick(container, 'hot-click', hotClickHandler);
    container.addEventListener('click', generalLinkHandler);
};
