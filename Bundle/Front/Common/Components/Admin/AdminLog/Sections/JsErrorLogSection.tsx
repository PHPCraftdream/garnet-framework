import * as React from 'react';
import {useState, useMemo} from 'react';
import {JsErrorEntry, JsErrorsFilterOptions, GridConfig} from '../types';
import {AdminLogGrid} from '../AdminLogGrid';
import {PageResponse} from '@common/hooks/data/usePagination';
import {AdminUserLink} from '../AdminUserLink';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {LogDetailModal} from '../LogDetailModal';
import {JsErrorLogDetail} from '../details/JsErrorLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/Data/pagination';

interface Props {
    pageUrl: string;
    initialData: PageResponse<JsErrorEntry> | null;
    filterOptions: JsErrorsFilterOptions;
}

const truncate = (s: string, n: number): string => (s.length > n ? s.slice(0, n) + '…' : s);

export const JsErrorLogSection: React.FC<Props> = ({pageUrl, initialData, filterOptions}) => {
    const [selected, setSelected] = useState<JsErrorEntry | null>(null);
    const [accountId, setAccountId] = useState<string>('');
    const [fileFilter, setFileFilter] = useState<string>('');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');

    const allLabel = t.Admin_Log_Filter_All();

    const accountOptions = useMemo(() => (
        [{value: '', label: allLabel}, ...filterOptions.accounts.map(a => ({value: String(a.id), label: a.name}))]
    ), [filterOptions.accounts, allLabel]);

    const fileOptions = useMemo(() => (
        [{value: '', label: allLabel}, ...filterOptions.files.map(f => ({value: f, label: f}))]
    ), [filterOptions.files, allLabel]);

    const resetAll = (): void => {
        setAccountId('');
        setFileFilter('');
        setDateFrom('');
        setDateTo('');
    };

    const hasActiveFilter = !!(accountId || fileFilter || dateFrom || dateTo);

    const extraParams = useMemo(() => ({
        accountId: accountId ? Number(accountId) : 0,
        file: fileFilter,
        dateFrom: dateFrom ? Math.floor(new Date(dateFrom + 'T00:00:00').getTime() / 1000) : 0,
        dateTo: dateTo ? Math.floor(new Date(dateTo + 'T23:59:59').getTime() / 1000) : 0,
    }), [accountId, fileFilter, dateFrom, dateTo]);

    const config: GridConfig = useMemo(() => ({
        columns: [
            {key: 'last_seen_at', label: t.JsErrorLog_LastSeen()},
            {key: 'count',        label: t.JsErrorLog_Count()},
            {key: 'message',      label: t.JsErrorLog_Message()},
            {key: 'file',         label: t.JsErrorLog_File()},
            {key: 'account',      label: t.JsErrorLog_Account()},
        ],
        // account_name is hydrated after the query, not a real column — the
        // account dropdown above (server-side accountId filter) covers that
        // axis instead of free-text search.
        searchFields: ['message', 'file', 'url'],
        sortFields: ['id', 'last_seen_at', 'first_seen_at', 'count'],
        pageSize: DEFAULT_PAGE_SIZE,
    }), []);

    return (
        <div>
            <div className="admin-log-filters">
                <div className="filter-cell filter-cell-user">
                    <label>{t.JsErrorLog_Account()}</label>
                    <Combobox
                        options={accountOptions}
                        value={accountId}
                        onChange={setAccountId}
                        placeholder={allLabel}
                        searchPlaceholder={t.Admin_Log_Filter_SearchUser()}
                        emptyText={t.Admin_Log_Filter_NoMatches()}
                        testId="js-errors-account-filter"
                    />
                </div>
                <div className="filter-cell filter-cell-user">
                    <label>{t.JsErrorLog_Filter_File()}</label>
                    <Combobox
                        options={fileOptions}
                        value={fileFilter}
                        onChange={setFileFilter}
                        placeholder={allLabel}
                        searchPlaceholder={t.JsErrorLog_Filter_File()}
                        emptyText={t.Admin_Log_Filter_NoMatches()}
                        testId="js-errors-file-filter"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="js-errors-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                    <DateInput
                        id="js-errors-date-from"
                        className="text-sm"
                        value={dateFrom}
                        onChange={e => setDateFrom(e.target.value)}
                        data-test-id="js-errors-date-from"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="js-errors-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                    <DateInput
                        id="js-errors-date-to"
                        className="text-sm"
                        value={dateTo}
                        onChange={e => setDateTo(e.target.value)}
                        data-test-id="js-errors-date-to"
                    />
                </div>
                <div className="filter-actions">
                    {hasActiveFilter && (
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            onClick={resetAll}
                            data-test-id="js-errors-reset"
                            aria-label={t.Admin_Log_Filter_Reset()}
                            title={t.Admin_Log_Filter_Reset()}
                        >
                            ×
                        </button>
                    )}
                </div>
            </div>

            <AdminLogGrid
                pageUrl={pageUrl}
                initialData={initialData}
                config={config}
                extraParams={extraParams}
                rowKey={r => r.id}
                rowTestId={r => `js-errors-row-${r.id}`}
                emptyMessage={t.JsErrorLog_Empty()}
                onRowClick={row => setSelected(row)}
                renders={{
                    last_seen_at: r => <span className="text-muted text-xs whitespace-nowrap">{formatTs(r.last_seen_at)}</span>,
                    count:        r => <span className="font-mono text-sm">{r.count}</span>,
                    message:      r => <span className="text-sm truncate max-w-[400px] inline-block">{truncate(r.message, 120)}</span>,
                    file:         r => r.file
                        ? <span className="font-mono text-sm">{truncate(r.file, 60)}{r.line ? `:${r.line}` : ''}</span>
                        : null,
                    account:      r => r.account_id
                        ? <AdminUserLink id={r.account_id} name={r.account_name || `#${r.account_id}`} />
                        : <span className="text-muted">—</span>,
                }}
            />

            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <JsErrorLogDetail row={selected} />
                </LogDetailModal>
            )}
        </div>
    );
};
