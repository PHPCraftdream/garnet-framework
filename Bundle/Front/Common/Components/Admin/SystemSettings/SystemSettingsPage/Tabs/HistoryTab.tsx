/**
 * Вкладка истории правок настроек: кто и когда что менял.
 */
import * as React from 'react';
import {EntityHistoryRow, EntityHistoryTable} from '@common/Components/Admin/EntityHistory/EntityHistoryTable';
import {SystemSettingsLabels} from '@common/Components/Admin/SystemSettings/SystemSettingsPage/types';

interface HistoryTabProps {
    labels: SystemSettingsLabels;
    historyRows: EntityHistoryRow[];
    historyLoading: boolean;
    onRefresh: () => void;
    onRowClick: (row: EntityHistoryRow) => void;
}

/** Renders the settings change history and exposes refresh and row selection. */
export const HistoryTab: React.FC<HistoryTabProps> = ({labels, historyRows, historyLoading, onRefresh, onRowClick}) => (
<section
    className="settings-history-panel"
    data-test-id="system-settings-history-section"
>
    <div className="settings-history-header">
        <div>
            <h2 className="settings-history-title">
                {labels.historyTitle}
            </h2>
            <p className="settings-history-hint">
                {labels.historyHint}
            </p>
        </div>
        <button
            type="button"
            className="settings-history-refresh"
            disabled={historyLoading}
            onClick={onRefresh}
        >
            {historyLoading ? '…' : labels.historyRefresh}
        </button>
    </div>

    {historyLoading && historyRows.length === 0 && (
        <div className="settings-history-message">{labels.historyLoading}</div>
    )}
    {!historyLoading && historyRows.length === 0 && (
        <div className="settings-history-message" data-test-id="system-settings-history-empty">
            {labels.historyEmpty}
        </div>
    )}
    {historyRows.length > 0 && (
        <EntityHistoryTable
            rows={historyRows}
            onRowClick={onRowClick}
            showAction={false}
            dataTestId="system-settings-history-table"
            rowTestIdPrefix="system-settings-history-row"
        />
    )}
</section>
);
