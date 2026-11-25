import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;
const tsToParts = dateUtilsShared.tsToParts;

/** Returns the tsToDow date utility result for its configured timezone. */
export function tsToDow(ts: number | string | null | undefined, opts?: { tz?: string }): number {
    const n = Number(ts);
    if (!n) return 0;
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(n, tz);
    // Reconstruct the wall-clock as if it were UTC and read getUTCDay():
    const utc = Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day), 0, 0, 0);
    return new Date(utc).getUTCDay();
}
