import * as React from 'react';
import {ActionLog} from '../types';
import {AdminUserLink} from '../AdminUserLink';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {actionLabel} from '../actionLabel';

interface Props {
    row: ActionLog;
}

export const ActionLogDetail: React.FC<Props> = ({row}) => (
    <div data-test-id="log-detail-actions">
        <div className="log-detail-grid">
            <div className="log-detail-label">{t.LogDetail_Id()}</div>
            <div className="log-detail-value font-mono">{row.id}</div>

            <div className="log-detail-label">{t.Admin_Log_CreatedAt()}</div>
            <div className="log-detail-value">{formatTs(row.created_at)}</div>

            <div className="log-detail-label">{t.Admin_Log_Actor()}</div>
            <div className="log-detail-value">
                <AdminUserLink
                    id={row.actor_id}
                    name={row.actor_name || row.actor_login}
                    role={row.actor_type}
                />
                {row.actor_login && (
                    <span className="text-muted ml-2 text-xs font-mono">{row.actor_login}</span>
                )}
            </div>

            <div className="log-detail-label">{t.Admin_Log_Target()}</div>
            <div className="log-detail-value">
                <AdminUserLink
                    id={row.target_id}
                    name={row.target_name || row.target_login}
                    role={row.target_type}
                />
                {row.target_login && (
                    <span className="text-muted ml-2 text-xs font-mono">{row.target_login}</span>
                )}
            </div>

            <div className="log-detail-label">{t.Admin_Log_Action()}</div>
            <div className="log-detail-value" title={row.action}>{actionLabel(row.action)}</div>
        </div>

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.Admin_Log_OldValue()}</div>
            <pre className="log-detail-pre">{row.old_value || '—'}</pre>
        </div>

        <div className="log-detail-section">
            <div className="log-detail-section-title">{t.Admin_Log_NewValue()}</div>
            <pre className="log-detail-pre">{row.new_value || '—'}</pre>
        </div>
    </div>
);
