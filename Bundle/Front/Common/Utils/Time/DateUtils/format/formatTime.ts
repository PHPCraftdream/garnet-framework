import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const appLocale = dateUtilsShared.appLocale;
const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;

/** Returns the formatTime date utility result for its configured timezone. */
export function formatTime(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
    return fmt.format(new Date(n * 1000));
}
