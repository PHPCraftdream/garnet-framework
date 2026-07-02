import * as React from 'react';
import {useState, useMemo} from 'react';
import {useBodyScrollLock} from '../../hooks/useBodyScrollLock';
import {usePageSize} from '../../hooks/usePageSize';
import {PageSizeSelector} from '../PageSizeSelector';
import {DetailSection, DetailViewConfig, GlobalRenders, GridConfig, SubGridConfig} from './types';
import {SubGridModal} from './SubGridModal';
import {DetailViewModal} from './DetailViewModal';
import {sendPost} from '@common/Api/sendPost';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

type RowRenders<T> = Partial<Record<string, (row: T) => React.ReactNode>>;

export interface AdminGridProps<T> {
    rows: T[];
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
}

interface SubModalState {
    open: boolean;
    loading: boolean;
    title: string;
    rows: unknown[];
    gridConfig: GridConfig | null;
}

interface DetailModalState {
    open: boolean;
    loading: boolean;
    title: string;
    sections: DetailSection[];
}

type SortDir = 'asc' | 'desc';

const CLOSED_SUB: SubModalState      = {open: false, loading: false, title: '', rows: [], gridConfig: null};
const CLOSED_DETAIL: DetailModalState = {open: false, loading: false, title: '', sections: []};

function getField(row: unknown, field: string): unknown {
    return (row as Record<string, unknown>)[field];
}

function compareValues(a: unknown, b: unknown): number {
    if (a == null && b == null) return 0;
    if (a == null) return 1;
    if (b == null) return -1;
    if (typeof a === 'number' && typeof b === 'number') return a - b;
    return String(a).localeCompare(String(b));
}

export function AdminGrid<T>({rows, config, rowKey, renders = {}, globalRenders = {}, emptyMessage, expandRenderer, expandable, onRowClick, rowTestId}: AdminGridProps<T>) {
    const [query,       setQuery]       = useState('');
    const [sortField,   setSortField]   = useState<string | null>(null);
    const [sortDir,     setSortDir]     = useState<SortDir>('asc');
    const [page,        setPage]        = useState(1);
    const [userPageSize, setUserPageSize] = usePageSize();
    const [subModal,    setSubModal]    = useState<SubModalState>(CLOSED_SUB);
    const [detModal,    setDetModal]    = useState<DetailModalState>(CLOSED_DETAIL);
    const [expandedKeys, setExpandedKeys] = useState<Set<string | number>>(new Set());

    useBodyScrollLock(subModal.open || detModal.open);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q || config.searchFields.length === 0) return rows;
        return rows.filter(row =>
            config.searchFields.some(f => {
                const val = getField(row, f);
                return val != null && String(val).toLowerCase().includes(q);
            })
        );
    }, [rows, query, config.searchFields]);

    const sorted = useMemo(() => {
        if (!sortField || !config.sortFields.includes(sortField)) return filtered;
        return [...filtered].toSorted((a, b) => {
            const cmp = compareValues(getField(a, sortField), getField(b, sortField));
            return sortDir === 'asc' ? cmp : -cmp;
        });
    }, [filtered, sortField, sortDir, config.sortFields]);

    // User-controlled page size (localStorage) beats the per-grid default
    // baked into config.pageSize. `config.pageSize <= 0` still means "show
    // everything", same legacy semantics as before.
    const pageSize  = config.pageSize > 0 ? userPageSize : sorted.length;
    const pageCount = Math.max(1, Math.ceil(sorted.length / pageSize));
    const safePage  = Math.min(page, pageCount);
    const paged     = sorted.slice((safePage - 1) * pageSize, safePage * pageSize);

    const handlePageSizeChange = (n: number) => {
        setUserPageSize(n);
        setPage(1);
    };

    const subGrids:    SubGridConfig[]    = config.subGrids   ?? [];
    const detailViews: DetailViewConfig[] = config.detailViews ?? [];

    const handleSort = (key: string) => {
        if (!config.sortFields.includes(key)) return;
        if (sortField === key) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
        else { setSortField(key); setSortDir('asc'); }
        setPage(1);
    };

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        setQuery(e.target.value);
        setPage(1);
    };

    const openSubGrid = async (sg: SubGridConfig, row: T) => {
        const paramValue = getField(row, sg.rowField);
        const title = `${sg.buttonLabel} (${sg.rowField}: ${paramValue})`;
        setSubModal({open: true, loading: true, title, rows: [], gridConfig: null});
        try {
            const data = await sendPost(sg.fetchUrl, {[sg.urlParam]: paramValue}) as {rows: unknown[]; gridConfig: GridConfig};
            setSubModal({open: true, loading: false, title, rows: data.rows ?? [], gridConfig: data.gridConfig ?? null});
        } catch {
            setSubModal(CLOSED_SUB);
        }
    };

    const openDetailView = async (dv: DetailViewConfig, row: T) => {
        const paramValue = getField(row, dv.rowField);
        const title = `${dv.buttonLabel} #${paramValue}`;
        setDetModal({open: true, loading: true, title, sections: []});
        try {
            const data = await sendPost(dv.fetchUrl, {[dv.urlParam]: paramValue}) as {sections: DetailSection[]};
            setDetModal({open: true, loading: false, title, sections: data.sections ?? []});
        } catch {
            setDetModal(CLOSED_DETAIL);
        }
    };

    const renderCell = (row: T, key: string): React.ReactNode => {
        if (renders[key]) return renders[key]!(row);
        const val = getField(row, key);
        if (key in globalRenders) return globalRenders[key](val);
        return val == null ? '—' : String(val);
    };

    const totalCols = config.columns.length + subGrids.length + detailViews.length + (expandRenderer ? 1 : 0);

    // Pagination row — rendered both above and below the table so users
    // don't have to scroll to the bottom of a long grid to switch pages
    // or change the page size.
    const paginationRow = config.pageSize > 0 ? (
        <div className="flex items-center gap-3 text-sm text-secondary flex-wrap">
            {pageCount > 1 && (
                <>
                    <button type="button" data-test-id="admin-grid-prev" className="btn btn-sm btn-outline-secondary" title={t.Grid_PrevPage()} disabled={safePage <= 1}       onClick={() => setPage(p => Math.max(1, p - 1))}>‹</button>
                    <span className="font-medium">{safePage} / {pageCount}</span>
                    <button type="button" data-test-id="admin-grid-next" className="btn btn-sm btn-outline-secondary" title={t.Grid_NextPage()} disabled={safePage >= pageCount} onClick={() => setPage(p => Math.min(pageCount, p + 1))}>›</button>
                    <span className="text-muted ml-1">{sorted.length} {t.Grid_Items()}</span>
                </>
            )}
            <PageSizeSelector value={pageSize} onChange={handlePageSizeChange} />
        </div>
    ) : null;

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
                            {subGrids.map(sg => (
                                <th key={`__sg_${sg.fetchUrl}`} className="w-px" />
                            ))}
                            {detailViews.map(dv => (
                                <th key={`__dv_${dv.fetchUrl}`} className="w-px" />
                            ))}
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
                                            {subGrids.map(sg => (
                                                <td key={`__sg_${sg.fetchUrl}`} className="px-3 py-3 w-px whitespace-nowrap">
                                                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={e => { e.stopPropagation(); openSubGrid(sg, row); }}>
                                                        {sg.buttonLabel}
                                                    </button>
                                                </td>
                                            ))}
                                            {detailViews.map(dv => (
                                                <td key={`__dv_${dv.fetchUrl}`} className="px-3 py-3 w-px whitespace-nowrap">
                                                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={e => { e.stopPropagation(); openDetailView(dv, row); }}>
                                                        {dv.buttonLabel}
                                                    </button>
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

            {subModal.open && (
                subModal.loading ? (
                    <div className="fixed inset-0 flex items-center justify-center z-50 bg-black/35">
                        <div className="bg-surface rounded-lg px-8 py-6 shadow-xl text-secondary">Loading…</div>
                    </div>
                ) : subModal.gridConfig && (
                    <SubGridModal
                        title={subModal.title}
                        rows={subModal.rows}
                        gridConfig={subModal.gridConfig}
                        globalRenders={globalRenders}
                        onClose={() => setSubModal(CLOSED_SUB)}
                    />
                )
            )}

            {detModal.open && (
                detModal.loading ? (
                    <div className="fixed inset-0 flex items-center justify-center z-50 bg-black/35">
                        <div className="bg-surface rounded-lg px-8 py-6 shadow-xl text-secondary">Loading…</div>
                    </div>
                ) : (
                    <DetailViewModal
                        title={detModal.title}
                        sections={detModal.sections}
                        globalRenders={globalRenders}
                        onClose={() => setDetModal(CLOSED_DETAIL)}
                    />
                )
            )}
        </>
    );
}
