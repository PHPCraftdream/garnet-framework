import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';
import {tsToDow} from '@common/Utils/Time/DateUtils/input/tsToDow';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;
const tsToParts = dateUtilsShared.tsToParts;
const tzWallToUnix = dateUtilsShared.tzWallToUnix;

/** Returns the weekStartTs date utility result for its configured timezone. */
export function weekStartTs(ts?: number, opts?: { tz?: string }): number {
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const n = ts ?? Math.floor(Date.now() / 1000);
    const p = tsToParts(n, tz);
    const dow = tsToDow(n, {tz});
    // Walk back `dow` calendar days using UTC math (so we don't accidentally
    // re-introduce browser-TZ semantics), then convert that wall-clock back
    // to unix via tzWallToUnix.
    const back = new Date(Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day)));
    back.setUTCDate(back.getUTCDate() - dow);
    return tzWallToUnix(back.getUTCFullYear(), back.getUTCMonth() + 1, back.getUTCDate(), 0, 0, 0, tz);
}
