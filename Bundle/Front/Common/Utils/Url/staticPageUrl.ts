/**
 * Public URL of a static page, addressed by its readable slug.
 *
 * The `/page/view~` route scheme lives here in ONE place, so links can be
 * written against the slug alone (e.g. in i18n strings as `page:privacy`)
 * and resolved consistently — change the scheme here, not at every callsite.
 */
export const staticPageUrl = (slug: string): string => `/page/view~${slug}`;

const renderMarkdownLink = (_m: string, label: string, href: string): string => {
    const url = href.startsWith('page:') ? staticPageUrl(href.slice(5)) : href;
    return `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`;
};

/**
 * Convert markdown links `[label](href)` in a translated string to safe
 * anchor HTML. An `href` of the form `page:slug` is resolved through
 * staticPageUrl(); any other href is passed through unchanged.
 */
export const renderMarkdownLinks = (text: string): string =>
    text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, renderMarkdownLink);
