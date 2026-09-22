import * as React from 'react';
import {useState, useMemo} from 'react';
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

const statusBadge = (status: string): React.ReactNode => {
    const cls: Record<string, string> = {
        sent: 'status-success',
        failed: 'status-danger',
        skipped_dev: 'status-muted',
        pending: 'status-warning',
    };
    return <span className={`badge ${cls[status] ?? 'status-info'}`}>{status}</span>;
};

export const MailLogSection: React.FC<Props> = ({pageUrl, initialData, config, filterOptions}) => {
    const [statusFilter, setStatusFilter] = useState<MailStatus | 'all'>('all');
    const [userFilter, setUserFilter] = useState<string>('');
    const [typeFilter, setTypeFilter] = useState<string>('');
    const [selected, setSelected] = useState<MailLogEntry | null>(null);

    const userOptions = useMemo(() => {
        const out: {value: string; label: string}[] = [{value: '', label: t.Admin_MailLog_Filter_All()}];
        if (filterOptions.hasNoAccount) out.push({value: NO_ACCOUNT, label: t.Admin_MailLog_Filter_NoAccount()});
        out.push(...filterOptions.accounts.map(a => ({value: String(a.id), label: a.name})));
        return out;
    }, [filterOptions]);

    const extraParams = useMemo(() => ({
        status: statusFilter === 'all' ? '' : statusFilter,
        accountId: userFilter,
        mailType: typeFilter,
    }), [statusFilter, userFilter, typeFilter]);

    return (
        <div>
            <div className="admin-log-filters">
                <div className="filter-cell filter-cell-user">
                    <label>{t.Admin_MailLog_Filter_Recipient()}</label>
                    <Combobox
                        options={userOptions}
                        value={userFilter}
                        onChange={setUserFilter}
                        placeholder={t.Admin_MailLog_Filter_All()}
                        searchPlaceholder={t.Admin_MailLog_Filter_SearchUser()}
                        emptyText={t.Admin_MailLog_Filter_NoMatches()}
                        testId="mails-recipient-filter"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="mails-type">{t.Admin_MailLog_Filter_Type()}</label>
                    <select
                        id="mails-type"
                        className="form-select text-sm"
                        value={typeFilter}
                        onChange={e => setTypeFilter(e.target.value)}
                        data-test-id="mails-type-filter"
                    >
                        <option value="">{t.Admin_MailLog_Filter_All()}</option>
                        {filterOptions.types.map(tp => <option key={tp} value={tp}>{tp}</option>)}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="mails-status">{t.Admin_MailLog_Filter_Status()}</label>
                    <select
                        id="mails-status"
                        className="form-select text-sm"
                        value={statusFilter}
                        onChange={e => setStatusFilter(e.target.value as MailStatus | 'all')}
                        data-test-id="mails-status-filter"
                    >
                        <option value="all">{t.Admin_MailLog_Filter_All()}</option>
                        {STATUSES.map(s => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                {/* Subject text search folded into the grid's own generic
                    search box — config.searchFields already includes 'subject',
                    a second dedicated field here would just duplicate it. */}
            </div>
            <AdminLogGrid
                pageUrl={pageUrl}
                initialData={initialData}
                config={config}
                extraParams={extraParams}
                rowKey={r => r.id}
                rowTestId={r => `mails-row-${r.id}`}
                emptyMessage={t.Admin_MailLog_Empty()}
                onRowClick={row => setSelected(row)}
                renders={{
                    created_at: r => <span className="text-muted text-xs whitespace-nowrap">{formatTs(r.created_at)}</span>,
                    recipient_email: r => (
                        <span className="text-sm">
                            {r.recipient_email}
                            {r.account_id ? (
                                <span className="text-muted ml-1">
                                    (<AdminUserLink id={r.account_id} name={r.account_name || r.account_login || `#${r.account_id}`} />)
                                </span>
                            ) : null}
                        </span>
                    ),
                    mail_type: r => <span className="font-mono text-sm">{r.mail_type}</span>,
                    status: r => statusBadge(r.status),
                    error_log: r => r.error_log ? <span className="text-danger text-sm truncate max-w-[200px] inline-block">{r.error_log}</span> : null,
                }}
            />
            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <MailLogDetail row={selected} />
                </LogDetailModal>
            )}
        </div>
    );
};
