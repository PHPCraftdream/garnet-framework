import * as React from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

export interface ErrorRow {
    ts: string;
    name: string;
    hash: string;
    message: string;
    file: string;
}

interface Props {
    row: ErrorRow;
}

export const ErrorLogDetail: React.FC<Props> = ({row}) => (
    <div data-test-id="log-detail-errors">
        <div className="log-detail-grid">
            <div className="log-detail-label">{t.RequestLog_Time()}</div>
            <div className="log-detail-value font-mono">{row.ts}</div>

            <div className="log-detail-label">{t.Logs_Errors_File()}</div>
            <div className="log-detail-value font-mono break-all">{row.file || '—'}</div>

            <div className="log-detail-label">{t.Logs_Errors_Name()}</div>
            <div className="log-detail-value font-mono break-all">{row.name}</div>
        </div>

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.Logs_Errors_Message()}</div>
            <pre className="log-detail-pre">{row.message}</pre>
        </div>
    </div>
);
