/**
 * Public URL of a static page, addressed by its readable slug.
 *
 * The `/page/view~` route scheme lives here in ONE place, so links can be
 * written against the slug alone (e.g. in i18n strings as `page:privacy`)
 * and resolved consistently — change the scheme here, not at every callsite.
 */
export const staticPageUrl = (slug: string): string => `/page/view~${slug}`;

