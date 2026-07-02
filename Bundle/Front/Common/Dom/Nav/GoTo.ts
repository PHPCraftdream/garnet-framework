import {getHtml} from '@common/Api/getHtml';
import {PageLoader} from '@common/Dom/PageLoader';
import {RespError} from '@common/Api/RespError';
import {showToast} from '@common/Components/GlobalToast';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

// Monotonic navigation token. Every goTo() captures the current value up-front;
// if a newer navigation starts before this one settles, this one is "superseded"
// and applies NOTHING — no pushState, no page swap, no error toast. So a slow or
// failing request from a previous click is silently dropped the moment the user
// clicks another link, and only the latest navigation takes effect. Centralised
// here, so it covers every entry point (hot-click links, popstate, …).
let navToken = 0;

// The build id the running page was served with. Compared against each fetched
// page so a deploy mid-session triggers a full reload instead of swapping a new
// page into a stale bundle (which would call code/i18n that no longer matches).
const currentBuild = (): string =>
    document.querySelector('meta[name="garnet-build"]')?.getAttribute('content') ?? '';

const buildFromHtml = (html: string): string =>
    html.match(/<meta\s+name="garnet-build"\s+content="([^"]*)"/i)?.[1] ?? '';

const isStaleBuild = (html: string): boolean => {
    const cur = currentBuild();
    const next = buildFromHtml(html);
    return cur !== '' && next !== '' && cur !== next;
};

export const goTo = (href: string): Promise<void> => {
    const token = ++navToken;
    const superseded = (): boolean => token !== navToken;

    return getHtml(href).then((html) => {
        if (superseded()) {
            return;
        }
        // A newer build is live — hard-navigate to pull matching assets.
        if (isStaleBuild(html)) {
            window.location.href = href;
            return;
        }
        window.history.pushState({}, "", href);

        return PageLoader.updatePage(html);
    }).catch((error: RespError) => {
        // A newer click is already in flight — ignore this one's outcome entirely.
        if (superseded()) {
            return;
        }
        if (typeof error.response === 'string') {
            if (isStaleBuild(error.response)) {
                window.location.href = href;
                return;
            }
            if (window.location.href !== href) {
                window.history.pushState({}, "", href);
            }

            PageLoader.updatePage(error.response);
            return;
        }

        // No server response — likely offline, DNS failure, CORS, or aborted.
        // The user clicked a link and nothing visibly happened; surface a
        // toast so they know to retry instead of clicking again silently.
        const isOffline = typeof navigator !== 'undefined' && navigator.onLine === false;
        const message = isOffline
            ? I18nFramework.Common_NavigationError_Offline()
            : I18nFramework.Common_NavigationError_Generic();
        showToast(message, 'danger');
        throw error;
    });
};
