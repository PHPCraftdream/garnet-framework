/**
 * Вкладка истории правок настроек: кто и когда что менял.
 */
import * as React from 'react';
import {EntityHistoryRow, EntityHistoryTable} from '@common/Components/Admin/EntityHistory/EntityHistoryTable';
import {SystemSettingsLabels} from '../types';

interface HistoryTabProps {
    labels: SystemSettingsLabels;
    historyRows: EntityHistoryRow[];
    historyLoading: boolean;
    onRefresh: () => void;
    onRowClick: (row: EntityHistoryRow) => void;
}

export const HistoryTab: React.FC<HistoryTabProps> = ({labels, historyRows, historyLoading, onRefresh, onRowClick}) => (
<section
    className="rounded-lg border border-default bg-surface p-5"
    data-test-id="system-settings-history-section"
>
    <div className="mb-4 flex items-center justify-between">
        <div>
            <h2 className="text-lg font-semibold text-on-surface">
                {labels.historyTitle}
            </h2>
            <p className="mt-1 text-sm text-secondary">
                {labels.historyHint}
            </p>
        </div>
        <button
            type="button"
            className="rounded-lg border border-default px-3 py-1.5 text-sm hover:bg-strong/30"
            disabled={historyLoading}
            onClick={onRefresh}
        >
            {historyLoading ? '…' : labels.historyRefresh}
        </button>
    </div>

    {historyLoading && historyRows.length === 0 && (
        <div className="text-muted">{labels.historyLoading}</div>
    )}
    {!historyLoading && historyRows.length === 0 && (
        <div className="text-muted" data-test-id="system-settings-history-empty">
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
