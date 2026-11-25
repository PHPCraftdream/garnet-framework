import {classClick} from '@common/Dom/El/ClassClick';
import {hotClickHandler} from './hotClickHandler';

/** Registers delegated click handlers for marked and eligible internal links. */
export const hotClickInit = (container: HTMLElement): void => {
    classClick(container, 'hot-click', hotClickHandler);
    container.addEventListener('click', markedLinkHandler);
    container.addEventListener('click', generalLinkHandler);
};

const isHotLink = (a: Element): boolean => a.hasAttribute('data-hot-click') || a.classList.contains('hot-click');

const isEligibleInternalLink = (a: HTMLAnchorElement): boolean => {
    if (isHotLink(a) || a.classList.contains('no-hot') || a.hasAttribute('data-no-hot')) return false;
    if (a.target && a.target !== '' && a.target !== '_self') return false;
    if (a.hasAttribute('download') || /\bexternal\b/i.test(a.getAttribute('rel') || '')) return false;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || /^[a-z]+:/i.test(href) && !/^https?:/i.test(href)) return false;
    let url: URL;
    try { url = new URL(href, window.location.href); } catch { return false; }
    if (url.origin !== window.location.origin) return false;
    if (/\.[a-z0-9]{1,6}$/i.test(url.pathname)) return false;
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;
    return true;
};

const generalLinkHandler = (event: MouseEvent): void => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
        || event.shiftKey || event.altKey) return;
    const target = event.target as Element | null;
    const a = target && typeof target.closest === 'function'
        ? target.closest('a[href]') as HTMLAnchorElement | null
        : null;
    if (a && isEligibleInternalLink(a)) hotClickHandler(event, a);
};

const markedLinkHandler = (event: MouseEvent): void => {
    const target = event.target as Element | null;
    const a = target && typeof target.closest === 'function'
        ? target.closest('a[data-hot-click]') as HTMLAnchorElement | null
        : null;
    if (a) hotClickHandler(event, a);
};
