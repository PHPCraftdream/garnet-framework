import {IDataListItem, ITzInfo} from '@common/Support/Models';

const sortTimezoneEntries = (a: ITzInfo, b: ITzInfo): number =>
    Math.abs(a.offsetDiff) - Math.abs(b.offsetDiff) || a.zone.localeCompare(b.zone);

const browserOffset = new Date().getTimezoneOffset();

/** Calculates a timezone offset from its local formatted date and time. */
function tzOffset(timeZone: string): number {
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone, year: 'numeric', month: 'numeric', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23', hour12: false,
    });
    const match = formatter.format(new Date()).match(/(\d{1,2})\/(\d{1,2})\/(\d{1,4}),\s*(\d{1,2}):(\d{1,2})/);
    if (!match) return 0;
    const target = new Date();
    const hour = Number(match[4]) === 24 ? 0 : Number(match[4]);
    target.setFullYear(Number(match[3]), Number(match[1]) - 1, Number(match[2]));
    target.setHours(hour, Number(match[5]), 0, 0);
    return -Math.round((new Date().getTime() - target.getTime()) / 60000);
}

/** Formats an offset in signed hours and minutes. */
function formatOffset(offset: number): string {
    const hours = Math.floor(Math.abs(offset) / 60);
    const minutes = Math.abs(offset) % 60;
    const sign = offset < 0 ? '-' : '+';
    return `${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

const toTimezoneInfo = (zone: string): ITzInfo => {
    const offsetDiff = tzOffset(zone);
    return {zone, offsetDiff, offsetStr: formatOffset(offsetDiff - browserOffset)};
};

/** Sorts timezone entries by offset distance and then by zone name. */
const sortTzList = (tzList: readonly ITzInfo[]): ITzInfo[] => tzList.toSorted(sortTimezoneEntries);

/** Provides timezone-list helpers for combining, sorting, and formatting timezone entries. */
export const TzList = {
    /** Formats a timezone entry as a labelled data-list option. */
    tzMapToListItem(zone: ITzInfo): IDataListItem {
        return {value: zone.zone, text: `${zone.offsetStr} ${zone.zone}`};
    },

    /** Combines supported browser zones with backend zones and prioritizes the current zone. */
    mergeWithBackendTzList(zones: string[]): ITzInfo[] {
        const backend = new Set(zones);
        const current = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const available = Intl.supportedValuesOf('timeZone')
            .filter(zone => backend.has(zone) && zone !== current)
            .map(toTimezoneInfo);
        available.unshift(toTimezoneInfo(current));
        return sortTzList(available);
    },
};
