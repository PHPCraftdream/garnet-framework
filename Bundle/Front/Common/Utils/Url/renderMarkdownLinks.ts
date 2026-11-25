import {staticPageUrl} from '@common/Utils/Url/staticPageUrl';

/** Converts markdown links into navigable HTML links. */
const renderMarkdownLink = (_match: string, label: string, href: string): string => {
    const url = href.startsWith('page:') ? staticPageUrl(href.slice(5)) : href;
    return `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`;
};

/** Rewrites markdown link syntax while resolving static-page slugs to application routes. */
export const renderMarkdownLinks = (text: string): string =>
    text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, renderMarkdownLink);
