import * as React from 'react';
import {useMemo, useImperativeHandle, forwardRef} from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {usePagination, PageResponse} from '../../../hooks/data/usePagination';
import {AdminGridPagination} from './AdminGridPagination';
import {GlobalRenders, GridConfig} from './types';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {AdminGridSearch} from './AdminGridSearch';
import {AdminGridHeader} from './AdminGridHeader';
import {AdminGridTableShell} from './AdminGridTableShell';
import {AdminGridBody} from './AdminGridBody';

type RowRenders<T> = Partial<Record<string, (row: T) => React.ReactNode>>;

/**
 * Imperative handle for patching/refreshing a mounted grid from the parent —
 * e.g. after a row action (toggle a flag, adjust a balance) succeeds and the
 * caller wants to reflect the new value without jumping back to page 1 the
 * way a `params` change would.
 */
export interface AdminGridHandle<T> {
    /** Patch the currently-loaded page in place — does not refetch. */
    setItems: (updater: T[] | ((prev: T[]) => T[])) => void;
    /** Refetch the current page from the server (e.g. after a row was deleted server-side). */
    refresh: () => void;
}

export interface AdminGridProps<T> {
    /** POST endpoint returning a PageResponse<T> for {page, perPage, query, sortField, sortDir, ...extraParams}. */
    pageUrl: string;
    /** SSR first page, so the grid doesn't have to fetch on mount. */
    initialData: PageResponse<T> | null;
    config: GridConfig;
    rowKey: (row: T) => string | number;
    renders?: RowRenders<T>;
    globalRenders?: GlobalRenders;
    emptyMessage?: React.ReactNode;
    expandRenderer?: (row: T, isExpanded: boolean) => React.ReactNode;
    expandable?: (row: T) => boolean;
    onRowClick?: (row: T) => void;
    /** Override the default `grid-row-{key}` testid emitted on each <tr>. */
    rowTestId?: (row: T) => string;
    /**
     * Extra fixed/variable server params (e.g. an active tab filter).
     * MUST be a stable reference (useMemo/useState) — a fresh object literal
     * every render reads as "params changed" and refetches on every render.
     */
    extraParams?: Record<string, unknown>;
}

type SortDir = 'asc' | 'desc';

function getField(row: unknown, field: string): unknown {
    return (row as Record<string, unknown>)[field];
}

type GridState = {query: string; sortField: string | null; sortDir: SortDir};
type ControllerProps<T> = AdminGridProps<T> & {gridRef: React.ForwardedRef<AdminGridHandle<T>>};
type NetworkProps<T> = ControllerProps<T> & GridState & {
    onSearch: React.ChangeEventHandler<HTMLInputElement>;
    onSort: (key: string) => void;
};

const emptyRenders: Record<string, never> = {};
const emptyGlobalRenders: GlobalRenders = {};

function createCellRenderer<T>(renders: RowRenders<T>, globalRenders: GlobalRenders) {
    return (row: T, key: string): React.ReactNode => {
        if (renders[key]) return renders[key]!(row);
        const val = getField(row, key);
        if (key in globalRenders) return globalRenders[key](val);
        return val == null ? '—' : String(val);
    };
}

function AdminGridNetwork<T>({pageUrl, initialData, config, rowKey, renders = emptyRenders, globalRenders = emptyGlobalRenders, emptyMessage, expandRenderer, expandable, onRowClick, rowTestId, extraParams, gridRef, query, sortField, sortDir, onSearch, onSort}: NetworkProps<T>) {

    // The server does the search/sort/paging now — this object is the
    // reactive trigger usePagination watches to know when to refetch from
    // page 1 (see its debounce effect).
    const params = useMemo(
        () => ({query, sortField, sortDir, ...extraParams}),
        [query, sortField, sortDir, extraParams],
    );

    const {items: paged, setItems, page: safePage, totalPages: pageCount, total, loading, goToPage, perPage: pageSize, setPerPage, refresh} =
        usePagination<T>({url: pageUrl, initialData: initialData ?? undefined, params});

    useImperativeHandle(gridRef, () => ({setItems, refresh}), [setItems, refresh]);

    const renderCell = useMemo(() => createCellRenderer(renders, globalRenders), [renders, globalRenders]);

    const paginationProps = {
        page: safePage,
        totalPages: pageCount,
        total,
        loading,
        onPageChange: goToPage,
        pageSize,
        onPageSizeChange: setPerPage,
    };

    return (
        <>
            {config.searchFields.length > 0 && (
                <AdminGridSearch value={query} placeholder={t.Grid_Search()} onChange={onSearch} />
            )}

            <AdminGridPagination position="top" {...paginationProps} />

            <AdminGridTableShell>
                    <AdminGridHeader
                        columns={config.columns}
                        sortFields={config.sortFields}
                        sortField={sortField}
                        sortDir={sortDir}
                        hasExpander={!!expandRenderer}
                        onSort={onSort}
                    />
                    <AdminGridBody rows={paged} columns={config.columns} rowKey={rowKey} renderCell={renderCell}
                        emptyMessage={emptyMessage} expandRenderer={expandRenderer} expandable={expandable}
                        onRowClick={onRowClick} rowTestId={rowTestId} />
            </AdminGridTableShell>

            <AdminGridPagination position="bottom" {...paginationProps} />
        </>
    );
}

class AdminGridController<T> extends AntiHookComponent<ControllerProps<T>> {
    private readonly controls = new Carburetor<GridState>({query: '', sortField: null, sortDir: 'asc'});
    private committedSortField: string | null = null;

    public componentDidUpdate(previousProps: Readonly<ControllerProps<T>>): void {
        super.componentDidUpdate(previousProps);
        this.committedSortField = this.controls.getData().sortField;
    }

    // Caller-owned config and renderers can change in place.
    private readonly updateOnParentRender = true;

    public shouldComponentUpdate(nextProps: Readonly<ControllerProps<T>>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    private readonly handleSearch: React.ChangeEventHandler<HTMLInputElement> = (event) => {
        this.controls.setData({...this.controls.getData(), query: event.target.value});
    };

    private readonly handleSort = (key: string): void => {
        if (!this.props.config.sortFields.includes(key)) return;
        const state = this.controls.getData();
        this.controls.setData(this.committedSortField === key
            ? {...state, sortDir: state.sortDir === 'asc' ? 'desc' : 'asc'}
            : {...state, sortField: key, sortDir: 'asc'});
    };

    public render() {
        const {query, sortField, sortDir} = this.useCarburetor(this.controls);
        return <AdminGridNetwork {...this.props} query={query} sortField={sortField} sortDir={sortDir} onSearch={this.handleSearch} onSort={this.handleSort} />;
    }
}

function AdminGridInner<T>(props: AdminGridProps<T>, ref: React.ForwardedRef<AdminGridHandle<T>>) {
    return <AdminGridController {...props} gridRef={ref} />;
}

export const AdminGrid = forwardRef(AdminGridInner) as <T>(
    props: AdminGridProps<T> & {ref?: React.ForwardedRef<AdminGridHandle<T>>},
) => ReturnType<typeof AdminGridInner>;
