import * as React from 'react';
import {useState, useMemo} from 'react';
import {CronLogEntry, GridConfig} from './types';
import {AdminLogGrid} from './AdminLogGrid';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {LogDetailModal} from './LogDetailModal';
import {CronLogDetail} from './details/CronLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/pagination';

interface Props {
    logs: CronLogEntry[];
}

const statusBadge = (status: CronLogEntry['status']): React.ReactNode => {
    const cls: Record<CronLogEntry['status'], string> = {
        success: 'status-success',
        error: 'status-danger',
        running: 'status-warning',
    };
    return <span className={`badge ${cls[status]}`}>{status}</span>;
};

const formatDuration = (ms: number): string => {
    if (ms < 1000) return `${ms} ms`;
    const sec = ms / 1000;
    if (sec < 60) return `${sec.toFixed(2)} s`;
    const min = Math.floor(sec / 60);
    const restSec = (sec - min * 60).toFixed(0);
    return `${min}m ${restSec}s`;
};

const truncate = (s: string, n: number): string => (s.length > n ? s.slice(0, n) + '…' : s);

export const CronLogSection: React.FC<Props> = ({logs}) => {
    const [selected, setSelected] = useState<CronLogEntry | null>(null);
    const [taskFilter, setTaskFilter] = useState<string>('');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');

    const taskOptions = useMemo(() => {
        const set = new Set<string>();
        for (const r of logs) {
            if (r.task_name) set.add(r.task_name);
        }
        return Array.from(set).toSorted((a, b) => a.localeCompare(b));
    }, [logs]);

    const filtered = useMemo(() => {
        let res = logs;
        if (taskFilter) res = res.filter(r => r.task_name === taskFilter);
        if (dateFrom) {
            const tsFrom = Math.floor(new Date(dateFrom + 'T00:00:00Z').getTime() / 1000);
            res = res.filter(r => r.started_at >= tsFrom);
        }
        if (dateTo) {
            const tsTo = Math.floor(new Date(dateTo + 'T23:59:59Z').getTime() / 1000);
            res = res.filter(r => r.started_at <= tsTo);
        }
        return res;
    }, [logs, taskFilter, dateFrom, dateTo]);

    const resetAll = (): void => {
        setTaskFilter('');
        setDateFrom('');
        setDateTo('');
    };

    const hasActiveFilter = !!(taskFilter || dateFrom || dateTo);

    const config: GridConfig = useMemo(() => ({
        columns: [
            {key: 'started_at', label: t.CronLog_Date()},
            {key: 'task_name', label: t.CronLog_Task()},
            {key: 'status', label: t.CronLog_Status()},
            {key: 'duration_ms', label: t.CronLog_Duration()},
            {key: 'output', label: t.CronLog_Output()},
            {key: 'error_message', label: t.CronLog_Error()},
        ],
        searchFields: ['task_name', 'status', 'output', 'error_message'],
        sortFields: ['id', 'started_at', 'duration_ms', 'task_name', 'status'],
        pageSize: DEFAULT_PAGE_SIZE,
        subGrids: [],
        detailViews: [],
    }), []);

    return (
        <div>
            <div className="admin-log-filters">
                <div className="filter-cell">
                    <label htmlFor="cron-task">{t.CronLog_Filter_Task()}</label>
                    <select
                        id="cron-task"
                        className="form-select text-sm"
                        value={taskFilter}
                        onChange={e => setTaskFilter(e.target.value)}
                        data-test-id="cron-task-filter"
                    >
                        <option value="">{t.CronLog_Filter_All()}</option>
                        {taskOptions.map(name => <option key={name} value={name}>{name}</option>)}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="cron-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                    <DateInput
                        id="cron-date-from"
                        className="text-sm"
                        value={dateFrom}
                        onChange={e => setDateFrom(e.target.value)}
                        data-test-id="cron-date-from"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="cron-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                    <DateInput
                        id="cron-date-to"
                        className="text-sm"
                        value={dateTo}
                        onChange={e => setDateTo(e.target.value)}
                        data-test-id="cron-date-to"
                    />
                </div>
                <div className="filter-actions">
                    {hasActiveFilter && (
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            onClick={resetAll}
                            data-test-id="cron-reset"
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
                rowTestId={r => `cron-row-${r.id}`}
                emptyMessage={t.CronLog_Empty()}
                onRowClick={row => setSelected(row)}
                renders={{
                    started_at: r => <span className="text-muted text-xs whitespace-nowrap">{formatTs(r.started_at)}</span>,
                    task_name: r => <span className="font-mono text-sm">{r.task_name}</span>,
                    status: r => statusBadge(r.status),
                    duration_ms: r => <span className="font-mono text-sm">{formatDuration(r.duration_ms)}</span>,
                    output: r => r.output
                        ? <span className="text-sm truncate max-w-[300px] inline-block">{truncate(r.output, 80)}</span>
                        : null,
                    error_message: r => r.error_message
                        ? <span className="text-danger text-sm truncate max-w-[200px] inline-block">{truncate(r.error_message, 60)}</span>
                        : null,
                }}
            />

            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <CronLogDetail row={selected} />
                </LogDetailModal>
            )}
        </div>
    );
};
