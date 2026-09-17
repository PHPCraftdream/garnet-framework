export interface IGarnetWindow {
    __GARNET_CSRF__?: string;
    __GARNET_BASE_URL__?: string;
    __GARNET_UPLOAD_DIR__?: string;
    __GARNET_UI_LANG__?: string;
    __GARNET_PAGE_EVENTS__?: IPageEvents;
    __GARNET_DOM_OBSERVER__?: IDomObserver;
}

export type TProgressHandler = (progress: number) => void;
export type TSelectImageHandler = (imageDate: string | null) => void;
export type TCropHandler = (data: Blob | null, cropData: ICropData) => void;
export type TClassClickHandler = (event: MouseEvent, element: HTMLElement) => void;
export type NodeHandler = (element: HTMLElement) => void;

export interface IDomObserver {
    defineRemoveHandler: (name: string, handler: NodeHandler) => void;
    defineAddClassHandler: (className: string, handler: NodeHandler) => void;
    registerElementRemoval: (element: HTMLElement, callback: () => void) => string;
}

export type TEventParams = null | Record<string, unknown>;

export interface IPageEvents {
    subscribe: (name: string, handler: FEventHandler) => string;
    unsubscribe: (id: string) => boolean;
    emmit: (name: string, params: TEventParams) => void;
}

export interface IEvent {
    name: string;
    params: TEventParams;
}

export type FEventHandler = (params: TEventParams) => void
export type IEventHandler = {
    id: string,
    handler: FEventHandler,
};

export interface ITzInfo {
    zone: string;
    offsetDiff: number;
    offsetStr: string;
}

export interface IDataListItem {
    value: string;
    text: string;
}

export interface ICropData {
    x: number;
    y: number;
    width: number;
    height: number
}

export type TFromValue = string | number | TFromNestedMap | TFromValue[];
export type TFormFileValue = Blob | File | TFormFileValue[];

export interface TFromNestedMap {
    [key: string]: TFromValue;
}

export interface TFromMap {
    [key: string]: TFromValue | TFormFileValue | null | undefined;
}

export type TFromBackendErrors = Record<string, string | string[]>;

export type TValidateCallback = null | ((value: string | Blob, el: HTMLInputElement) => string | boolean);
export type TOptionalCallback = null | (() => void);
export type TGetValueHandler = null | ((resultObj?: TFromMap) => TFromValue | TFormFileValue | null);
export type TFormErrors = { errors: TFromBackendErrors, commonErrors: string | string [] };

export type IApiSuccessResponse<T = Record<string, unknown>> = {
    ok?: boolean;
    data?: T;
} & T;

export interface IApiErrorResponse {
    ok?: boolean;
    message?: string;
    errors?: TFromBackendErrors;
    commonErrors?: string | string[];
}

export type IApiResponse<T = Record<string, unknown>> = IApiSuccessResponse<T> | IApiErrorResponse;
