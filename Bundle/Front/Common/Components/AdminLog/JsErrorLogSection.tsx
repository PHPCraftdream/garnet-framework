import * as React from 'react';
import {useState, useMemo} from 'react';
import {JsErrorEntry, GridConfig} from './types';
import {AdminLogGrid} from './AdminLogGrid';
import {AdminUserLink} from './AdminUserLink';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {LogDetailModal} from './LogDetailModal';
import {JsErrorLogDetail} from './details/JsErrorLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/pagination';

interface Props {
    logs: JsErrorEntry[];
}

const truncate = (s: string, n: number): string => (s.length > n ? s.slice(0, n) + '…' : s);

export const JsErrorLogSection: React.FC<Props> = ({logs}) => {
    const [selected, setSelected] = useState<JsErrorEntry | null>(null);
    const [accountId, setAccountId] = useState<string>('');
    const [fileFilter, setFileFilter] = useState<string>('');
    const [messageFilter, setMessageFilter] = useState<string>('');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');

    const allLabel = t.Admin_Log_Filter_All();

    const accountOptions = useMemo(() => {
        const map = new Map<string, string>();
        for (const r of logs) {
            if (r.account_id == null) continue;
            const id = String(r.account_id);
            if (!map.has(id)) map.set(id, r.account_name || `#${r.account_id}`);
        }
        const arr = Array.from(map.entries()).map(([value, label]) => ({value, label}));
        arr.sort((a, b) => a.label.localeCompare(b.label));
        return [{value: '', label: allLabel}, ...arr];
    }, [logs, allLabel]);

    const fileOptions = useMemo(() => {
        const set = new Set<string>();
        for (const r of logs) {
            if (r.file) set.add(r.file);
        }
        const arr = Array.from(set).toSorted((a, b) => a.localeCompare(b));
        return [{value: '', label: allLabel}, ...arr.map(f => ({value: f, label: f}))];
    }, [logs, allLabel]);

    const filtered = useMemo(() => {
        let res = logs;
        if (accountId) {
            const idNum = Number(accountId);
            res = res.filter(r => r.account_id === idNum);
        }
        if (fileFilter) {
            res = res.filter(r => r.file === fileFilter);
        }
        if (messageFilter) {
            const needle = messageFilter.toLowerCase();
            res = res.filter(r => r.message.toLowerCase().includes(needle));
        }
        if (dateFrom) {
            const tsFrom = Math.floor(new Date(dateFrom + 'T00:00:00').getTime() / 1000);
            res = res.filter(r => r.last_seen_at >= tsFrom);
        }
        if (dateTo) {
            const tsTo = Math.floor(new Date(dateTo + 'T23:59:59').getTime() / 1000);
            res = res.filter(r => r.last_seen_at <= tsTo);
        }
        return res;
    }, [logs, accountId, fileFilter, messageFilter, dateFrom, dateTo]);

    const resetAll = (): void => {
        setAccountId('');
        setFileFilter('');
        setMessageFilter('');
        setDateFrom('');
        setDateTo('');
    };

    const hasActiveFilter = !!(accountId || fileFilter || messageFilter || dateFrom || dateTo);

    const config: GridConfig = useMemo(() => ({
        columns: [
            {key: 'last_seen_at', label: t.JsErrorLog_LastSeen()},
            {key: 'count',        label: t.JsErrorLog_Count()},
            {key: 'message',      label: t.JsErrorLog_Message()},
            {key: 'file',         label: t.JsErrorLog_File()},
            {key: 'account',      label: t.JsErrorLog_Account()},
        ],
        searchFields: ['message', 'file', 'url', 'account_name'],
        sortFields: ['id', 'last_seen_at', 'first_seen_at', 'count'],
        pageSize: DEFAULT_PAGE_SIZE,
        subGrids: [],
        detailViews: [],
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
                    <label htmlFor="js-errors-message">{t.JsErrorLog_Filter_Message()}</label>
                    <input
                        id="js-errors-message"
                        type="text"
                        className="form-control text-sm"
                        value={messageFilter}
                        onChange={e => setMessageFilter(e.target.value)}
                        data-test-id="js-errors-message-filter"
                        placeholder={t.JsErrorLog_Filter_Message()}
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
                    <span className="filter-counter">{filtered.length} / {logs.length}</span>
                </div>
            </div>

            <AdminLogGrid
                rows={filtered}
                config={config}
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
