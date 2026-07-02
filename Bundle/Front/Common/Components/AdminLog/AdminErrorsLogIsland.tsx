import * as React from 'react';
import {useState, useEffect, useCallback} from 'react';
import {sendPost} from '@common/Api/sendPost';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {LogDetailModal} from './LogDetailModal';
import {ErrorLogDetail, ErrorRow} from './details/ErrorLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/pagination';

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

const PER_PAGE_OPTIONS = [DEFAULT_PAGE_SIZE, 50, 100, 200];

export const AdminErrorsLogIsland: React.FC<Props> = ({dates: initialDates, pageUrl}) => {
    const [dates, setDates] = useState<string[]>(initialDates);
    const [date, setDate] = useState<string>(initialDates[0] ?? '');
    const [search, setSearch] = useState<string>('');
    const [appliedSearch, setAppliedSearch] = useState<string>('');
    const [perPage, setPerPage] = useState<number>(DEFAULT_PAGE_SIZE);
    const [rows, setRows] = useState<ErrorRow[]>([]);
    const [total, setTotal] = useState<number>(0);
    const [page, setPage] = useState<number>(1);
    const [loading, setLoading] = useState<boolean>(false);
    const [selected, setSelected] = useState<ErrorRow | null>(null);

    const load = useCallback(
        async (targetDate: string, targetPage: number, targetSearch: string, targetPerPage: number): Promise<void> => {
            if (!targetDate) {
                setRows([]);
                setTotal(0);
                return;
            }
            setLoading(true);
            try {
                const res = await sendPost<
                    {date: string; page: number; perPage: number; search: string},
                    PageResponse
                >(pageUrl, {date: targetDate, page: targetPage, perPage: targetPerPage, search: targetSearch});
                setRows(res.rows ?? []);
                setTotal(res.total ?? 0);
                setPage(res.page ?? targetPage);
                if (res.dates && res.dates.length) {
                    setDates(res.dates);
                    if (!targetDate && res.date) {
                        setDate(res.date);
                    }
                }
            } finally {
                setLoading(false);
            }
        },
        [pageUrl],
    );

    useEffect(() => {
        if (date) {
            void load(date, 1, appliedSearch, perPage);
        }
    }, [date, appliedSearch, perPage, load]);

    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const minDate = dates.length ? dates[dates.length - 1] : undefined;
    const maxDate = dates.length ? dates[0] : undefined;

    const goPrev = (): void => {
        if (page > 1) void load(date, page - 1, appliedSearch, perPage);
    };
    const goNext = (): void => {
        if (page < totalPages) void load(date, page + 1, appliedSearch, perPage);
    };

    const applySearch = (): void => setAppliedSearch(search);
    const onSearchKey = (e: React.KeyboardEvent<HTMLInputElement>): void => {
        if (e.key === 'Enter') applySearch();
    };

    return (
        <div data-test-id="admin-errors-log">
            <div className="admin-log-filters">
                <div className="filter-cell">
                    <label htmlFor="errors-date">
                        {t.Logs_Errors_Date()}
                    </label>
                    <DateInput
                        id="errors-date"
                        className="text-sm"
                        value={date}
                        min={minDate}
                        max={maxDate}
                        onChange={e => setDate(e.target.value)}
                        data-test-id="errors-date-select"
                    />
                </div>

                <div className="filter-cell">
                    <label htmlFor="errors-search">
                        {t.Logs_Errors_Search()}
                    </label>
                    <input
                        id="errors-search"
                        type="text"
                        className="form-control"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        onKeyDown={onSearchKey}
                        data-test-id="errors-search-input"
                    />
                </div>

                <div className="filter-cell">
                    <label htmlFor="errors-perpage">
                        {t.Grid_Items()}
                    </label>
                    <select
                        id="errors-perpage"
                        className="form-select"
                        value={perPage}
                        onChange={e => setPerPage(parseInt(e.target.value, 10) || 50)}
                        data-test-id="errors-perpage-select"
                    >
                        {PER_PAGE_OPTIONS.map(n => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="filter-actions">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-primary whitespace-nowrap"
                        onClick={applySearch}
                        data-test-id="errors-apply"
                    >
                        {t.Grid_Search()}
                    </button>
                    <span className="filter-counter">
                        {total}
                        {loading ? ' …' : ''}
                    </span>
                </div>
            </div>

            {dates.length === 0 || total === 0 ? (
                <div className="admin-dash-empty" data-test-id="errors-empty">
                    {t.Logs_Errors_Empty()}
                </div>
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="admin-detail-table">
                            <thead>
                                <tr>
                                    <th className="whitespace-nowrap">{t.RequestLog_Time()}</th>
                                    <th>{t.Logs_Errors_Name()}</th>
                                    <th>{t.Logs_Errors_Message()}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((r, idx) => {
                                    const firstLine = r.message.split('\n', 1)[0] ?? '';
                                    return (
                                        <tr
                                            key={`${r.file}-${idx}`}
                                            data-test-id={`errors-row-${idx}`}
                                            className="cursor-pointer hover:bg-surface-hover transition-colors"
                                            onClick={() => setSelected(r)}
                                        >
                                            <td className="admin-dash-meta whitespace-nowrap align-top">{r.ts}</td>
                                            <td className="font-mono text-sm align-top break-all">{r.name}</td>
                                            <td className="align-top">
                                                <div className="font-mono text-sm whitespace-pre-wrap break-words">
                                                    {firstLine}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center gap-3 mt-3">
                        <button
                            type="button"
                            className="admin-filter-btn"
                            onClick={goPrev}
                            disabled={page <= 1 || loading}
                            data-test-id="errors-prev"
                        >
                            ‹
                        </button>
                        <span className="admin-dash-meta">
                            {page} / {totalPages}
                        </span>
                        <button
                            type="button"
                            className="admin-filter-btn"
                            onClick={goNext}
                            disabled={page >= totalPages || loading}
                            data-test-id="errors-next"
                        >
                            ›
                        </button>
                    </div>
                </>
            )}

            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <ErrorLogDetail row={selected} />
                </LogDetailModal>
            )}
        </div>
    );
};
