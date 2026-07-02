import isArray from 'lodash/isArray';
import isObject from 'lodash/isObject';
import {
    IGridInfo,
    TGridBoolStr,
    TGridFieldType,
    TGridMap,
    TGridSelect, TGridSelectField,
    TGridTimeZone
} from '@common/Dom/GridTable/Models';
import {TzList} from '@common/Utils/TzList';
import {IDataListItem} from '@common/Models';

export class GridUtils {
    static isGridData<T>(data: IGridInfo | unknown): data is IGridInfo {
        const d = data as IGridInfo;

        if (!d?.hasOwnProperty('items') || !d?.hasOwnProperty('fields') || !isArray(d?.items)) {
            return false;
        }

        if (!d?.hasOwnProperty('idColumn') || !d?.hasOwnProperty('gridFields') || !d?.hasOwnProperty('detailsFields')) {
            return false;
        }

        return isObject(d.fields);
    }

    static isBoolStr(typeInfo: TGridFieldType): typeInfo is TGridBoolStr {
        return typeof (typeInfo as TGridBoolStr)?.bool === 'string';
    }

    static isMap(typeInfo: TGridFieldType): typeInfo is TGridMap {
        return typeof (typeInfo as TGridMap)?.map === 'object';
    }

    static isSelect(typeInfo: TGridFieldType): typeInfo is TGridSelect {
        return typeof (typeInfo as TGridSelect)?.select === 'object';
    }

    static isTimeZone(typeInfo: TGridFieldType): typeInfo is TGridTimeZone {
        return typeof (typeInfo as TGridTimeZone)?.time_zone === 'object';
    }

    static getSelectArr(type: TGridSelectField): IDataListItem[] {
        if (GridUtils.isSelect(type)) {
            return type.select.map((el) => ({value: el, text: el}));
        } else if (GridUtils.isMap(type)) {
            return type.map;
        } else if (GridUtils.isTimeZone(type)) {
            return TzList.mergeWithBackendTzList(type.time_zone).map(TzList.tzMapToListItem);
        }
    }
}
