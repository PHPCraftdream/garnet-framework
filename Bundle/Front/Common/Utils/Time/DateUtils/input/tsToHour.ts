import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;
const tsToParts = dateUtilsShared.tsToParts;

/** Returns the tsToHour date utility result for its configured timezone. */
export function tsToHour(ts: number | string | null | undefined, opts?: { tz?: string }): number {
    const n = Number(ts);
    if (!n) return 0;
    const tz = opts?.tz ?? userTz() ?? browserTz;
    return Number(tsToParts(n, tz).hour);
}
