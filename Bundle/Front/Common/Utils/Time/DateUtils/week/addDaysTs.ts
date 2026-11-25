import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;
const tsToParts = dateUtilsShared.tsToParts;
const tzWallToUnix = dateUtilsShared.tzWallToUnix;

/** Returns the addDaysTs date utility result for its configured timezone. */
export function addDaysTs(ts: number, days: number, opts?: { tz?: string }): number {
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const p = tsToParts(ts, tz);
    const d = new Date(Date.UTC(
        Number(p.year), Number(p.month) - 1, Number(p.day),
        Number(p.hour), Number(p.minute), 0,
    ));
    d.setUTCDate(d.getUTCDate() + days);
    return tzWallToUnix(
        d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate(),
        d.getUTCHours(), d.getUTCMinutes(), 0, tz,
    );
}
