import {
    IGridInfo,
    TGridBoolStr,
    TGridFieldType,
    TGridMap,
    TGridSelect,
    TGridSelectField,
    TGridTimeZone,
} from '@common/Dom/GridTable/Models';
import {TzList} from '@common/Utils/Time/tz/tzList';
import {IDataListItem} from '@common/Support/Models';

/** Converts a string option into a list item. */
const selectValueToListItem = (el: string): IDataListItem => ({value: el, text: el});

/**
 * Namespace-compatible collection of grid helpers. Functions are exported individually
 * while retaining the existing `GridUtils.foo` access path.
 */
export namespace GridUtils {
    /** Checks whether an unknown value has the required grid configuration shape. */
    export function isGridData(data: IGridInfo | unknown): data is IGridInfo {
        const d = data as IGridInfo;

        if (!d?.hasOwnProperty('items') || !d?.hasOwnProperty('fields') || !Array.isArray(d?.items)) {
            return false;
        }

        if (
            !d?.hasOwnProperty('idColumn') ||
            !d?.hasOwnProperty('gridFields') ||
            !d?.hasOwnProperty('detailsFields')
        ) {
            return false;
        }

        return typeof d.fields === 'object' && d.fields !== null && !Array.isArray(d.fields);
    }

    /** Checks whether a field type describes a string-backed boolean. */
    export function isBoolStr(typeInfo: TGridFieldType): typeInfo is TGridBoolStr {
        return typeof (typeInfo as TGridBoolStr)?.bool === 'string';
    }

    /** Checks whether a field type describes a value map. */
    export function isMap(typeInfo: TGridFieldType): typeInfo is TGridMap {
        return typeof (typeInfo as TGridMap)?.map === 'object';
    }

    /** Checks whether a field type describes a select list. */
    export function isSelect(typeInfo: TGridFieldType): typeInfo is TGridSelect {
        return typeof (typeInfo as TGridSelect)?.select === 'object';
    }

    /** Checks whether a field type describes a time zone list. */
    export function isTimeZone(typeInfo: TGridFieldType): typeInfo is TGridTimeZone {
        return typeof (typeInfo as TGridTimeZone)?.time_zone === 'object';
    }

    /** Returns option items for select, map, or time zone field types. */
    export function getSelectArr(type: TGridSelectField): IDataListItem[] {
        if (isSelect(type)) {
            return type.select.map(selectValueToListItem);
        } else if (isMap(type)) {
            return type.map;
        } else if (isTimeZone(type)) {
            return TzList.mergeWithBackendTzList(type.time_zone).map(TzList.tzMapToListItem);
        }

        return [];
    }
}
