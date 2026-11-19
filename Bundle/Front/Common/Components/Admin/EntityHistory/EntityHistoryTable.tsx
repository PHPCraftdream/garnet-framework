import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatHistoryTime, renderHistoryValue} from './entityHistoryFormat';

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
    onRowClick?: (row: EntityHistoryRow) => void;
    showAction?: boolean;
    dataTestId?: string;
    rowTestIdPrefix?: string;
}

function renderDiffEntry([field, change]: [string, {old: unknown; new: unknown}]) {
    return <li key={field}>
        <strong>{field}:</strong>{' '}
        <span className="entity-history-old">{renderHistoryValue(change.old)}</span>
        {' → '}
        <span className="entity-history-new">{renderHistoryValue(change.new)}</span>
    </li>;
}

export class EntityHistoryTable extends AntiHookComponent<Props> {
    private readonly onOpen = (event: React.MouseEvent<HTMLButtonElement>): void => {
        const index = Number(event.currentTarget.dataset.rowIndex);
        const row = this.props.rows[index];
        if (row) this.props.onRowClick?.(row);
    };

    private renderChanges(row: EntityHistoryRow, index: number) {
        const fields = row.diff ? Object.keys(row.diff) : [];
        if (fields.length === 0) return row.comment ? <span>{row.comment}</span> : <span className="entity-history-muted">—</span>;
        if (this.props.onRowClick) return <button type="button" className="entity-history-open" onClick={this.onOpen}
            data-row-index={index} data-test-id={`entity-history-open-${row.id}`}>
            {fields.length === 1 ? fields[0] : `${fields.length} ${t.EntityHistory_FieldsCount()}`}
        </button>;
        return <ul className="entity-history-diff">{Object.entries(row.diff!).map(renderDiffEntry)}</ul>;
    }

    private readonly renderRow = (row: EntityHistoryRow, index: number) => {
        const {showAction = true, rowTestIdPrefix = 'entity-history-row'} = this.props;
        const actor = row.actor_name || row.actor_login_resolved || row.actor_login || '—';
        return <tr key={row.id} className="entity-history-row" data-test-id={`${rowTestIdPrefix}-${row.id}`}>
            <td className="entity-history-time">{formatHistoryTime(row.created_at)}</td>
            <td className="entity-history-cell"><span title={row.actor_id ? `#${row.actor_id}` : undefined}>{actor}</span></td>
            {showAction && <td className="entity-history-cell"><code data-test-id={`entity-history-action-${row.id}`}>{row.action}</code></td>}
            <td className="entity-history-cell">{this.renderChanges(row, index)}</td>
        </tr>;
    };

    public render() {
        const {rows, showAction = true, dataTestId = 'entity-history-table'} = this.props;
        return <table className="entity-history-table" data-test-id={dataTestId}>
            <thead><tr className="entity-history-table-header">
                <th className="entity-history-heading">{t.EntityHistory_When()}</th>
                <th className="entity-history-heading">{t.EntityHistory_Actor()}</th>
                {showAction && <th className="entity-history-heading">{t.EntityHistory_Action()}</th>}
                <th className="entity-history-heading">{t.EntityHistory_Changes()}</th>
            </tr></thead>
            <tbody>{rows.map(this.renderRow)}</tbody>
        </table>;
    }
}
