import * as React from 'react';
import {AdminUserLink} from '../AdminUserLink';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

export interface RequestLogRow {
    ts: string;
    log_ts?: string;
    method: string;
    uri: string;
    status: number;
    duration_ms: number;
    account_id: number | null;
    ip: string;
    ua: string;
}

interface Props {
    row: RequestLogRow;
    accounts: Record<string, string>;
}

const statusBadgeClass = (status: number): string => {
    if (status >= 500) return 'status-danger';
    if (status >= 400) return 'status-warning';
    if (status >= 300) return 'status-info';
    return 'status-success';
};

export const RequestLogDetail: React.FC<Props> = ({row, accounts}) => (
    <div data-test-id="log-detail-requests">
        <div className="log-detail-grid">
            <div className="log-detail-label">{t.RequestLog_Time()}</div>
            <div className="log-detail-value font-mono">{row.ts}</div>

            <div className="log-detail-label">{t.RequestLog_Method()}</div>
            <div className="log-detail-value font-mono">{row.method}</div>

            <div className="log-detail-label">{t.RequestLog_Status()}</div>
            <div className="log-detail-value">
                <span className={`badge ${statusBadgeClass(row.status)}`}>{row.status}</span>
            </div>

            <div className="log-detail-label">{t.RequestLog_Uri()}</div>
            <div className="log-detail-value font-mono break-all">{row.uri}</div>

            <div className="log-detail-label">{t.RequestLog_Duration()}</div>
            <div className="log-detail-value">{row.duration_ms}</div>

            <div className="log-detail-label">{t.Logs_Requests_ColumnUser()}</div>
            <div className="log-detail-value">
                {row.account_id != null ? (
                    <AdminUserLink
                        id={row.account_id}
                        name={accounts[String(row.account_id)] ?? `#${row.account_id}`}
                    />
                ) : (
                    <span className="text-muted">{t.Logs_Requests_Guest()}</span>
                )}
            </div>

            <div className="log-detail-label">{t.RequestLog_Ip()}</div>
            <div className="log-detail-value font-mono">{row.ip}</div>

            <div className="log-detail-label">{t.RequestLog_Ua()}</div>
            <div className="log-detail-value whitespace-pre-wrap break-all">{row.ua}</div>
        </div>

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.LogDetail_RawJson()}</div>
            <pre className="log-detail-pre">{JSON.stringify(row, null, 2)}</pre>
        </div>
    </div>
);
