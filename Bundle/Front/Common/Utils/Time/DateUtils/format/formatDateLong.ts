import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const appLocale = dateUtilsShared.appLocale;
const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;

/** Returns the formatDateLong date utility result for its configured timezone. */
export function formatDateLong(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    return fmt.format(new Date(n * 1000));
}
