import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {CronLogEntry, CronFilterOptions, GridConfig} from '@common/Components/Admin/AdminLog/types';
import {AdminLogGrid} from '@common/Components/Admin/AdminLog/AdminLogGrid';
import {PageResponse} from '@common/hooks/data/usePagination';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/Time/DateUtils/format/formatTs';
import {LogDetailModal} from '@common/Components/Admin/AdminLog/LogDetailModal';
import {CronLogDetail} from '@common/Components/Admin/AdminLog/details/CronLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/Data/pagination/DEFAULT_PAGE_SIZE';

interface Props {
    pageUrl: string;
    initialData: PageResponse<CronLogEntry> | null;
    filterOptions: CronFilterOptions;
}

interface Filters {
    taskFilter: string;
    dateFrom: string;
    dateTo: string;
}

const emptyFilters = (): Filters => ({taskFilter: '', dateFrom: '', dateTo: ''});

/** Handles formatDuration. */
function formatDuration(ms: number): string {
    if (ms < 1000) return `${ms} ms`;
    const sec = ms / 1000;
    if (sec < 60) return `${sec.toFixed(2)} s`;
    const min = Math.floor(sec / 60);
    const restSec = (sec - min * 60).toFixed(0);
    return `${min}m ${restSec}s`;
}

const truncate = (s: string, n: number): string => (s.length > n ? s.slice(0, n) + '…' : s);
const rowKey = (row: CronLogEntry) => row.id;
const rowTestId = (row: CronLogEntry) => `cron-row-${row.id}`;

/** Handles taskOption. */
function taskOption(name: string) {
    return <option key={name} value={name}>{name}</option>;
}

const renders = {
    started_at: (row: CronLogEntry) => <span className="admin-log-section-time">{formatTs(row.started_at)}</span>,
    task_name: (row: CronLogEntry) => <span className="admin-log-section-mono">{row.task_name}</span>,
    status: (row: CronLogEntry) => <span className={row.status === 'success' ?
        'admin-log-section-status-success' : row.status === 'error' ? 'admin-log-section-status-danger' :
        'admin-log-section-status-warning'}>{row.status}</span>,
    duration_ms: (row: CronLogEntry) => <span
        className="admin-log-section-mono">{formatDuration(row.duration_ms)}</span>,
    output: (row: CronLogEntry) => row.output ? <span
        className="admin-log-section-output">{truncate(row.output, 80)}</span> : null,
    error_message: (row: CronLogEntry) => row.error_message ? <span
        className="admin-log-section-error">{truncate(row.error_message, 60)}</span> : null,
};

export class CronLogSection extends AntiHookComponent<Props> {
    private readonly filters = new Carburetor<Filters>(emptyFilters());
    private readonly selection = new Carburetor<{selected: CronLogEntry | null}>({selected: null});
    private readonly selected = this.connectSelection(this.selection, state => state.selected);
    private lastFilters: Filters | null = null;
    private extraParams: Record<string, unknown> = {};
    private readonly config: GridConfig = {
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
    };

    /** Handles patch. */
    private patch(patch: Partial<Filters>): void {
        this.filters.setData({...this.filters.getData(), ...patch});
    }

    /** Handles changeTask. */
    private readonly changeTask = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({taskFilter:
        event.target.value});
    /** Handles changeFrom. */
    private readonly changeFrom = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateFrom:
        event.target.value});
    /** Handles changeTo. */
    private readonly changeTo = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateTo:
        event.target.value});
    /** Handles resetAll. */
    private readonly resetAll = () => this.filters.setData(emptyFilters());
    /** Handles selectRow. */
    private readonly selectRow = (selected: CronLogEntry) => this.selection.setData({selected});
    /** Handles closeDetail. */
    private readonly closeDetail = () => this.selection.setData({selected: null});

    /** Handles params. */
    private params(filters: Filters): Record<string, unknown> {
        const last = this.lastFilters;
        if (!last || last.taskFilter !== filters.taskFilter || last.dateFrom !== filters.dateFrom ||
            last.dateTo !== filters.dateTo) {
            this.lastFilters = {...filters};
            this.extraParams = {
                taskName: filters.taskFilter,
                dateFrom: filters.dateFrom ? Math.floor(new Date(filters.dateFrom + 'T00:00:00Z').getTime() / 1000) : 0,
                dateTo: filters.dateTo ? Math.floor(new Date(filters.dateTo + 'T23:59:59Z').getTime() / 1000) : 0,
            };
        }
        return this.extraParams;
    }

    /** Handles renderFilters. */
    private renderFilters(filters: Filters): React.ReactNode {
        const hasActiveFilter = !!(filters.taskFilter || filters.dateFrom || filters.dateTo);
        return <div className="admin-log-filters">
            <div className="filter-cell">
                <label htmlFor="cron-task">{t.CronLog_Filter_Task()}</label>
                <select id="cron-task" className="admin-log-section-select" value={filters.taskFilter}
                    onChange={this.changeTask} data-test-id="cron-task-filter">
                    <option value="">{t.CronLog_Filter_All()}</option>
                    {this.props.filterOptions.taskNames.map(taskOption)}
                </select>
            </div>
            <div className="filter-cell">
                <label htmlFor="cron-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                <DateInput id="cron-date-from" value={filters.dateFrom} onChange={this.changeFrom}
                    data-test-id="cron-date-from" />
            </div>
            <div className="filter-cell">
                <label htmlFor="cron-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                <DateInput id="cron-date-to" value={filters.dateTo} onChange={this.changeTo}
                    data-test-id="cron-date-to" />
            </div>
            <div className="filter-actions">
                {hasActiveFilter && <button type="button" className="admin-log-section-reset" onClick={this.resetAll}
                    data-test-id="cron-reset" aria-label={t.Admin_Log_Filter_Reset()}
                        title={t.Admin_Log_Filter_Reset()}>×</button>}
            </div>
        </div>;
    }

    /** Handles render. */
    public render() {
        const filters = this.useCarburetor(this.filters);
        const selected = this.selected();
        const {pageUrl, initialData} = this.props;
        return <div>
            {this.renderFilters(filters)}
            <AdminLogGrid pageUrl={pageUrl} initialData={initialData} config={this.config}
                extraParams={this.params(filters)}
                rowKey={rowKey} rowTestId={rowTestId} emptyMessage={t.CronLog_Empty()}
                    onRowClick={this.selectRow} renders={renders} />
            {selected && <LogDetailModal onClose={this.closeDetail}><CronLogDetail row={selected} /></LogDetailModal>}
        </div>;
    }
}
