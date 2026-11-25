import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/Data/pagination/DEFAULT_PAGE_SIZE';
import {LogDetailModal} from '@common/Components/Admin/AdminLog/LogDetailModal';
import {ErrorLogDetail, ErrorRow} from '@common/Components/Admin/AdminLog/details/ErrorLogDetail';

interface PageResponse {
    date: string;
    rows: ErrorRow[];
    total: number;
    page: number;
    perPage: number;
    dates: string[];
    search: string;
}

interface Props {
    dates: string[];
    pageUrl: string;
}

interface ErrorState {
    dates: string[];
    date: string;
    search: string;
    appliedSearch: string;
    perPage: number;
    rows: ErrorRow[];
    total: number;
    page: number;
    loading: boolean;
    selected: ErrorRow | null;
}

type LoadPage = (date: string, page: number, search: string, perPage: number) => Promise<void>;
interface NetworkProps {
    pageUrl: string;
    date: string;
    appliedSearch: string;
    perPage: number;
    update: (patch: Partial<ErrorState>) => void;
    setLoader: (load: LoadPage | null) => void;
}

const PER_PAGE_OPTIONS = [DEFAULT_PAGE_SIZE, 50, 100, 200];
/** Handles renderPageOption. */
function renderPageOption(n: number) { return <option key={n} value={n}>{n}</option>; }

class ErrorsLog extends AntiHookComponent<Props> {
    private readonly store = new Carburetor<ErrorState>({
        dates: [...this.props.dates], date: this.props.dates[0] ?? '', search: '', appliedSearch: '',
        perPage: DEFAULT_PAGE_SIZE, rows: [], total: 0, page: 1, loading: false, selected: null,
    });
    private readonly rows = this.connectSelection(this.store, state => state.rows);
    private readonly selected = this.connectSelection(this.store, state => state.selected);
    private loader: LoadPage | null = null;
    /** Handles setLoader. */
    private readonly setLoader = (load: LoadPage | null) => { this.loader = load; };
    /** Handles update. */
    private readonly update = (patch: Partial<ErrorState>) => this.store.setData({...this.store.getData(), ...patch});
    /** Handles changeDate. */
    private readonly changeDate = (event: React.ChangeEvent<HTMLInputElement>) => this.update({date:
        event.target.value});
    /** Handles changeSearch. */
    private readonly changeSearch = (event: React.ChangeEvent<HTMLInputElement>) => this.update({search:
        event.target.value});
    /** Handles changePerPage. */
    private readonly changePerPage = (event: React.ChangeEvent<HTMLSelectElement>) => this.update({perPage:
        parseInt(event.target.value, 10) || 50});
    /** Handles applySearch. */
    private readonly applySearch = () => this.update({appliedSearch: this.store.getData().search});
    /** Handles onSearchKey. */
    private readonly onSearchKey = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') this.applySearch();
    };
    /** Handles goPrev. */
    private readonly goPrev = () => {
        const {date, page, appliedSearch, perPage} = this.store.getData();
        if (page > 1) void this.loader?.(date, page - 1, appliedSearch, perPage);
    };
    /** Handles goNext. */
    private readonly goNext = () => {
        const {date, page, total, appliedSearch, perPage} = this.store.getData();
        if (page < Math.max(1, Math.ceil(total / perPage))) void this.loader?.(date, page + 1, appliedSearch, perPage);
    };
    /** Handles selectRow. */
    private readonly selectRow = (event: React.MouseEvent<HTMLTableRowElement>) => {
        const row = this.store.getData().rows[Number(event.currentTarget.dataset.rowIndex)];
        if (row) this.update({selected: {...row}});
    };
    /** Handles closeDetail. */
    private readonly closeDetail = () => this.update({selected: null});

    /** Handles renderFilters. */
    private renderFilters(dates: readonly string[], date: string, search: string, perPage: number, total:
        number, loading: boolean) {
        return (
            <div className="admin-log-filters">
                <div className="filter-cell">
                    <label htmlFor="errors-date">{t.Logs_Errors_Date()}</label>
                    <DateInput id="errors-date" value={date} min={dates[dates.length - 1]} max={dates[0]}
                        onChange={this.changeDate} data-test-id="errors-date-select" />
                </div>
                <div className="filter-cell">
                    <label htmlFor="errors-search">{t.Logs_Errors_Search()}</label>
                    <input id="errors-search" type="text" className="admin-filter-field" value={search}
                        onChange={this.changeSearch} onKeyDown={this.onSearchKey} data-test-id="errors-search-input" />
                </div>
                <div className="filter-cell">
                    <label htmlFor="errors-perpage">{t.Grid_Items()}</label>
                    <select id="errors-perpage" className="admin-log-section-select" value={perPage}
                        onChange={this.changePerPage} data-test-id="errors-perpage-select">
                        {PER_PAGE_OPTIONS.map(renderPageOption)}
                    </select>
                </div>
                <div className="filter-actions">
                    <button type="button" className="admin-request-apply" onClick={this.applySearch}
                        data-test-id="errors-apply">{t.Grid_Search()}</button>
                    <span className="admin-request-counter">{total}{loading ? ' …' : ''}</span>
                </div>
            </div>
        );
    }

    /** Handles renderRow. */
    private renderRow = (row: ErrorRow, index: number) => (
        <tr key={`${row.file}-${index}`} data-test-id={`errors-row-${index}`} data-row-index={index}
            className="admin-errors-row" onClick={this.selectRow}>
            <td className="admin-errors-time">{row.ts}</td>
            <td className="admin-errors-name">{row.name}</td>
            <td className="admin-errors-cell"><div className="admin-errors-message">{row.message.split('\n',
                1)[0] ?? ''}</div></td>
        </tr>
    );

    /** Handles renderTable. */
    private renderTable(rows: readonly ErrorRow[], page: number, perPage: number, total: number, loading: boolean) {
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        return (
            <>
                <div className="admin-request-table-scroll">
                    <table className="admin-request-table">
                        <thead><tr className="admin-request-heading-row"><th
                            className="admin-errors-heading-time">{t.RequestLog_Time()}</th><th
                            className="admin-request-heading">{t.Logs_Errors_Name()}</th><th
                            className="admin-request-heading">{t.Logs_Errors_Message()}</th></tr></thead>
                        <tbody>{rows.map(this.renderRow)}</tbody>
                    </table>
                </div>
                <div className="admin-request-pagination">
                    <button type="button" className="admin-request-page-button" onClick={this.goPrev}
                        disabled={page <= 1 || loading} data-test-id="errors-prev">‹</button>
                    <span className="admin-request-page-meta">{page} / {totalPages}</span>
                    <button type="button" className="admin-request-page-button" onClick={this.goNext}
                        disabled={page >= totalPages || loading} data-test-id="errors-next">›</button>
                </div>
            </>
        );
    }

    /** Handles render. */
    render() {
        const state = this.useCarburetor(this.store);
        const rows = this.rows();
        const selected = this.selected();
        return (
            <div data-test-id="admin-errors-log">
                <ErrorsLogNetwork pageUrl={this.props.pageUrl} date={state.date} appliedSearch={state.appliedSearch}
                    perPage={state.perPage} update={this.update} setLoader={this.setLoader} />
                {this.renderFilters(state.dates, state.date, state.search, state.perPage, state.total, state.loading)}
                {state.dates.length === 0 || state.total === 0
                    ? <div className="admin-request-empty" data-test-id="errors-empty">{t.Logs_Errors_Empty()}</div>
                    : this.renderTable(rows, state.page, state.perPage, state.total, state.loading)}
                {selected && <LogDetailModal onClose={this.closeDetail}><ErrorLogDetail row={selected}
                    /></LogDetailModal>}
            </div>
        );
    }
}

/** Handles fetchErrorPage. */
async function fetchErrorPage({pageUrl, update}: Pick<NetworkProps, 'pageUrl' | 'update'>, targetDate: string,
    targetPage: number, targetSearch: string, targetPerPage: number): Promise<void> {
    if (!targetDate) {
        update({rows: [], total: 0});
        return;
    }
    update({loading: true});
    try {
        const res = await sendPost<{date: string; page: number; perPage: number; search: string}, PageResponse>(
            pageUrl, {date: targetDate, page: targetPage, perPage: targetPerPage, search: targetSearch},
        );
        update({rows: res.rows ?? [], total: res.total ?? 0, page: res.page ?? targetPage,
            ...(res.dates && res.dates.length ? {dates: [...res.dates]} : {})});
    } finally {
        update({loading: false});
    }
}

/** Handles refreshAfterCommit. */
function refreshAfterCommit(snapshot: Pick<NetworkProps, 'pageUrl' | 'update'>, date: string, search: string,
    perPage: number): void {
    queueMicrotask(() => { void fetchErrorPage(snapshot, date, 1, search, perPage); });
}

class ErrorsLogNetwork extends AntiHookComponent<NetworkProps> {
    /** Handles loadPage. */
    private readonly loadPage: LoadPage = (date, page, search, perPage) =>
        fetchErrorPage(this.props, date, page, search, perPage);

    /** Handles clearLoader. */
    private readonly clearLoader = () => this.props.setLoader(null);

    /** Handles useEffects. */
    protected useEffects(): void {
        const {pageUrl, date, appliedSearch, perPage, update, setLoader} = this.props;
        this.useEffect('setLoader', () => {
            setLoader(this.loadPage);
            return this.clearLoader;
        }, [pageUrl, update, setLoader]);
        this.useEffect('loadFirstPage', () => {
            if (date) {
                refreshAfterCommit({pageUrl, update}, date, appliedSearch, perPage);
            }
        }, [date, appliedSearch, perPage, pageUrl, update]);
    }

    /** Handles render. */
    render() {
        return null;
    }
}

export const AdminErrorsLogIsland = ErrorsLog;
