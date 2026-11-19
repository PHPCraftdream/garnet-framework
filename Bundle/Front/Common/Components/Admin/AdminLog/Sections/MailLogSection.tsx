import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {MailLogEntry, MailsFilterOptions, GridConfig} from '../types';
import {AdminLogGrid} from '../AdminLogGrid';
import {PageResponse} from '@common/hooks/data/usePagination';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {LogDetailModal} from '../LogDetailModal';
import {MailLogDetail} from '../details/MailLogDetail';
import {Combobox} from '@common/Components/ui/Combobox';
import {AdminUserLink} from '../AdminUserLink';

interface Props {
    pageUrl: string;
    initialData: PageResponse<MailLogEntry> | null;
    config: GridConfig;
    filterOptions: MailsFilterOptions;
}

const STATUSES = ['sent', 'failed', 'skipped_dev', 'pending'] as const;
type MailStatus = typeof STATUSES[number];
const NO_ACCOUNT = '__no_account__';

interface Filters {
    statusFilter: MailStatus | 'all';
    userFilter: string;
    typeFilter: string;
}

const emptyFilters = (): Filters => ({statusFilter: 'all', userFilter: '', typeFilter: ''});
const rowKey = (row: MailLogEntry) => row.id;
const rowTestId = (row: MailLogEntry) => `mails-row-${row.id}`;

function option(value: string) {
    return <option key={value} value={value}>{value}</option>;
}

function accountOption(account: MailsFilterOptions['accounts'][number]) {
    return {value: String(account.id), label: account.name};
}

function statusBadge(status: string): React.ReactNode {
    if (status === 'sent') return <span className="admin-log-section-status-success">{status}</span>;
    if (status === 'failed') return <span className="admin-log-section-status-danger">{status}</span>;
    if (status === 'skipped_dev') return <span className="admin-log-section-status-muted">{status}</span>;
    if (status === 'pending') return <span className="admin-log-section-status-warning">{status}</span>;
    return <span className="admin-log-section-status-info">{status}</span>;
}

const renders = {
    created_at: (row: MailLogEntry) => <span className="admin-log-section-time">{formatTs(row.created_at)}</span>,
    recipient_email: (row: MailLogEntry) => <span className="admin-log-section-recipient">
        {row.recipient_email}
        {row.account_id ? <span className="admin-log-section-recipient-account">
            (<AdminUserLink id={row.account_id} name={row.account_name || row.account_login || `#${row.account_id}`} />)
        </span> : null}
    </span>,
    mail_type: (row: MailLogEntry) => <span className="admin-log-section-mono">{row.mail_type}</span>,
    status: (row: MailLogEntry) => statusBadge(row.status),
    error_log: (row: MailLogEntry) => row.error_log ? <span className="admin-log-section-error">{row.error_log}</span> : null,
};

export class MailLogSection extends AntiHookComponent<Props> {
    private readonly filters = new Carburetor<Filters>(emptyFilters());
    private readonly selection = new Carburetor<{selected: MailLogEntry | null}>({selected: null});
    private readonly selected = this.connectSelection(this.selection, state => state.selected);
    private lastFilters: Filters | null = null;
    private extraParams: Record<string, unknown> = {};

    private patch(patch: Partial<Filters>): void {
        this.filters.setData({...this.filters.getData(), ...patch});
    }

    private readonly changeUser = (value: string) => this.patch({userFilter: value});
    private readonly changeType = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({typeFilter: event.target.value});
    private readonly changeStatus = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({statusFilter: event.target.value as MailStatus | 'all'});
    private readonly selectRow = (selected: MailLogEntry) => this.selection.setData({selected});
    private readonly closeDetail = () => this.selection.setData({selected: null});

    private params(filters: Filters): Record<string, unknown> {
        const last = this.lastFilters;
        if (!last || last.statusFilter !== filters.statusFilter || last.userFilter !== filters.userFilter || last.typeFilter !== filters.typeFilter) {
            this.lastFilters = {...filters};
            this.extraParams = {
                status: filters.statusFilter === 'all' ? '' : filters.statusFilter,
                accountId: filters.userFilter,
                mailType: filters.typeFilter,
            };
        }
        return this.extraParams;
    }

    private renderFilters(filters: Filters): React.ReactNode {
        const {filterOptions} = this.props;
        const userOptions = [{value: '', label: t.Admin_MailLog_Filter_All()}];
        if (filterOptions.hasNoAccount) userOptions.push({value: NO_ACCOUNT, label: t.Admin_MailLog_Filter_NoAccount()});
        userOptions.push(...filterOptions.accounts.map(accountOption));
        return <div className="admin-log-filters">
            <div className="admin-log-person-filter">
                <label>{t.Admin_MailLog_Filter_Recipient()}</label>
                <Combobox options={userOptions} value={filters.userFilter} onChange={this.changeUser}
                    placeholder={t.Admin_MailLog_Filter_All()} searchPlaceholder={t.Admin_MailLog_Filter_SearchUser()}
                    emptyText={t.Admin_MailLog_Filter_NoMatches()} testId="mails-recipient-filter" />
            </div>
            <div className="filter-cell">
                <label htmlFor="mails-type">{t.Admin_MailLog_Filter_Type()}</label>
                <select id="mails-type" className="admin-log-section-select" value={filters.typeFilter} onChange={this.changeType} data-test-id="mails-type-filter">
                    <option value="">{t.Admin_MailLog_Filter_All()}</option>
                    {filterOptions.types.map(option)}
                </select>
            </div>
            <div className="filter-cell">
                <label htmlFor="mails-status">{t.Admin_MailLog_Filter_Status()}</label>
                <select id="mails-status" className="admin-log-section-select" value={filters.statusFilter} onChange={this.changeStatus} data-test-id="mails-status-filter">
                    <option value="all">{t.Admin_MailLog_Filter_All()}</option>
                    {STATUSES.map(option)}
                </select>
            </div>
        </div>;
    }

    public render() {
        const filters = this.useCarburetor(this.filters);
        const selected = this.selected();
        const {pageUrl, initialData, config} = this.props;
        return <div>
            {this.renderFilters(filters)}
            <AdminLogGrid pageUrl={pageUrl} initialData={initialData} config={config} extraParams={this.params(filters)}
                rowKey={rowKey} rowTestId={rowTestId} emptyMessage={t.Admin_MailLog_Empty()} onRowClick={this.selectRow} renders={renders} />
            {selected && <LogDetailModal onClose={this.closeDetail}><MailLogDetail row={selected} /></LogDetailModal>}
        </div>;
    }
}
