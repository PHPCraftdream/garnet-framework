import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {AdminUserLink} from '@common/Components/Admin/AdminLog/AdminUserLink';
import {LogDetailModal} from '@common/Components/Admin/AdminLog/LogDetailModal';
import {RequestLogDetail, RequestLogRow} from '@common/Components/Admin/AdminLog/details/RequestLogDetail';

type AccountsMap = Record<string, string>;

interface PageResponse {
    date: string;
    rows: RequestLogRow[];
    total: number;
    page: number;
    perPage: number;
    dates: string[];
    accounts?: AccountsMap;
}

interface Filters {
    status: string;
    method: string;
    uri: string;
    ua: string;
}

// Sentinel value for "guest" choice in the user filter (account_id === null).
const GUEST_FILTER_VALUE = 'guest';

interface Props {
    dates: string[];
    pageUrl: string;
}

const PER_PAGE_OPTIONS = [25, 50, 100];
const DEFAULT_PER_PAGE = 50;

const HTTP_METHODS = ['', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
const STATUS_OPTIONS = ['', '2xx', '3xx', '4xx', '5xx'];

/** Handles renderStatus. */
function renderStatus(status: number) {
    return <span className={status >= 500 ? 'admin-log-section-status-danger' : status >= 400 ?
        'admin-log-section-status-warning' : status >= 300 ? 'admin-log-section-status-info' :
        'admin-log-section-status-success'}>{status}</span>;
}

/** Handles renderFilterOption. */
function renderFilterOption(value: string) { return <option key={value} value={value}>{value || '—'}</option>; }
/** Handles renderPageOption. */
function renderPageOption(value: number) { return <option key={value} value={value}>{value}
    {t.Pagination_Items()}</option>; }

type FilterOption = {value: string; label: string};
/** Handles compareOptionLabels. */
function compareOptionLabels(a: FilterOption, b: FilterOption): number { return a.label.localeCompare(b.label); }
/** Handles buildUserOptions. */
function buildUserOptions(rows: readonly RequestLogRow[], accounts: AccountsMap): FilterOption[] {
    const seen = new Set<string>();
    const userOptions: {value: string; label: string}[] = [];
    let hasGuest = false;
    for (const row of rows) {
        if (row.account_id == null) { hasGuest = true; continue; }
        const key = String(row.account_id);
        if (seen.has(key)) continue;
        seen.add(key);
        userOptions.push({value: key, label: accounts[key] ?? `#${row.account_id}`});
    }
    userOptions.sort(compareOptionLabels);
    if (hasGuest) userOptions.unshift({value: GUEST_FILTER_VALUE, label: t.Logs_Requests_Guest()});
    userOptions.unshift({value: '', label: t.Logs_Requests_FilterByUserAll()});
    return userOptions;
}

/** Handles matchesUser. */
function matchesUser(row: RequestLogRow, filter: string): boolean {
    return !filter || (row.account_id == null ? filter === GUEST_FILTER_VALUE : String(row.account_id) === filter);
}

interface RequestState {
    dates: string[];
    date: string;
    rows: RequestLogRow[];
    accounts: AccountsMap;
    total: number;
    page: number;
    loading: boolean;
    perPage: number;
    userFilter: string;
    selected: RequestLogRow | null;
}

type LoadPage = (date: string, page: number, filters: Filters) => Promise<void>;

interface NetworkProps {
    pageUrl: string;
    date: string;
    perPage: number;
    appliedFilters: Filters;
    appliedVersion: number;
    updateRequest: (patch: Partial<RequestState>) => void;
    setLoader: (load: LoadPage | null) => void;
}

class RequestLogFilters extends AntiHookComponent<Props> {
    private readonly ui = new Carburetor<{filters: Filters; appliedVersion: number}>({
        filters: {status: '', method: '', uri: '', ua: ''},
        appliedVersion: 0,
    });

    private readonly request = new Carburetor<RequestState>({
        dates: [...this.props.dates], date: this.props.dates[0] ?? '', rows: [], accounts: {}, total: 0,
        page: 1, loading: false, perPage: DEFAULT_PER_PAGE, userFilter: '', selected: null,
    });
    private readonly rows = this.connectSelection(this.request, state => state.rows);
    private readonly accounts = this.connectSelection(this.request, state => state.accounts);
    private readonly selected = this.connectSelection(this.request, state => state.selected);
    private loader: LoadPage | null = null;
    /** Handles setLoader. */
    private readonly setLoader = (load: LoadPage | null) => { this.loader = load; };
    /** Handles goPrev. */
    private readonly goPrev = () => {
        const {date, page} = this.request.getData();
        if (page > 1) void this.loader?.(date, page - 1, this.appliedSnapshot);
    };
    /** Handles goNext. */
    private readonly goNext = () => {
        const {date, page, total, perPage} = this.request.getData();
        if (page < Math.max(1, Math.ceil(total / perPage))) void this.loader?.(date, page + 1, this.appliedSnapshot);
    };
    /** Handles updateRequest. */
    private readonly updateRequest = (patch: Partial<RequestState>) => {
        this.request.setData({...this.request.getData(), ...patch});
    };
    /** Handles changeDate. */
    private readonly changeDate = (event: React.ChangeEvent<HTMLInputElement>) => this.updateRequest({date:
        event.target.value});
    /** Handles changePerPage. */
    private readonly changePerPage = (event: React.ChangeEvent<HTMLSelectElement>) =>
        this.updateRequest({perPage: Number(event.target.value)});
    /** Handles changeUser. */
    private readonly changeUser = (userFilter: string) => this.updateRequest({userFilter});
    /** Handles selectRow. */
    private readonly selectRow = (event: React.MouseEvent<HTMLTableRowElement>) => {
        const row = this.rows()[Number(event.currentTarget.dataset.rowIndex)];
        if (row) this.updateRequest({selected: {...row}});
    };
    /** Handles closeDetail. */
    private readonly closeDetail = () => this.updateRequest({selected: null});

    private filtersRevision = 0;
    private appliedRevision = -1;
    private appliedSnapshot: Filters = {status: '', method: '', uri: '', ua: ''};

    /** Handles patch. */
    private patch(patch: Partial<Filters>): void {
        this.filtersRevision++;
        const state = this.ui.getData();
        this.ui.setData({...state, filters: {...state.filters, ...patch}});
    }

    /** Handles changeMethod. */
    private readonly changeMethod = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({method:
        event.target.value});
    /** Handles changeStatus. */
    private readonly changeStatus = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({status:
        event.target.value});
    /** Handles changeUri. */
    private readonly changeUri = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({uri: event.target.value});
    /** Handles changeUa. */
    private readonly changeUa = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({ua: event.target.value});
    /** Handles applyFilters. */
    private readonly applyFilters = () => {
        if (this.appliedRevision === this.filtersRevision) return;
        this.appliedRevision = this.filtersRevision;
        const state = this.ui.getData();
        this.appliedSnapshot = {...state.filters};
        this.ui.setData({...state, appliedVersion: state.appliedVersion + 1});
    };
    /** Handles resetFilters. */
    private readonly resetFilters = () => {
        const empty: Filters = {status: '', method: '', uri: '', ua: ''};
        this.appliedRevision = ++this.filtersRevision;
        this.appliedSnapshot = {...empty};
        this.ui.setData({filters: empty, appliedVersion: this.ui.getData().appliedVersion + 1});
    };
    /** Handles onFilterEnter. */
    private readonly onFilterEnter = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') this.applyFilters();
    };

    /** Handles renderFilters. */
    private renderFilters(filters: Filters, dates: readonly string[], date: string, userFilter: string,
        userOptions: FilterOption[], total: number, rowCount: number, visibleCount: number, loading: boolean) {
        const minDate = dates[dates.length - 1];
        const maxDate = dates[0];
        return (
        <div className="admin-log-filters">
            <div className="admin-request-filter-cell">
                <label className="admin-request-filter-label" htmlFor="request-log-date">{t.RequestLog_Date()}</label>
                <DateInput
                    id="request-log-date"
                    value={date}
                    min={minDate}
                    max={maxDate}
                    onChange={this.changeDate}
                    data-test-id="request-log-date-input"
                />
            </div>
            <div className="admin-request-filter-cell">
                <label className="admin-request-filter-label"
                    htmlFor="request-log-method">{t.RequestLog_Method()}</label>
                <select
                    id="request-log-method"
                    className="admin-log-section-select"
                    value={filters.method}
                    onChange={this.changeMethod}
                    data-test-id="request-log-method-filter"
                >
                    {HTTP_METHODS.map(renderFilterOption)}
                </select>
            </div>
            <div className="admin-request-filter-cell">
                <label className="admin-request-filter-label"
                    htmlFor="request-log-status">{t.RequestLog_Status()}</label>
                <select
                    id="request-log-status"
                    className="admin-log-section-select"
                    value={STATUS_OPTIONS.includes(filters.status) ? filters.status : ''}
                    onChange={this.changeStatus}
                    data-test-id="request-log-status-filter"
                >
                    {STATUS_OPTIONS.map(renderFilterOption)}
                </select>
            </div>
            <div className="admin-request-filter-cell">
                <label className="admin-request-filter-label" htmlFor="request-log-uri">{t.RequestLog_Uri()}</label>
                <input
                    id="request-log-uri"
                    type="text"
                    className="admin-filter-field"
                    placeholder="/admin/..."
                    value={filters.uri}
                    onChange={this.changeUri}
                    onKeyDown={this.onFilterEnter}
                    data-test-id="request-log-uri-filter"
                />
            </div>
            <div className="admin-request-filter-cell">
                <label className="admin-request-filter-label" htmlFor="request-log-ua">{t.RequestLog_Ua()}</label>
                <input
                    id="request-log-ua"
                    type="text"
                    className="admin-filter-field"
                    placeholder="Mozilla / curl..."
                    value={filters.ua}
                    onChange={this.changeUa}
                    onKeyDown={this.onFilterEnter}
                    data-test-id="request-log-ua-filter"
                />
            </div>
            {userOptions.length > 1 && (
                <div className="admin-log-person-filter">
                    <label className="admin-request-filter-label"
                        htmlFor="request-log-user">{t.Logs_Requests_FilterByUser()}</label>
                    <Combobox
                        options={userOptions}
                        value={userFilter}
                        onChange={this.changeUser}
                        placeholder={t.Logs_Requests_FilterByUserAll()}
                        searchPlaceholder={t.Grid_Search()}
                        emptyText={t.Logs_Requests_FilterByUserAll()}
                        testId="request-log-user-filter"
                    />
                </div>
            )}
            <div className="admin-request-filter-actions">
                <button
                    type="button"
                    className="admin-request-apply"
                    onClick={this.applyFilters}
                    data-test-id="request-log-apply"
                >
                    {t.Grid_Search()}
                </button>
                <button
                    type="button"
                    className="admin-log-section-reset"
                    onClick={this.resetFilters}
                    data-test-id="request-log-reset"
                >
                    ✕
                </button>
                <span className="admin-request-counter">
                    {visibleCount === rowCount ? total : `${visibleCount} / ${total}`}
                    {loading ? ' …' : ''}
                </span>
            </div>
        </div>
        );
    }

    /** Handles renderRow. */
    private renderRow(r: RequestLogRow, idx: number, sourceIndex: number, accounts: AccountsMap) {
        return (
        <tr
            key={`${r.ts}-${idx}`}
            data-test-id={`requests-row-${idx}`}
            className="admin-request-row"
            data-row-index={sourceIndex}
            onClick={this.selectRow}
        >
            <td className="admin-request-time">{r.ts}</td>
            <td className="admin-request-mono">{r.method}</td>
            <td className="admin-request-cell">{renderStatus(r.status)}</td>
            <td className="admin-request-uri">{r.uri}</td>
            <td className="admin-request-duration">{r.duration_ms}</td>
            <td className="admin-request-cell">
                {r.account_id != null ? (
                    <AdminUserLink
                        id={r.account_id}
                        name={accounts[String(r.account_id)] ?? `#${r.account_id}`}
                    />
                ) : (
                    <span className="admin-log-section-muted">{t.Logs_Requests_Guest()}</span>
                )}
            </td>
            <td className="admin-request-mono">{r.ip}</td>
            <td className="admin-request-ua" title={r.ua}>{r.ua}</td>
        </tr>
        );
    }

    /** Handles renderTable. */
    private renderTable(rows: readonly RequestLogRow[], accounts: AccountsMap, userFilter: string) {
        const renderedRows: React.ReactNode[] = [];
        for (const [sourceIndex, row] of rows.entries()) {
            if (matchesUser(row, userFilter)) renderedRows.push(this.renderRow(row, renderedRows.length,
                sourceIndex, accounts));
        }
        return (
        <div className="admin-request-table-scroll">
            <table className="admin-request-table">
                <thead>
                    <tr className="admin-request-heading-row">
                        <th className="admin-request-heading">{t.RequestLog_Time()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Method()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Status()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Uri()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Duration()}</th>
                        <th className="admin-request-heading">{t.Logs_Requests_ColumnUser()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Ip()}</th>
                        <th className="admin-request-heading">{t.RequestLog_Ua()}</th>
                    </tr>
                </thead>
                <tbody>
                    {renderedRows}
                </tbody>
            </table>
        </div>
        );
    }

    /** Handles renderPagination. */
    private renderPagination(page: number, perPage: number, total: number, loading: boolean) {
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        return (
        <div className="admin-request-pagination">
            <button
                type="button"
                className="admin-request-page-button"
                onClick={this.goPrev}
                disabled={page <= 1 || loading}
                data-test-id="request-log-prev"
            >
                ‹
            </button>
            <span className="admin-request-page-meta">{page} / {totalPages}</span>
            <button
                type="button"
                className="admin-request-page-button"
                onClick={this.goNext}
                disabled={page >= totalPages || loading}
                data-test-id="request-log-next"
            >
                ›
            </button>
            <select
                className="admin-request-page-select"
                value={perPage}
                onChange={this.changePerPage}
                data-test-id="request-log-per-page"
            >
                {PER_PAGE_OPTIONS.map(renderPageOption)}
            </select>
        </div>
        );
    }

    /** Handles render. */
    render() {
        const {filters, appliedVersion} = this.useCarburetor(this.ui);
        const {dates, date, total, page, loading, perPage, userFilter} = this.useCarburetor(this.request);
        const rows = this.rows();
        const accounts = this.accounts();
        const selected = this.selected();
        const userOptions = buildUserOptions(rows, accounts);
        let visibleCount = 0;
        for (const row of rows) if (matchesUser(row, userFilter)) visibleCount++;
        return (
            <div data-test-id="admin-request-log">
                <RequestLogNetwork pageUrl={this.props.pageUrl} date={date} perPage={perPage}
                    appliedFilters={this.appliedSnapshot} appliedVersion={appliedVersion}
                    updateRequest={this.updateRequest} setLoader={this.setLoader} />
                {this.renderFilters(filters, dates, date, userFilter, userOptions, total, rows.length,
                    visibleCount, loading)}
                {dates.length === 0 || total === 0 ? (
                    <div className="admin-request-empty" data-test-id="request-log-empty">{t.RequestLog_NoData()}</div>
                ) : <>{this.renderTable(rows, accounts, userFilter)}{this.renderPagination(page, perPage,
                    total, loading)}</>}
                {selected && <LogDetailModal onClose={this.closeDetail}><RequestLogDetail row={selected}
                    accounts={accounts} /></LogDetailModal>}
            </div>
        );
    }
}

/** Handles loadRequestPage. */
async function loadRequestPage({pageUrl, perPage, updateRequest}: NetworkProps, targetDate: string,
    targetPage: number, filters: Filters): Promise<void> {
    if (!targetDate) {
        updateRequest({rows: [], accounts: {}, total: 0});
        return;
    }
    updateRequest({loading: true});
    try {
        const res = await sendPost<{date: string; page: number; perPage: number; status: string; method:
            string; uri: string; ua: string}, PageResponse>(
            pageUrl,
            {date: targetDate, page: targetPage, perPage, ...filters},
        );
        updateRequest({
            rows: res.rows ?? [], accounts: {...res.accounts},
            total: res.total ?? 0, page: res.page ?? targetPage, userFilter: '',
            ...(res.dates && res.dates.length ? {dates: [...res.dates]} : {}),
        });
    } finally {
        updateRequest({loading: false});
    }
}

class RequestLogNetwork extends AntiHookComponent<NetworkProps> {
    /** Handles load. */
    private readonly load: LoadPage = (targetDate, targetPage, filters) => loadRequestPage(this.props,
        targetDate, targetPage, filters);
    /** Handles unregisterLoader. */
    private readonly unregisterLoader = () => this.props.setLoader(null);

    /** Handles useEffects. */
    protected override useEffects(): void {
        const props = this.props;
        const {pageUrl, date, perPage, appliedFilters, appliedVersion, updateRequest, setLoader} = props;
        this.useEffect('registerLoader', () => {
            setLoader(this.load);
            return this.unregisterLoader;
        }, [pageUrl, perPage, updateRequest, setLoader]);

        this.useEffect('refreshFirstPage', () => {
            if (date) {
                // Wait until the parent has committed its subscriptions.
                queueMicrotask(() => { void loadRequestPage(props, date, 1, appliedFilters); });
            }
        }, [date, appliedFilters, appliedVersion, pageUrl, perPage, updateRequest]);
    }

    /** Handles render. */
    public render() {
        return null;
    }
}

export const AdminRequestLogIsland = RequestLogFilters;
