import * as React from 'react';
import {useState, useMemo} from 'react';
import {MailLogEntry, GridConfig} from './types';
import {AdminLogGrid} from './AdminLogGrid';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {LogDetailModal} from './LogDetailModal';
import {MailLogDetail} from './details/MailLogDetail';
import {Combobox} from '@common/Components/ui/Combobox';
import {AdminUserLink} from './AdminUserLink';

interface Props {
    logs: MailLogEntry[];
    config: GridConfig;
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

export const MailLogSection: React.FC<Props> = ({logs, config}) => {
    const [statusFilter, setStatusFilter] = useState<MailStatus | 'all'>('all');
    const [userFilter, setUserFilter] = useState<string>('');
    const [typeFilter, setTypeFilter] = useState<string>('');
    const [subjectFilter, setSubjectFilter] = useState('');
    const [selected, setSelected] = useState<MailLogEntry | null>(null);

    const statusCounts = useMemo(() => {
        const counts: Partial<Record<MailStatus, number>> = {};
        for (const log of logs) {
            if (STATUSES.includes(log.status as MailStatus)) {
                counts[log.status as MailStatus] = (counts[log.status as MailStatus] || 0) + 1;
            }
        }
        return counts;
    }, [logs]);

    const userOptions = useMemo(() => {
        const map = new Map<string, string>();
        let hasNoAccount = false;
        for (const r of logs) {
            if (r.account_id === null || r.account_id === undefined) {
                hasNoAccount = true;
                continue;
            }
            const id = String(r.account_id);
            if (!map.has(id)) {
                const label = r.account_name || r.account_login || r.recipient_email || `#${r.account_id}`;
                map.set(id, label);
            }
        }
        const arr = Array.from(map.entries()).map(([value, label]) => ({value, label}));
        arr.sort((a, b) => a.label.localeCompare(b.label));
        const out: {value: string; label: string}[] = [{value: '', label: t.Admin_MailLog_Filter_All()}];
        if (hasNoAccount) out.push({value: NO_ACCOUNT, label: t.Admin_MailLog_Filter_NoAccount()});
        out.push(...arr);
        return out;
    }, [logs]);

    const typeOptions = useMemo(() => {
        const set = new Set<string>();
        for (const r of logs) {
            if (r.mail_type) set.add(r.mail_type);
        }
        return Array.from(set).toSorted((a, b) => a.localeCompare(b));
    }, [logs]);

    const filteredLogs = useMemo(() => {
        let res = logs;
        if (statusFilter !== 'all') res = res.filter(l => l.status === statusFilter);
        if (userFilter) {
            if (userFilter === NO_ACCOUNT) res = res.filter(l => l.account_id == null);
            else res = res.filter(l => String(l.account_id ?? '') === userFilter);
        }
        if (typeFilter) res = res.filter(l => l.mail_type === typeFilter);
        if (subjectFilter.trim()) {
            const q = subjectFilter.trim().toLowerCase();
            res = res.filter(l => (l.subject ?? '').toLowerCase().includes(q));
        }
        return res;
    }, [logs, statusFilter, userFilter, typeFilter, subjectFilter]);

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
                        {typeOptions.map(tp => <option key={tp} value={tp}>{tp}</option>)}
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
                        {STATUSES.map(s => (statusCounts[s] || 0) > 0 && (
                            <option key={s} value={s}>{s} ({statusCounts[s]})</option>
                        ))}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="mails-subject">{t.Admin_MailLog_Filter_Subject()}</label>
                    <input
                        id="mails-subject"
                        type="text"
                        className="form-control text-sm"
                        value={subjectFilter}
                        onChange={e => setSubjectFilter(e.target.value)}
                        data-test-id="mails-subject-filter"
                    />
                </div>
                <div className="filter-actions">
                    <span className="filter-counter">{filteredLogs.length} / {logs.length}</span>
                </div>
            </div>
            <AdminLogGrid
                rows={filteredLogs}
                config={config}
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
