import {IDataListItem, ITzInfo} from '@common/Support/Models';

/** Converts a timezone entry into a labelled data-list option. */
export function tzMapToListItem(zone: ITzInfo): IDataListItem {
    return {value: zone.zone, text: `${zone.offsetStr} ${zone.zone}`};
}
