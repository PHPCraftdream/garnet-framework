const currentLanguage = (): string | undefined => {
    if (typeof window === 'undefined') return undefined;
    return (window as Window & {__GARNET_UI_LANG__?: string}).__GARNET_UI_LANG__;
};

/** Returns the locale used by date formatters. */
export function appLocale(): string {
    const lang = currentLanguage() || 'RU';
    return lang === 'RU' ? 'ru-RU' : 'en-US';
}
