import * as React from 'react';
import {MailLogEntry} from '../types';
import {AdminUserLink} from '../AdminUserLink';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';

interface Props {
    row: MailLogEntry;
}

const statusBadge = (status: string): React.ReactNode => {
    const cls: Record<string, string> = {
        sent: 'status-success',
        failed: 'status-danger',
        skipped_dev: 'status-muted',
        pending: 'status-warning',
    };
    return <span className={`badge ${cls[status] ?? 'status-info'}`}>{status}</span>;
};

export const MailLogDetail: React.FC<Props> = ({row}) => {
    let metaParsed: Record<string, unknown> | null = null;
    if (row.meta) {
        try { metaParsed = JSON.parse(row.meta); } catch { /* ignore */ }
    }

    return (
        <div data-test-id="log-detail-mails">
            <div className="log-detail-grid">
                <div className="log-detail-label">{t.LogDetail_Id()}</div>
                <div className="log-detail-value font-mono">{row.id}</div>

                <div className="log-detail-label">{t.Admin_MailLog_Date()}</div>
                <div className="log-detail-value">{formatTs(row.created_at)}</div>

                <div className="log-detail-label">{t.Admin_MailLog_Recipient()}</div>
                <div className="log-detail-value">
                    <span className="font-mono">{row.recipient_email}</span>
                    {row.account_id != null && row.account_id > 0 && (
                        <span className="ml-2">
                            <AdminUserLink
                                id={row.account_id}
                                name={row.account_name || row.account_login || `#${row.account_id}`}
                            />
                        </span>
                    )}
                </div>

                <div className="log-detail-label">{t.Admin_MailLog_Type()}</div>
                <div className="log-detail-value font-mono">{row.mail_type}</div>

                <div className="log-detail-label">{t.Admin_MailLog_Subject()}</div>
                <div className="log-detail-value">{row.subject || '—'}</div>

                <div className="log-detail-label">{t.Admin_MailLog_Status()}</div>
                <div className="log-detail-value">{statusBadge(row.status)}</div>

                {row.error_log && (
                    <>
                        <div className="log-detail-label">{t.Admin_MailLog_Error()}</div>
                        <div className="log-detail-value text-danger whitespace-pre-wrap">{row.error_log}</div>
                    </>
                )}
            </div>

            {metaParsed && (
                <div className="log-detail-section">
                    <div className="log-detail-section-title">{t.LogDetail_Meta()}</div>
                    <pre className="log-detail-pre" data-test-id={`mail-meta-${row.id}`}>
                        {JSON.stringify(metaParsed, null, 2)}
                    </pre>
                </div>
            )}

            {row.body_html && (
                <div className="log-detail-section">
                    <div className="log-detail-section-title">{t.Admin_MailLog_Body()}</div>
                    <iframe
                        srcDoc={row.body_html}
                        className="log-detail-iframe"
                        data-test-id={`mail-body-${row.id}`}
                        title={t.Admin_MailLog_Body()}
                        sandbox=""
                    />
                </div>
            )}
        </div>
    );
};
