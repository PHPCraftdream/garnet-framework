import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const tsToParts = dateUtilsShared.tsToParts;
const browserTz = dateUtilsShared.browserTz;

/** Calculates the wall-clock offset between a timezone and the browser. */
export function tzOffsetMinutes(ts: number, tz: string): number {
    const a = tsToParts(ts, tz);
    const b = tsToParts(ts, browserTz);
    const wallA = Date.UTC(
        Number(a.year), Number(a.month) - 1, Number(a.day),
        Number(a.hour), Number(a.minute), 0,
    );
    const wallB = Date.UTC(
        Number(b.year), Number(b.month) - 1, Number(b.day),
        Number(b.hour), Number(b.minute), 0,
    );
    return Math.round((wallA - wallB) / 60000);
}
