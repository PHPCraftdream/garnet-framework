import {IDataListItem} from '@common/Models';

export type TGridBoolStr = { bool: string };
export type TGridMap = { map: IDataListItem[] };
export type TGridSelect = { select: string[] };
export type TGridTimeZone = { time_zone: string[] };

export type TGridSelectField = TGridTimeZone | TGridSelect | TGridMap;

export type TCropInfo = { x: number, y: number, w: number, h: number };

export type TGridFieldType =
    'string'
    | 'unix_time'
    | 'bool'
    | 'image'
    | 'textarea'
    | 'photo'
    | TGridBoolStr
    | TGridSelectField;

export interface TGridFieldInfo {
    name: string;
    type: TGridFieldType;
    readOnly?: boolean;
    hidden?: boolean;
    cropInfo?: string;
    cropName?: string;
    uploadPath?: string;
    validation?: (string|unknown)[];
}

export type TValidationMapped = {name: string, args: string[]};
export type TValidationEventObj = {
    info: TValidationMapped,
    value: string|Blob|null|undefined,
    result: boolean|string,
    el: HTMLInputElement
};

export interface IFromFieldsInfo {
    fields: Record<string, TGridFieldInfo>;
    detailsFields: string[];
}

export interface IDetailsInfo extends IFromFieldsInfo {
    saveUrl: string | null;
    idColumn: string;
}

export interface IFromInfo {
    detailsInfo: IDetailsInfo;
    details: Record<string, unknown>;
    action?: string;
}

export interface IGridInfo extends IDetailsInfo {
    items: Record<string, unknown>[];
    gridFields: string[];
}

export interface IEditColumnInfo {
    column: string;
    columnId: string;
    labelEscaped: string;
    value: string | null | undefined | unknown;
    valEscaped: string;
    data: Record<string, unknown>,
    fieldInfo: TGridFieldInfo;
}

export interface IEditFieldControl {
    init?: () => void;
    control: string;
    label: string;
    columnId: string;
}

