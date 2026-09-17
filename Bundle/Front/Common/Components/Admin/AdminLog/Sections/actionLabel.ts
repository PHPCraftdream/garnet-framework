import {I18nFramework} from '@framework/I18nGen/I18nFramework';

/**
 * Human-readable label for an admin action code.
 *
 * Maps known codes to localized strings using `AdminAction_<code>` i18n keys
 * (dots in the code are replaced with underscores). Falls back to the raw
 * code for unknown actions, so the column never shows an empty cell.
 */
export function actionLabel(code: string): string {
    if (!code) return '';

    const key = `AdminAction_${code.replace(/\./g, '_')}`;

    // I18nFramework extends I18nBase, which exposes a generic `t(key, args?)` lookup
    // returning the key itself when the entry is missing.
    const i18n = I18nFramework as unknown as {
        t: (k: string, args?: (string | number)[]) => string;
    } & Record<string, (args?: (string | number)[]) => string>;

    const direct = i18n.t(key);
    if (direct && direct !== key) return direct;

    // Secondary fallback: generated static getter (kept for safety in case `t` ever
    // signals "missing" differently than returning the key verbatim).
    const fn = i18n[key];
    if (typeof fn === 'function') {
        const v = fn();
        if (v && v !== key) return v;
    }

    return code;
}
