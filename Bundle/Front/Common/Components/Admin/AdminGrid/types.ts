import * as React from 'react';

export interface GridColumnConfig {
    key: string;
    label: string;
    shrink?: boolean; // collapse column to content width
}

export interface SubGridConfig {
    buttonLabel: string;
    fetchUrl: string;
    urlParam: string;
    rowField: string;
}

export interface DetailViewConfig {
    buttonLabel: string;
    fetchUrl: string;
    urlParam: string;
    rowField: string;
}

export interface DetailSection {
    title: string;
    rows: unknown[];
    gridConfig: GridConfig;
}

export interface GridConfig {
    columns: GridColumnConfig[];
    searchFields: string[];
    sortFields: string[];
    pageSize: number;
    subGrids?: SubGridConfig[];
    detailViews?: DetailViewConfig[];
}

export type GlobalRenders = Record<string, (val: unknown) => React.ReactNode>;
