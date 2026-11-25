import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;
const tsToParts = dateUtilsShared.tsToParts;

/** Returns the tsToInputDate date utility result for its configured timezone. */
export function tsToInputDate(ts: number | string | null | undefined, opts?: { tz?: string }): string {
    const n = Number(ts);
    if (!n) return '';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    return `${p.year}-${p.month}-${p.day}`;
}
