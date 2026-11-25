import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {JsErrorEntry, JsErrorsFilterOptions, GridConfig} from '@common/Components/Admin/AdminLog/types';
import {AdminLogGrid} from '@common/Components/Admin/AdminLog/AdminLogGrid';
import {PageResponse} from '@common/hooks/data/usePagination';
import {AdminUserLink} from '@common/Components/Admin/AdminLog/AdminUserLink';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/Time/DateUtils/format/formatTs';
import {LogDetailModal} from '@common/Components/Admin/AdminLog/LogDetailModal';
import {JsErrorLogDetail} from '@common/Components/Admin/AdminLog/details/JsErrorLogDetail';
import {DEFAULT_PAGE_SIZE} from '@common/Utils/Data/pagination/DEFAULT_PAGE_SIZE';

interface Props {
    pageUrl: string;
    initialData: PageResponse<JsErrorEntry> | null;
    filterOptions: JsErrorsFilterOptions;
}

interface Filters {
    accountId: string;
    fileFilter: string;
    dateFrom: string;
    dateTo: string;
}

const emptyFilters = (): Filters => ({accountId: '', fileFilter: '', dateFrom: '', dateTo: ''});
const truncate = (s: string, n: number): string => (s.length > n ? s.slice(0, n) + '…' : s);
const rowKey = (row: JsErrorEntry) => row.id;
const rowTestId = (row: JsErrorEntry) => `js-errors-row-${row.id}`;

/** Handles accountOption. */
function accountOption(account: JsErrorsFilterOptions['accounts'][number]) {
    return {value: String(account.id), label: account.name};
}

/** Handles fileOption. */
function fileOption(file: string) {
    return {value: file, label: file};
}

const renders = {
    last_seen_at: (row: JsErrorEntry) => <span className="admin-log-section-time">{formatTs(row.last_seen_at)}</span>,
    count: (row: JsErrorEntry) => <span className="admin-log-section-mono">{row.count}</span>,
    message: (row: JsErrorEntry) => <span className="admin-log-section-message">{truncate(row.message, 120)}</span>,
    file: (row: JsErrorEntry) => row.file ? <span className="admin-log-section-mono">{truncate(row.file,
        60)}{row.line ? `:${row.line}` : ''}</span> : null,
    account: (row: JsErrorEntry) => row.account_id
        ? <AdminUserLink id={row.account_id} name={row.account_name || `#${row.account_id}`} />
        : <span className="admin-log-section-muted">—</span>,
};

export class JsErrorLogSection extends AntiHookComponent<Props> {
    private readonly filters = new Carburetor<Filters>(emptyFilters());
    private readonly selection = new Carburetor<{selected: JsErrorEntry | null}>({selected: null});
    private readonly selected = this.connectSelection(this.selection, state => state.selected);
    private lastFilters: Filters | null = null;
    private extraParams: Record<string, unknown> = {};
    private readonly config: GridConfig = {
        columns: [
            {key: 'last_seen_at', label: t.JsErrorLog_LastSeen()},
            {key: 'count', label: t.JsErrorLog_Count()},
            {key: 'message', label: t.JsErrorLog_Message()},
            {key: 'file', label: t.JsErrorLog_File()},
            {key: 'account', label: t.JsErrorLog_Account()},
        ],
        searchFields: ['message', 'file', 'url'],
        sortFields: ['id', 'last_seen_at', 'first_seen_at', 'count'],
        pageSize: DEFAULT_PAGE_SIZE,
    };

    /** Handles patch. */
    private patch(patch: Partial<Filters>): void {
        this.filters.setData({...this.filters.getData(), ...patch});
    }

    /** Handles changeAccount. */
    private readonly changeAccount = (value: string) => this.patch({accountId: value});
    /** Handles changeFile. */
    private readonly changeFile = (value: string) => this.patch({fileFilter: value});
    /** Handles changeFrom. */
    private readonly changeFrom = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateFrom:
        event.target.value});
    /** Handles changeTo. */
    private readonly changeTo = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateTo:
        event.target.value});
    /** Handles resetAll. */
    private readonly resetAll = () => this.filters.setData(emptyFilters());
    /** Handles selectRow. */
    private readonly selectRow = (selected: JsErrorEntry) => this.selection.setData({selected});
    /** Handles closeDetail. */
    private readonly closeDetail = () => this.selection.setData({selected: null});

    /** Handles params. */
    private params(filters: Filters): Record<string, unknown> {
        const last = this.lastFilters;
        if (!last || last.accountId !== filters.accountId || last.fileFilter !== filters.fileFilter ||
            last.dateFrom !== filters.dateFrom || last.dateTo !== filters.dateTo) {
            this.lastFilters = {...filters};
            this.extraParams = {
                accountId: filters.accountId ? Number(filters.accountId) : 0,
                file: filters.fileFilter,
                dateFrom: filters.dateFrom ? Math.floor(new Date(filters.dateFrom + 'T00:00:00').getTime() / 1000) : 0,
                dateTo: filters.dateTo ? Math.floor(new Date(filters.dateTo + 'T23:59:59').getTime() / 1000) : 0,
            };
        }
        return this.extraParams;
    }

    /** Handles renderFilters. */
    private renderFilters(filters: Filters): React.ReactNode {
        const {filterOptions} = this.props;
        const allLabel = t.Admin_Log_Filter_All();
        const accountOptions = [{value: '', label: allLabel}, ...filterOptions.accounts.map(accountOption)];
        const fileOptions = [{value: '', label: allLabel}, ...filterOptions.files.map(fileOption)];
        const hasActiveFilter = !!(filters.accountId || filters.fileFilter || filters.dateFrom || filters.dateTo);
        return <div className="admin-log-filters">
            <div className="admin-log-person-filter">
                <label>{t.JsErrorLog_Account()}</label>
                <Combobox options={accountOptions} value={filters.accountId} onChange={this.changeAccount}
                    placeholder={allLabel}
                    searchPlaceholder={t.Admin_Log_Filter_SearchUser()}
                        emptyText={t.Admin_Log_Filter_NoMatches()} testId="js-errors-account-filter" />
            </div>
            <div className="admin-log-person-filter">
                <label>{t.JsErrorLog_Filter_File()}</label>
                <Combobox options={fileOptions} value={filters.fileFilter} onChange={this.changeFile}
                    placeholder={allLabel}
                    searchPlaceholder={t.JsErrorLog_Filter_File()} emptyText={t.Admin_Log_Filter_NoMatches()}
                        testId="js-errors-file-filter" />
            </div>
            <div className="filter-cell">
                <label htmlFor="js-errors-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                <DateInput id="js-errors-date-from" value={filters.dateFrom} onChange={this.changeFrom}
                    data-test-id="js-errors-date-from" />
            </div>
            <div className="filter-cell">
                <label htmlFor="js-errors-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                <DateInput id="js-errors-date-to" value={filters.dateTo} onChange={this.changeTo}
                    data-test-id="js-errors-date-to" />
            </div>
            <div className="filter-actions">
                {hasActiveFilter && <button type="button" className="admin-log-section-reset" onClick={this.resetAll}
                    data-test-id="js-errors-reset" aria-label={t.Admin_Log_Filter_Reset()}
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
                rowKey={rowKey} rowTestId={rowTestId} emptyMessage={t.JsErrorLog_Empty()}
                    onRowClick={this.selectRow} renders={renders} />
            {selected && <LogDetailModal onClose={this.closeDetail}><JsErrorLogDetail row={selected}
                /></LogDetailModal>}
        </div>;
    }
}
