import * as React from 'react';
import {JsErrorEntry} from '../types';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {AdminUserLink} from '../AdminUserLink';

interface Props {
    row: JsErrorEntry;
}

export const JsErrorLogDetail: React.FC<Props> = ({row}) => (
    <div data-test-id="log-detail-js-errors">
        <div className="log-detail-grid">
            <div className="log-detail-label">{t.LogDetail_Id()}</div>
            <div className="log-detail-value font-mono">{row.id}</div>

            <div className="log-detail-label">{t.JsErrorLog_LastSeen()}</div>
            <div className="log-detail-value">{formatTs(row.last_seen_at)}</div>

            <div className="log-detail-label">{t.JsErrorLog_Count()}</div>
            <div className="log-detail-value font-mono">{row.count}</div>

            <div className="log-detail-label">{t.JsErrorLog_Message()}</div>
            <div className="log-detail-value">{row.message}</div>

            <div className="log-detail-label">{t.JsErrorLog_File()}</div>
            <div className="log-detail-value font-mono">
                {row.file ? `${row.file}:${row.line}:${row.col}` : '—'}
            </div>

            <div className="log-detail-label">{t.JsErrorLog_Url()}</div>
            <div className="log-detail-value font-mono break-all">{row.url ?? '—'}</div>

            <div className="log-detail-label">{t.JsErrorLog_UserAgent()}</div>
            <div className="log-detail-value font-mono break-all">{row.user_agent ?? '—'}</div>

            <div className="log-detail-label">{t.JsErrorLog_Account()}</div>
            <div className="log-detail-value">
                {row.account_id ? (
                    <AdminUserLink id={row.account_id} name={row.account_name || `#${row.account_id}`} />
                ) : '—'}
            </div>
        </div>

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.JsErrorLog_Stack()}</div>
            <pre className="log-detail-pre">{row.stack && row.stack.length > 0 ? row.stack : '—'}</pre>
        </div>
    </div>
);
