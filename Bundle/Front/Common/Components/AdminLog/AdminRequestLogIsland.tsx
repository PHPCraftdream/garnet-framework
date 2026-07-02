import * as React from 'react';
import {useState, useEffect, useCallback, useMemo} from 'react';
import {sendPost} from '@common/Api/sendPost';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {AdminUserLink} from './AdminUserLink';
import {LogDetailModal} from './LogDetailModal';
import {RequestLogDetail, RequestLogRow} from './details/RequestLogDetail';

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

const statusBadgeClass = (status: number): string => {
    if (status >= 500) return 'status-danger';
    if (status >= 400) return 'status-warning';
    if (status >= 300) return 'status-info';
    return 'status-success';
};

export const AdminRequestLogIsland: React.FC<Props> = ({dates: initialDates, pageUrl}) => {
    const [dates, setDates] = useState<string[]>(initialDates);
    const [date, setDate] = useState<string>(initialDates[0] ?? '');
    const [filters, setFilters] = useState<Filters>({status: '', method: '', uri: '', ua: ''});
    const [appliedFilters, setAppliedFilters] = useState<Filters>({status: '', method: '', uri: '', ua: ''});
    const [rows, setRows] = useState<RequestLogRow[]>([]);
    const [accounts, setAccounts] = useState<AccountsMap>({});
    const [total, setTotal] = useState<number>(0);
    const [page, setPage] = useState<number>(1);
    const [loading, setLoading] = useState<boolean>(false);
    const [perPage, setPerPage] = useState<number>(DEFAULT_PER_PAGE);
    const [userFilter, setUserFilter] = useState<string>('');
    const [selected, setSelected] = useState<RequestLogRow | null>(null);

    const load = useCallback(async (targetDate: string, targetPage: number, f: Filters) => {
        if (!targetDate) {
            setRows([]);
            setAccounts({});
            setTotal(0);
            return;
        }
        setLoading(true);
        try {
            const res = await sendPost<{date: string; page: number; perPage: number; status: string; method: string; uri: string; ua: string}, PageResponse>(
                pageUrl,
                {date: targetDate, page: targetPage, perPage, ...f},
            );
            setRows(res.rows ?? []);
            setAccounts(res.accounts ?? {});
            setTotal(res.total ?? 0);
            setPage(res.page ?? targetPage);
            if (res.dates && res.dates.length) {
                setDates(res.dates);
            }
            // reset client-side user filter on each page reload (visible-page only).
            setUserFilter('');
        } finally {
            setLoading(false);
        }
    }, [pageUrl, perPage]);

    useEffect(() => {
        if (date) {
            void load(date, 1, appliedFilters);
        }
    }, [date, appliedFilters, load]);

    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const minDate = dates.length ? dates[dates.length - 1] : undefined;
    const maxDate = dates.length ? dates[0] : undefined;

    // Distinct accounts present in the visible page (for the user filter).
    // First option is the "All" pseudo-entry (value === '') used as the default/reset choice.
    const userOptions = useMemo<{value: string; label: string}[]>(() => {
        const seen = new Set<string>();
        const out: {value: string; label: string}[] = [];
        let hasGuest = false;
        for (const r of rows) {
            if (r.account_id === null || r.account_id === undefined) {
                hasGuest = true;
                continue;
            }
            const key = String(r.account_id);
            if (seen.has(key)) continue;
            seen.add(key);
            const label = accounts[key] ?? `#${r.account_id}`;
            out.push({value: key, label});
        }
        out.sort((a, b) => a.label.localeCompare(b.label));
        if (hasGuest) {
            out.unshift({value: GUEST_FILTER_VALUE, label: t.Logs_Requests_Guest()});
        }
        out.unshift({value: '', label: t.Logs_Requests_FilterByUserAll()});
        return out;
    }, [rows, accounts]);

    const filteredRows = useMemo<RequestLogRow[]>(() => {
        if (userFilter === '') return rows;
        return rows.filter(r => {
            if (r.account_id === null || r.account_id === undefined) {
                return userFilter === GUEST_FILTER_VALUE;
            }
            return String(r.account_id) === userFilter;
        });
    }, [rows, userFilter]);

    const goPrev = () => {
        if (page > 1) void load(date, page - 1, appliedFilters);
    };
    const goNext = () => {
        if (page < totalPages) void load(date, page + 1, appliedFilters);
    };

    const applyFilters = () => setAppliedFilters(filters);
    const resetFilters = () => {
        const empty: Filters = {status: '', method: '', uri: '', ua: ''};
        setFilters(empty);
        setAppliedFilters(empty);
    };

    const onFilterEnter = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') applyFilters();
    };

    return (
        <div data-test-id="admin-request-log">
            <div className="admin-log-filters">
                <div className="filter-cell">
                    <label htmlFor="request-log-date">{t.RequestLog_Date()}</label>
                    <DateInput
                        id="request-log-date"
                        value={date}
                        min={minDate}
                        max={maxDate}
                        onChange={e => setDate(e.target.value)}
                        data-test-id="request-log-date-input"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="request-log-method">{t.RequestLog_Method()}</label>
                    <select
                        id="request-log-method"
                        className="form-select"
                        value={filters.method}
                        onChange={e => setFilters(f => ({...f, method: e.target.value}))}
                        data-test-id="request-log-method-filter"
                    >
                        {HTTP_METHODS.map(m => (
                            <option key={m} value={m}>{m || '—'}</option>
                        ))}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="request-log-status">{t.RequestLog_Status()}</label>
                    <select
                        id="request-log-status"
                        className="form-select"
                        value={STATUS_OPTIONS.includes(filters.status) ? filters.status : ''}
                        onChange={e => setFilters(f => ({...f, status: e.target.value}))}
                        data-test-id="request-log-status-filter"
                    >
                        {STATUS_OPTIONS.map(s => (
                            <option key={s} value={s}>{s || '—'}</option>
                        ))}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="request-log-uri">{t.RequestLog_Uri()}</label>
                    <input
                        id="request-log-uri"
                        type="text"
                        className="form-control"
                        placeholder="/admin/..."
                        value={filters.uri}
                        onChange={e => setFilters(f => ({...f, uri: e.target.value}))}
                        onKeyDown={onFilterEnter}
                        data-test-id="request-log-uri-filter"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="request-log-ua">{t.RequestLog_Ua()}</label>
                    <input
                        id="request-log-ua"
                        type="text"
                        className="form-control"
                        placeholder="Mozilla / curl..."
                        value={filters.ua}
                        onChange={e => setFilters(f => ({...f, ua: e.target.value}))}
                        onKeyDown={onFilterEnter}
                        data-test-id="request-log-ua-filter"
                    />
                </div>
                {userOptions.length > 1 && (
                    <div className="filter-cell filter-cell-user">
                        <label htmlFor="request-log-user">{t.Logs_Requests_FilterByUser()}</label>
                        <Combobox
                            options={userOptions}
                            value={userFilter}
                            onChange={setUserFilter}
                            placeholder={t.Logs_Requests_FilterByUserAll()}
                            searchPlaceholder={t.Grid_Search()}
                            emptyText={t.Logs_Requests_FilterByUserAll()}
                            testId="request-log-user-filter"
                        />
                    </div>
                )}
                <div className="filter-actions">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-primary whitespace-nowrap"
                        onClick={applyFilters}
                        data-test-id="request-log-apply"
                    >
                        {t.Grid_Search()}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        onClick={resetFilters}
                        data-test-id="request-log-reset"
                    >
                        ✕
                    </button>
                    <span className="filter-counter">
                        {filteredRows.length === rows.length ? total : `${filteredRows.length} / ${total}`}
                        {loading ? ' …' : ''}
                    </span>
                </div>
            </div>

            {dates.length === 0 || total === 0 ? (
                <div className="admin-dash-empty" data-test-id="request-log-empty">{t.RequestLog_NoData()}</div>
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="admin-detail-table">
                            <thead>
                                <tr>
                                    <th>{t.RequestLog_Time()}</th>
                                    <th>{t.RequestLog_Method()}</th>
                                    <th>{t.RequestLog_Status()}</th>
                                    <th>{t.RequestLog_Uri()}</th>
                                    <th>{t.RequestLog_Duration()}</th>
                                    <th>{t.Logs_Requests_ColumnUser()}</th>
                                    <th>{t.RequestLog_Ip()}</th>
                                    <th>{t.RequestLog_Ua()}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredRows.map((r, idx) => (
                                    <tr
                                        key={`${r.ts}-${idx}`}
                                        data-test-id={`requests-row-${idx}`}
                                        className="cursor-pointer hover:bg-surface-hover transition-colors"
                                        onClick={() => setSelected(r)}
                                    >
                                        <td className="admin-dash-meta whitespace-nowrap">{r.ts}</td>
                                        <td className="font-mono text-sm">{r.method}</td>
                                        <td><span className={`badge ${statusBadgeClass(r.status)}`}>{r.status}</span></td>
                                        <td className="font-mono text-sm break-all">{r.uri}</td>
                                        <td className="text-right">{r.duration_ms}</td>
                                        <td>
                                            {r.account_id != null ? (
                                                <AdminUserLink
                                                    id={r.account_id}
                                                    name={accounts[String(r.account_id)] ?? `#${r.account_id}`}
                                                />
                                            ) : (
                                                <span className="text-muted">{t.Logs_Requests_Guest()}</span>
                                            )}
                                        </td>
                                        <td className="font-mono text-sm">{r.ip}</td>
                                        <td className="admin-dash-meta truncate max-w-[280px]" title={r.ua}>{r.ua}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center gap-3 mt-3">
                        <button
                            type="button"
                            className="admin-filter-btn"
                            onClick={goPrev}
                            disabled={page <= 1 || loading}
                            data-test-id="request-log-prev"
                        >
                            ‹
                        </button>
                        <span className="admin-dash-meta">{page} / {totalPages}</span>
                        <button
                            type="button"
                            className="admin-filter-btn"
                            onClick={goNext}
                            disabled={page >= totalPages || loading}
                            data-test-id="request-log-next"
                        >
                            ›
                        </button>
                        <select
                            className="form-select form-select-sm w-auto ml-auto"
                            value={perPage}
                            onChange={e => setPerPage(Number(e.target.value))}
                            data-test-id="request-log-per-page"
                        >
                            {PER_PAGE_OPTIONS.map(n => (
                                <option key={n} value={n}>{n} {t.Pagination_Items()}</option>
                            ))}
                        </select>
                    </div>
                </>
            )}
            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <RequestLogDetail row={selected} accounts={accounts} />
                </LogDetailModal>
            )}
        </div>
    );
};
