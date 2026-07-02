import * as React from 'react';
import {CronLogEntry} from '../types';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';

interface Props {
    row: CronLogEntry;
}

export const CronLogDetail: React.FC<Props> = ({row}) => (
    <div data-test-id="log-detail-cron">
        <div className="log-detail-grid">
            <div className="log-detail-label">{t.LogDetail_Id()}</div>
            <div className="log-detail-value font-mono">{row.id}</div>

            <div className="log-detail-label">{t.CronLog_Date()}</div>
            <div className="log-detail-value">{formatTs(row.started_at)}</div>

            <div className="log-detail-label">{t.CronLog_Task()}</div>
            <div className="log-detail-value font-mono">{row.task_name}</div>

            <div className="log-detail-label">{t.CronLog_Status()}</div>
            <div className="log-detail-value font-mono">{row.status}</div>

            <div className="log-detail-label">{t.CronLog_Duration()}</div>
            <div className="log-detail-value font-mono">{row.duration_ms} ms</div>
        </div>

        {row.error_message && (
            <div className="log-detail-section">
                <div className="log-detail-section-title">{t.CronLog_Error()}</div>
                <pre className="log-detail-pre text-danger">{row.error_message}</pre>
            </div>
        )}

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.CronLog_Output()}</div>
            <pre className="log-detail-pre">{row.output && row.output.length > 0 ? row.output : '—'}</pre>
        </div>
    </div>
);
