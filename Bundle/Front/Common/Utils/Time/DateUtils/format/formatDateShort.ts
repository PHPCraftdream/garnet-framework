import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const appLocale = dateUtilsShared.appLocale;
const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;

/** Returns the formatDateShort date utility result for its configured timezone. */
export function formatDateShort(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        day: '2-digit',
        month: '2-digit',
    });
    return fmt.format(new Date(n * 1000));
}
