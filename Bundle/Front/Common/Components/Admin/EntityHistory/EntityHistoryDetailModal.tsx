import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {EntityHistoryRow} from './EntityHistoryTable';
import {formatHistoryTime} from './entityHistoryFormat';
import {Portal} from '@common/Components/Layout/Portal';

interface Props {
    row: EntityHistoryRow;
    onClose: () => void;
}

/** Formats a value for the history detail view. */
function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '∅';
    if (value === '') return '""';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
}

/** Prevents clicks inside the dialog from closing it. */
function stopPropagation(event: React.MouseEvent): void {
    event.stopPropagation();
}

/** Renders one changed field in the diff table. */
function renderDiffRow([field, change]: [string, {old: unknown; new: unknown}]) {
    return <tr key={field} className="entity-history-detail-row">
        <td className="entity-history-field">{field}</td>
        <td className="entity-history-old-value">{formatValue(change.old)}</td>
        <td className="entity-history-new-value">{formatValue(change.new)}</td>
    </tr>;
}

/** Renders a table of changed fields, or nothing when there are none. */
function renderDiff(diff: NonNullable<EntityHistoryRow['diff']>) {
    const entries = Object.entries(diff);
    if (entries.length === 0) return null;
    return <table className="entity-history-table">
        <thead><tr className="entity-history-table-header">
            <th className="entity-history-heading">{t.EntityHistory_Field()}</th>
            <th className="entity-history-heading">{t.EntityHistory_Old()}</th>
            <th className="entity-history-heading">{t.EntityHistory_New()}</th>
        </tr></thead>
        <tbody>{entries.map(renderDiffRow)}</tbody>
    </table>;
}

export class EntityHistoryDetailModal extends AntiHookComponent<Props> {
    /** Registers the Escape-key listener. */
    public componentDidMount(): void {
        super.componentDidMount();
        document.addEventListener('keydown', this.onKey);
    }
    /** Removes the Escape-key listener. */
    public componentWillUnmount(): void {
        document.removeEventListener('keydown', this.onKey);
        super.componentWillUnmount();
    }
    /** Closes the dialog when Escape is pressed. */
    private readonly onKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') this.props.onClose();
    };
    /** Closes the dialog when its backdrop is clicked. */
    private readonly handleOverlayClick = (event: React.MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) this.props.onClose();
    };

    /** Renders the history detail dialog. */
    public render() {
        const {row, onClose} = this.props;
        const actor = row.actor_name || row.actor_login_resolved || row.actor_login || '—';
        return <Portal><div className="entity-history-overlay" onClick={this.handleOverlayClick} data-test-id="entity-history-detail-modal">
            <div role="dialog" aria-modal="true" className="entity-history-card" onClick={stopPropagation}>
                <div className="entity-history-header">
                    <h3 className="entity-history-title">{t.EntityHistory_Title()}</h3>
                    <button type="button" className="entity-history-close" onClick={onClose} data-test-id="entity-history-detail-close">&times;</button>
                </div>
                <div className="entity-history-body">
                    <div className="entity-history-metadata">
                        <div><strong>{t.EntityHistory_When()}:</strong> {formatHistoryTime(row.created_at)}</div>
                        <div><strong>{t.EntityHistory_Actor()}:</strong> {actor}</div>
                    </div>
                    {row.diff && renderDiff(row.diff)}
                </div>
            </div>
        </div></Portal>;
    }
}
