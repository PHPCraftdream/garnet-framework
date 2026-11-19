import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {ActionLog, ActionsFilterOptions, GridConfig} from '../types';
import {AdminLogGrid} from '../AdminLogGrid';
import {PageResponse} from '@common/hooks/data/usePagination';
import {AdminUserLink} from '../AdminUserLink';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {LogDetailModal} from '../LogDetailModal';
import {ActionLogDetail} from '../details/ActionLogDetail';
import {actionLabel} from './actionLabel';

interface Props {
    pageUrl: string;
    initialData: PageResponse<ActionLog> | null;
    config: GridConfig;
    filterOptions: ActionsFilterOptions;
}

interface Filters {
    actorId: string;
    targetId: string;
    dateFrom: string;
    dateTo: string;
    actionType: string;
}

const emptyFilters = (): Filters => ({actorId: '', targetId: '', dateFrom: '', dateTo: '', actionType: ''});
function actorOption(actor: ActionsFilterOptions['actors'][number]) {
    return {value: String(actor.id), label: actor.name};
}

function renderActionOption(action: string) {
    return <option key={action} value={action}>{actionLabel(action)}</option>;
}

const rowKey = (row: ActionLog) => row.id;
const rowTestId = (row: ActionLog) => `actions-row-${row.id}`;
const renders = {
    created_at: (row: ActionLog) => <span className="admin-log-section-time">{formatTs(row.created_at)}</span>,
    actor_login: (row: ActionLog) => <AdminUserLink id={row.actor_id} name={row.actor_name || row.actor_login} role={row.actor_type} />,
    target_login: (row: ActionLog) => <AdminUserLink id={row.target_id} name={row.target_name || row.target_login} role={row.target_type} />,
    action: (row: ActionLog) => <span title={row.action}>{actionLabel(row.action)}</span>,
};

export class LogsSection extends AntiHookComponent<Props> {
    private readonly filters = new Carburetor<Filters>(emptyFilters());
    private readonly selection = new Carburetor<{selected: ActionLog | null}>({selected: null});
    private readonly selected = this.connectSelection(this.selection, state => state.selected);
    private lastFilters: Filters | null = null;
    private extraParams: Record<string, unknown> = {};

    private patch(patch: Partial<Filters>): void {
        this.filters.setData({...this.filters.getData(), ...patch});
    }

    private readonly changeActor = (value: string) => this.patch({actorId: value});
    private readonly changeTarget = (value: string) => this.patch({targetId: value});
    private readonly changeFrom = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateFrom: event.target.value});
    private readonly changeTo = (event: React.ChangeEvent<HTMLInputElement>) => this.patch({dateTo: event.target.value});
    private readonly changeAction = (event: React.ChangeEvent<HTMLSelectElement>) => this.patch({actionType: event.target.value});
    private readonly resetAll = () => this.filters.setData(emptyFilters());
    private readonly selectRow = (selected: ActionLog) => this.selection.setData({selected});
    private readonly closeDetail = () => this.selection.setData({selected: null});

    private params(filters: Filters): Record<string, unknown> {
        const last = this.lastFilters;
        if (!last || last.actorId !== filters.actorId || last.targetId !== filters.targetId ||
            last.actionType !== filters.actionType || last.dateFrom !== filters.dateFrom || last.dateTo !== filters.dateTo) {
            this.lastFilters = {...filters};
            this.extraParams = {
                actorId: filters.actorId ? Number(filters.actorId) : 0,
                targetId: filters.targetId ? Number(filters.targetId) : 0,
                action: filters.actionType,
                dateFrom: filters.dateFrom ? Math.floor(new Date(filters.dateFrom + 'T00:00:00Z').getTime() / 1000) : 0,
                dateTo: filters.dateTo ? Math.floor(new Date(filters.dateTo + 'T23:59:59Z').getTime() / 1000) : 0,
            };
        }
        return this.extraParams;
    }

    private renderFilters(filters: Filters): React.ReactNode {
        const {filterOptions} = this.props;
        const allLabel = t.Admin_Log_Filter_All();
        const personOptions = [{value: '', label: allLabel}, ...filterOptions.actors.map(actorOption)];
        const hasActiveFilter = !!(filters.actorId || filters.targetId || filters.dateFrom || filters.dateTo || filters.actionType);
        return <div className="admin-log-filters">
            <div className="admin-log-person-filter">
                <label>{t.Admin_Log_Filter_Actor()}</label>
                <Combobox options={personOptions} value={filters.actorId} onChange={this.changeActor} placeholder={allLabel}
                    searchPlaceholder={t.Admin_Log_Filter_SearchUser()} emptyText={t.Admin_Log_Filter_NoMatches()} testId="actions-actor-filter" />
            </div>
            <div className="admin-log-person-filter">
                <label>{t.Admin_Log_Filter_Target()}</label>
                <Combobox options={personOptions} value={filters.targetId} onChange={this.changeTarget} placeholder={allLabel}
                    searchPlaceholder={t.Admin_Log_Filter_SearchUser()} emptyText={t.Admin_Log_Filter_NoMatches()} testId="actions-target-filter" />
            </div>
            <div className="filter-cell">
                <label htmlFor="actions-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                <DateInput id="actions-date-from" value={filters.dateFrom} onChange={this.changeFrom} data-test-id="actions-date-from" />
            </div>
            <div className="filter-cell">
                <label htmlFor="actions-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                <DateInput id="actions-date-to" value={filters.dateTo} onChange={this.changeTo} data-test-id="actions-date-to" />
            </div>
            <div className="filter-cell">
                <label htmlFor="actions-action">{t.Admin_Log_Filter_Action()}</label>
                <select id="actions-action" className="admin-log-section-select" value={filters.actionType} onChange={this.changeAction} data-test-id="actions-action-filter">
                    <option value="">{allLabel}</option>
                    {filterOptions.actions.map(renderActionOption)}
                </select>
            </div>
            <div className="filter-actions">
                {hasActiveFilter && <button type="button" className="admin-log-section-reset" onClick={this.resetAll}
                    data-test-id="actions-reset" aria-label={t.Admin_Log_Filter_Reset()} title={t.Admin_Log_Filter_Reset()}>×</button>}
            </div>
        </div>;
    }

    public render() {
        const filters = this.useCarburetor(this.filters);
        const selected = this.selected();
        const {pageUrl, initialData, config} = this.props;
        return <div>
            {this.renderFilters(filters)}
            <AdminLogGrid pageUrl={pageUrl} initialData={initialData} config={config} extraParams={this.params(filters)}
                rowKey={rowKey} rowTestId={rowTestId} emptyMessage={t.Admin_Log_Empty()} onRowClick={this.selectRow} renders={renders} />
            {selected && <LogDetailModal onClose={this.closeDetail}><ActionLogDetail row={selected} /></LogDetailModal>}
        </div>;
    }
}
