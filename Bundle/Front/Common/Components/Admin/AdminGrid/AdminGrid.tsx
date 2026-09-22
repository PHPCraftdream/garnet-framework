import * as React from 'react';
import {useState, useMemo} from 'react';
import {usePagination, PageResponse} from '../../../hooks/data/usePagination';
import Pagination from '../../Layout/Paging/Pagination';
import {GlobalRenders, GridConfig} from './types';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

type RowRenders<T> = Partial<Record<string, (row: T) => React.ReactNode>>;

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

export function AdminGrid<T>({pageUrl, initialData, config, rowKey, renders = {}, globalRenders = {}, emptyMessage, expandRenderer, expandable, onRowClick, rowTestId, extraParams}: AdminGridProps<T>) {
    const [query,     setQuery]     = useState('');
    const [sortField, setSortField] = useState<string | null>(null);
    const [sortDir,   setSortDir]   = useState<SortDir>('asc');
    const [expandedKeys, setExpandedKeys] = useState<Set<string | number>>(new Set());

    // The server does the search/sort/paging now — this object is the
    // reactive trigger usePagination watches to know when to refetch from
    // page 1 (see its debounce effect).
    const params = useMemo(
        () => ({query, sortField, sortDir, ...extraParams}),
        [query, sortField, sortDir, extraParams],
    );

    const {items: paged, page: safePage, totalPages: pageCount, total, loading, goToPage, perPage: pageSize, setPerPage} =
        usePagination<T>({url: pageUrl, initialData: initialData ?? undefined, params});

    const handleSort = (key: string) => {
        if (!config.sortFields.includes(key)) return;
        if (sortField === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
        else { setSortField(key); setSortDir('asc'); }
    };

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setQuery(e.target.value);
    };

    const renderCell = (row: T, key: string): React.ReactNode => {
        if (renders[key]) return renders[key]!(row);
        const val = getField(row, key);
        if (key in globalRenders) return globalRenders[key](val);
        return val == null ? '—' : String(val);
    };

    const totalCols = config.columns.length + (expandRenderer ? 1 : 0);

    // Pagination row — rendered both above and below the table (by
    // <Pagination> itself, called twice) so users don't have to scroll to
    // the bottom of a long grid to switch pages or change the page size.
    const paginationRow = (
        <Pagination
            page={safePage}
            totalPages={pageCount}
            total={total}
            loading={loading}
            onPageChange={goToPage}
            pageSize={pageSize}
            onPageSizeChange={setPerPage}
        />
    );

    return (
        <>
            {config.searchFields.length > 0 && (
                <div className="mb-4">
                    <input
                        type="search"
                        data-test-id="admin-grid-search"
                        className="form-control"
                        style={{maxWidth: '360px'}}
                        placeholder={t.Grid_Search()}
                        value={query}
                        onChange={handleSearch}
                    />
                </div>
            )}

            {paginationRow && <div className="mb-3">{paginationRow}</div>}

            <div className="overflow-x-auto rounded-lg border border-default shadow-sm">
                <table className="admin-table">
                    <thead>
                        <tr>
                            {expandRenderer && <th className="w-px" />}
                            {config.columns.map(col => {
                                const sortable = config.sortFields.includes(col.key);
                                const active   = sortField === col.key;
                                return (
                                    <th
                                        key={col.key}
                                        data-test-id={sortable ? `sort-col-${col.key}` : undefined}
                                        className={`whitespace-nowrap${col.shrink ? ' w-px' : ''}${sortable ? ' cursor-pointer select-none hover:bg-theme-border transition-colors' : ''}`}
                                        onClick={sortable ? () => handleSort(col.key) : undefined}
                                    >
                                        {col.label}
                                        {sortable && (
                                            <span className={`ml-1 ${active ? 'text-accent' : 'text-muted'}`}>
                                                {active ? (sortDir === 'asc' ? '▲' : '▼') : '⇅'}
                                            </span>
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {paged.length === 0 ? (
                            <tr>
                                <td colSpan={totalCols} className="px-4 py-8 text-center text-muted text-base">
                                    {emptyMessage ?? t.Grid_NoData()}
                                </td>
                            </tr>
                        ) : (
                            paged.map(row => {
                                const key = rowKey(row);
                                const isExpanded  = expandedKeys.has(key);
                                const canExpand   = expandRenderer ? (expandable ? expandable(row) : true) : false;
                                const expandContent = (expandRenderer && isExpanded) ? expandRenderer(row, true) : null;
                                const toggleExpand = () => setExpandedKeys(prev => {
                                    const next = new Set(prev);
                                    if (next.has(key)) next.delete(key); else next.add(key);
                                    return next;
                                });
                                return (
                                    <React.Fragment key={key}>
                                        <tr
                                            data-test-id={rowTestId ? rowTestId(row) : `grid-row-${key}`}
                                            className={`bg-surface hover:bg-surface-hover transition-colors${(canExpand || onRowClick) ? ' cursor-pointer' : ''}`}
                                            onClick={canExpand ? toggleExpand : (onRowClick ? () => onRowClick(row) : undefined)}
                                        >
                                            {expandRenderer && (
                                                <td className="px-3 py-3 w-px text-center">
                                                    {canExpand && (
                                                        <i className={`bi ${isExpanded ? 'bi-chevron-down text-accent' : 'bi-chevron-right text-muted'}`} />
                                                    )}
                                                </td>
                                            )}
                                            {config.columns.map(col => (
                                                <td key={col.key} className={`px-4 py-3 text-on-surface${col.shrink ? ' w-px whitespace-nowrap' : ''}`}>
                                                    {renderCell(row, col.key)}
                                                </td>
                                            ))}
                                        </tr>
                                        {expandContent != null && (
                                            <tr>
                                                <td colSpan={totalCols} className="p-0">
                                                    {expandContent}
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {paginationRow && <div className="mt-4">{paginationRow}</div>}
        </>
    );
}
