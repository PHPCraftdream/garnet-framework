import * as React from 'react';
import {createRoot} from 'react-dom/client';
import {DevLoginButtons} from '@framework/auth/DevLogin';
import {PageEvents} from '@common/Utils/PageEvents';
import {ECommonEvents} from '@common/Enums';

// Single persistent container — survives body.innerHTML replacement by re-attaching
let floatingDiv: HTMLDivElement | null = null;
let resizeObserver: ResizeObserver | null = null;

// floatingDiv is a transparent container; its visual content is a child
// `position: fixed` <div>, which means the container's own bbox is 0×0
// (fixed elements are out of flow). Read the child's box instead.
const measurePanelHeight = (): number => {
    const fixedChild = floatingDiv?.firstElementChild as HTMLElement | undefined;
    return fixedChild?.getBoundingClientRect().height ?? 0;
};

// Reserve room at the bottom of the document for the floating dev panel so
// it doesn't overlap the last row of long pages (grids, lists, tables). Uses
// ResizeObserver so wrapping the buttons to a second line on narrow viewports
// still keeps the reservation in sync.
const trackPanelHeight = (): void => {
    if (!floatingDiv || resizeObserver) return;
    const fixedChild = floatingDiv.firstElementChild as HTMLElement | null;
    if (!fixedChild) {
        // React hasn't painted the inner panel yet — retry next frame.
        requestAnimationFrame(trackPanelHeight);
        return;
    }
    resizeObserver = new ResizeObserver(() => {
        const h = measurePanelHeight();
        document.body.style.paddingBottom = `${Math.ceil(h)}px`;
    });
    resizeObserver.observe(fixedChild);
};

const applyBodyPadding = (): void => {
    const h = measurePanelHeight();
    if (h > 0) document.body.style.paddingBottom = `${Math.ceil(h)}px`;
};

const ensurePanel = (): void => {
    if (!floatingDiv) {
        floatingDiv = document.createElement('div');
        createRoot(floatingDiv).render(React.createElement(DevLoginButtons, {floating: true}));
    }
    // Re-attach whenever it's no longer in the document (body replacement, bfcache restore, etc.)
    if (!document.body.contains(floatingDiv)) {
        document.body.appendChild(floatingDiv);
    }
    trackPanelHeight();
    // After a body innerHTML replacement (SPA navigation) the inline padding
    // we set on body is wiped — re-apply it on every ensurePanel pass. The
    // ResizeObserver alone wouldn't fire here because the panel's own size
    // didn't change, only its parent.
    requestAnimationFrame(applyBodyPadding);
};

const init = (): void => {
    ensurePanel();
    // Re-attach after every SPA (hot-click) navigation — PageLoader replaces body.innerHTML
    PageEvents.init().subscribe(ECommonEvents.PAGE_RELOADED, ensurePanel);
    // Re-attach on popstate (back/forward navigation also replaces body)
    window.addEventListener('popstate', ensurePanel);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
