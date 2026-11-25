import * as React from 'react';
import {forwardRef} from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import type {PageResponse} from '@common/hooks/data/usePagination';
import {PaginationController} from '@common/hooks/data/PaginationController';
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

/** Handles getField. */
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

/** Handles createCellRenderer. */
function createCellRenderer<T>(renders: RowRenders<T>, globalRenders: GlobalRenders) {
    return (row: T, key: string): React.ReactNode => {
        if (renders[key]) return renders[key]!(row);
        const val = getField(row, key);
        if (key in globalRenders) return globalRenders[key](val);
        return val == null ? '—' : String(val);
    };
}

type NetworkState<T> = {snapshot: ReturnType<PaginationController<T>['snapshot']>};

class AdminGridNetwork<T> extends AntiHookComponent<NetworkProps<T>, NetworkState<T>> {
    private readonly pagination = new PaginationController<T>({
        url: this.props.pageUrl,
        initialData: this.props.initialData,
        params: this.createParams(),
    });
    public state: NetworkState<T> = {snapshot: this.pagination.snapshot()};
    private readonly handle: AdminGridHandle<T> = {setItems: this.pagination.setItems, refresh:
        this.pagination.refresh};
    private attachedRef: NetworkProps<T>['gridRef'] = null;
    private unsubscribe?: () => void;
    private readonly updateOnParentRender = true;
    private cellRenderer?: {
        renders: RowRenders<T>;
        globalRenders: GlobalRenders;
        render: ReturnType<typeof createCellRenderer<T>>;
    };

    /** Handles createParams. */
    private createParams() {
        const {query, sortField, sortDir, extraParams} = this.props;
        return {query, sortField, sortDir, ...extraParams};
    }

    /** Handles updateRef. */
    private updateRef(ref: NetworkProps<T>['gridRef']): void {
        if (this.attachedRef === ref) return;
        const previous = this.attachedRef;
        this.attachedRef = null;
        if (typeof previous === 'function') previous(null);
        else if (previous) previous.current = null;

        this.attachedRef = ref;
        if (typeof ref === 'function') ref(this.handle);
        else if (ref) ref.current = this.handle;
    }

    /** Handles shouldComponentUpdate. */
    public shouldComponentUpdate(nextProps: Readonly<NetworkProps<T>>, nextState: Readonly<NetworkState<T>>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    /** Handles syncSnapshot. */
    private readonly syncSnapshot = (): void => {
        const snapshot = this.pagination.snapshot();
        this.setState(previous => previous.snapshot === snapshot ? null : {snapshot});
    };

    /** Handles componentDidMount. */
    public componentDidMount(): void {
        super.componentDidMount();
        this.unsubscribe = this.pagination.subscribeSnapshot(this.syncSnapshot);
        this.pagination.start();
        this.syncSnapshot();
        this.updateRef(this.props.gridRef);
    }

    /** Handles componentDidUpdate. */
    public componentDidUpdate(previousProps: Readonly<NetworkProps<T>>): void {
        super.componentDidUpdate(previousProps);
        const {pageUrl, query, sortField, sortDir, extraParams, gridRef} = this.props;
        if (pageUrl !== previousProps.pageUrl) this.pagination.setUrl(pageUrl);
        if (query !== previousProps.query || sortField !== previousProps.sortField || sortDir !== previousProps.sortDir
            || extraParams !== previousProps.extraParams) {
            this.pagination.setParams(this.createParams());
        }
        this.updateRef(gridRef);
    }

    /** Handles componentWillUnmount. */
    public componentWillUnmount(): void {
        this.unsubscribe?.();
        this.unsubscribe = undefined;
        this.pagination.dispose();
        try {
            this.updateRef(null);
        } finally {
            super.componentWillUnmount();
        }
    }

    /** Handles renderTable. */
    private renderTable() {
        const {config, rowKey, renders = emptyRenders, globalRenders = emptyGlobalRenders, emptyMessage, expandRenderer,
            expandable, onRowClick, rowTestId, sortField, sortDir, onSort} = this.props;
        const {items: paged} = this.state.snapshot;
        if (this.cellRenderer?.renders !== renders || this.cellRenderer.globalRenders !== globalRenders) {
            this.cellRenderer = {renders, globalRenders, render: createCellRenderer(renders, globalRenders)};
        }
        const renderCell = this.cellRenderer.render;

        return (
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
        );
    }

    /** Handles render. */
    public render() {
        const {config, query, onSearch} = this.props;
        const {page: safePage, totalPages: pageCount, total, loading, perPage: pageSize} = this.state.snapshot;
        const {goToPage, setPerPage} = this.pagination;
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

                {this.renderTable()}

                <AdminGridPagination position="bottom" {...paginationProps} />
            </>
        );
    }
}

class AdminGridController<T> extends AntiHookComponent<ControllerProps<T>> {
    private readonly controls = new Carburetor<GridState>({query: '', sortField: null, sortDir: 'asc'});
    private committedSortField: string | null = null;

    /** Handles componentDidUpdate. */
    public componentDidUpdate(previousProps: Readonly<ControllerProps<T>>): void {
        super.componentDidUpdate(previousProps);
        this.committedSortField = this.controls.getData().sortField;
    }

    // Caller-owned config and renderers can change in place.
    private readonly updateOnParentRender = true;

    /** Handles shouldComponentUpdate. */
    public shouldComponentUpdate(nextProps: Readonly<ControllerProps<T>>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    /** Handles handleSearch. */
    private readonly handleSearch: React.ChangeEventHandler<HTMLInputElement> = (event) => {
        this.controls.setData({...this.controls.getData(), query: event.target.value});
    };

    /** Handles handleSort. */
    private readonly handleSort = (key: string): void => {
        if (!this.props.config.sortFields.includes(key)) return;
        const state = this.controls.getData();
        this.controls.setData(this.committedSortField === key
            ? {...state, sortDir: state.sortDir === 'asc' ? 'desc' : 'asc'}
            : {...state, sortField: key, sortDir: 'asc'});
    };

    /** Handles render. */
    public render() {
        const {query, sortField, sortDir} = this.useCarburetor(this.controls);
        return <AdminGridNetwork {...this.props} query={query} sortField={sortField} sortDir={sortDir}
            onSearch={this.handleSearch} onSort={this.handleSort} />;
    }
}

/** Handles AdminGridInner. */
function AdminGridInner<T>(props: AdminGridProps<T>, ref: React.ForwardedRef<AdminGridHandle<T>>) {
    return <AdminGridController {...props} gridRef={ref} />;
}

export const AdminGrid = forwardRef(AdminGridInner) as <T>(
    props: AdminGridProps<T> & {ref?: React.ForwardedRef<AdminGridHandle<T>>},
) => ReturnType<typeof AdminGridInner>;
