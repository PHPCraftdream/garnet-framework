import {IDataListItem, ITzInfo} from '@common/Models';

export const currentTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
const tzArea = currentTz.split('/')?.[0];
const offset = new Date().getTimezoneOffset();

export class TzList {
    static newTzObj = (zone: string): ITzInfo => {
        const offsetDiff = TzList.tzOffset(zone);

        return {zone, offsetDiff, offsetStr: TzList.formatOffset(offsetDiff - offset)};
    }

    static formatOffset = (offset: number): string => {
        const hours = Math.floor(Math.abs(offset) / 60);
        const minutes = Math.abs(offset) % 60;
        const sign = offset < 0 ? '-' : '+';

        const formattedHours = String(hours).padStart(2, '0');
        const formattedMinutes = String(minutes).padStart(2, '0');

        return `${sign}${formattedHours}:${formattedMinutes}`;
    }

    static tzOffset = (timeZone: string): number => {
        // noinspection TypeScriptValidateTypes
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: "numeric",
            month: "numeric",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hourCycle: "h23",
            hour12: false
        });

        const zoneTimeStr = formatter.format(new Date());
        const targetTimeZone = zoneTimeStr.match(/(\d{1,2})\/(\d{1,2})\/(\d{1,4}),\s*(\d{1,2}):(\d{1,2})/);

        if (!targetTimeZone) {
            return 0;
        }

        const current = new Date();
        const target = new Date();

        const year = Number(targetTimeZone[3]);
        const month = Number(targetTimeZone[1]);
        const day = Number(targetTimeZone[2]);

        const h = Number(targetTimeZone[4]);
        const hour = h === 24 ? 0 : h;

        const minutes = Number(targetTimeZone[5]);

        target.setFullYear(year, month - 1, day);
        target.setHours(hour);
        target.setMinutes(minutes);

        let result = 0.001 * (current.getTime() - target.getTime()) / 60;

        return -Math.round(result);
    };

    protected static offsetCompare = (a: ITzInfo, b: ITzInfo): number => {
        const absDiffA = Math.abs(a.offsetDiff);
        const absDiffB = Math.abs(b.offsetDiff);

        if (absDiffA < absDiffB) {
            return -1;
        }
        if (absDiffA > absDiffB) {
            return 1;
        }

        return 0;
    };

    protected static tzNameCompare = (a: ITzInfo, b: ITzInfo): number => {
        const aZone = a.zone;
        const bZone = b.zone;

        if (aZone < bZone) {
            return -1;
        }

        if (aZone > bZone) {
            return 1;
        }

        return 0;
    };

    protected static tzGroupCompare = (a: ITzInfo, b: ITzInfo): number => {
        const ra = a.zone.split('/')?.[0];
        const rb = b.zone.split('/')?.[0];

        if (ra === rb) {
            if (a.zone < b.zone) {
                return -1;
            }

            if (a.zone > b.zone) {
                return 1;
            }

            return 0;
        }

        if (ra === tzArea || rb === tzArea) {
            return -1;
        }

        return 1;
    };

    static sortTzList = (tzList: ITzInfo[]): ITzInfo[] => {
        const groups: Record<string, ITzInfo[]> = {};
        const result: ITzInfo[] = [];
        const order: string[] = [];

        tzList.sort(TzList.offsetCompare);

        for (const obj of tzList) {
            const key = obj.offsetDiff;
            if (!groups[key]) {
                order.push(key + '');
                groups[key] = [];
            }
            groups[key].push(obj);
        }

        for (const key of order) {
            const group = groups[key];
            groups[key] = group.toSorted(TzList.tzNameCompare);
        }

        if (groups['0']) {
            groups['0'].sort(TzList.tzGroupCompare);
        }

        for (const key of order) {
            const group = groups[key];
            result.push(...group);
        }

        return result;
    };

    static tzMapToListItem = (zone: ITzInfo): IDataListItem => {
        return {
            value: zone.zone,
            text: `${zone.offsetStr} ${zone.zone}`,
        };
    };

    static mergeWithBackendTzList = (tsList: string[]): ITzInfo[] => {
        const backendTimeZones: Record<string, true> = {};
        let resultZones: ITzInfo[] = [];

        for (let zone of tsList) {
            backendTimeZones[zone] = true;
        }

        const browserTimeZones = Intl.supportedValuesOf('timeZone');
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

        for (let zone of browserTimeZones) {
            if (backendTimeZones[zone] && tz !== zone) {
                resultZones.push(TzList.newTzObj(zone));
            }
        }

        resultZones = TzList.sortTzList(resultZones);
        resultZones.unshift(TzList.newTzObj(tz));

        return resultZones;
    }
}
