import * as React from 'react';

export interface GridColumnConfig {
    key: string;
    label: string;
    shrink?: boolean; // collapse column to content width
}

export interface GridConfig {
    columns: GridColumnConfig[];
    searchFields: string[];
    sortFields: string[];
    pageSize: number;
}

export type GlobalRenders = Record<string, (val: unknown) => React.ReactNode>;
