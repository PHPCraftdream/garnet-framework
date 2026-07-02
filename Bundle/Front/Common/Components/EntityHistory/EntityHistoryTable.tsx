import * as React from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';

export interface EntityHistoryRow {
    id: number;
    entity_type?: string;
    entity_id?: string;
    action: string;
    actor_id?: number;
    actor_login: string;
    actor_login_resolved: string;
    actor_name: string;
    diff: Record<string, {old: unknown; new: unknown}> | null;
    snapshot?: Record<string, unknown> | null;
    comment?: string;
    created_at: number;
    ip?: string;
    user_agent?: string;
}

interface Props {
    rows: EntityHistoryRow[];
    /** When provided, the "Changes" cell becomes a clickable summary button. */
    onRowClick?: (row: EntityHistoryRow) => void;
    showAction?: boolean;
    dataTestId?: string;
    rowTestIdPrefix?: string;
}

/** TZ-aware history timestamp formatter — uses user's TZ via formatTs (AGENTS.md §12). */
export const formatHistoryTime = (ts: number): string => {
    if (!ts) return '';
    return formatTs(ts);
};

export const renderHistoryValue = (v: unknown): string => {
    if (v === null || v === undefined) return '∅';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
};

const actorLabel = (row: EntityHistoryRow): string =>
    row.actor_name || row.actor_login_resolved || row.actor_login || '—';

export const EntityHistoryTable: React.FC<Props> = ({
    rows,
    onRowClick,
    showAction = true,
    dataTestId = 'entity-history-table',
    rowTestIdPrefix = 'entity-history-row',
}) => {
    return (
        <table className="admin-table" data-test-id={dataTestId}>
            <thead>
                <tr className="border-b border-subtle text-left">
                    <th>{t.EntityHistory_When()}</th>
                    <th>{t.EntityHistory_Actor()}</th>
                    {showAction && <th>{t.EntityHistory_Action()}</th>}
                    <th>{t.EntityHistory_Changes()}</th>
                </tr>
            </thead>
            <tbody>
                {rows.map(row => {
                    const fieldNames = row.diff ? Object.keys(row.diff) : [];
                    return (
                        <tr key={row.id} data-test-id={`${rowTestIdPrefix}-${row.id}`}>
                            <td className="whitespace-nowrap">{formatHistoryTime(row.created_at)}</td>
                            <td>
                                <span title={row.actor_id ? `#${row.actor_id}` : undefined}>
                                    {actorLabel(row)}
                                </span>
                            </td>
                            {showAction && (
                                <td>
                                    <code data-test-id={`entity-history-action-${row.id}`}>{row.action}</code>
                                </td>
                            )}
                            <td>
                                {fieldNames.length === 0 ? (
                                    row.comment ? (
                                        <span>{row.comment}</span>
                                    ) : (
                                        <span className="text-muted">—</span>
                                    )
                                ) : onRowClick ? (
                                    <button
                                        type="button"
                                        className="text-accent hover:text-on-surface text-sm underline text-left"
                                        onClick={() => onRowClick(row)}
                                        data-test-id={`entity-history-open-${row.id}`}
                                    >
                                        {fieldNames.length === 1
                                            ? fieldNames[0]
                                            : `${fieldNames.length} ${t.EntityHistory_FieldsCount()}`}
                                    </button>
                                ) : (
                                    <ul className="entity-history-diff">
                                        {Object.entries(row.diff!).map(([field, {old, new: nv}]) => (
                                            <li key={field}>
                                                <strong>{field}:</strong>{' '}
                                                <span className="entity-history-old">{renderHistoryValue(old)}</span>
                                                {' → '}
                                                <span className="entity-history-new">{renderHistoryValue(nv)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
};
