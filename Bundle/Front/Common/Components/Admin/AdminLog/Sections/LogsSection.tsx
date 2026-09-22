import * as React from 'react';
import {useState, useMemo} from 'react';
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

export const LogsSection: React.FC<Props> = ({pageUrl, initialData, config, filterOptions}) => {
    const [selected, setSelected] = useState<ActionLog | null>(null);
    const [actorId, setActorId] = useState<string>('');
    const [targetId, setTargetId] = useState<string>('');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');
    const [actionType, setActionType] = useState<string>('');

    const allLabel = t.Admin_Log_Filter_All();

    // actor and target share the same account pool — the log's own
    // distinct actor list (fetchFilterOptions) doubles as the target
    // combobox too, since anyone who ever acted can also be acted upon.
    const personOptions = useMemo(() => (
        [{value: '', label: allLabel}, ...filterOptions.actors.map(a => ({value: String(a.id), label: a.name}))]
    ), [filterOptions.actors, allLabel]);

    const resetAll = () => {
        setActorId('');
        setTargetId('');
        setDateFrom('');
        setDateTo('');
        setActionType('');
    };

    const hasActiveFilter = !!(actorId || targetId || dateFrom || dateTo || actionType);

    const extraParams = useMemo(() => ({
        actorId: actorId ? Number(actorId) : 0,
        targetId: targetId ? Number(targetId) : 0,
        action: actionType,
        dateFrom: dateFrom ? Math.floor(new Date(dateFrom + 'T00:00:00Z').getTime() / 1000) : 0,
        dateTo: dateTo ? Math.floor(new Date(dateTo + 'T23:59:59Z').getTime() / 1000) : 0,
    }), [actorId, targetId, actionType, dateFrom, dateTo]);

    return (
        <div>
            <div className="admin-log-filters">
                <div className="filter-cell filter-cell-user">
                    <label>{t.Admin_Log_Filter_Actor()}</label>
                    <Combobox
                        options={personOptions}
                        value={actorId}
                        onChange={setActorId}
                        placeholder={allLabel}
                        searchPlaceholder={t.Admin_Log_Filter_SearchUser()}
                        emptyText={t.Admin_Log_Filter_NoMatches()}
                        testId="actions-actor-filter"
                    />
                </div>
                <div className="filter-cell filter-cell-user">
                    <label>{t.Admin_Log_Filter_Target()}</label>
                    <Combobox
                        options={personOptions}
                        value={targetId}
                        onChange={setTargetId}
                        placeholder={allLabel}
                        searchPlaceholder={t.Admin_Log_Filter_SearchUser()}
                        emptyText={t.Admin_Log_Filter_NoMatches()}
                        testId="actions-target-filter"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="actions-date-from">{t.Admin_Log_Filter_DateFrom()}</label>
                    <DateInput
                        id="actions-date-from"
                        className="text-sm"
                        value={dateFrom}
                        onChange={e => setDateFrom(e.target.value)}
                        data-test-id="actions-date-from"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="actions-date-to">{t.Admin_Log_Filter_DateTo()}</label>
                    <DateInput
                        id="actions-date-to"
                        className="text-sm"
                        value={dateTo}
                        onChange={e => setDateTo(e.target.value)}
                        data-test-id="actions-date-to"
                    />
                </div>
                <div className="filter-cell">
                    <label htmlFor="actions-action">{t.Admin_Log_Filter_Action()}</label>
                    <select
                        id="actions-action"
                        className="form-select text-sm"
                        value={actionType}
                        onChange={e => setActionType(e.target.value)}
                        data-test-id="actions-action-filter"
                    >
                        <option value="">{allLabel}</option>
                        {filterOptions.actions.map(o => <option key={o} value={o}>{actionLabel(o)}</option>)}
                    </select>
                </div>
                <div className="filter-actions">
                    {hasActiveFilter && (
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            onClick={resetAll}
                            data-test-id="actions-reset"
                            aria-label={t.Admin_Log_Filter_Reset()}
                            title={t.Admin_Log_Filter_Reset()}
                        >
                            ×
                        </button>
                    )}
                </div>
            </div>
            <AdminLogGrid
                pageUrl={pageUrl}
                initialData={initialData}
                config={config}
                extraParams={extraParams}
                rowKey={r => r.id}
                rowTestId={r => `actions-row-${r.id}`}
                emptyMessage={t.Admin_Log_Empty()}
                onRowClick={row => setSelected(row)}
                renders={{
                    created_at:   r => <span className="text-muted text-xs whitespace-nowrap">{formatTs(r.created_at)}</span>,
                    actor_login:  r => <AdminUserLink id={r.actor_id}  name={r.actor_name  || r.actor_login}  role={r.actor_type}  />,
                    target_login: r => <AdminUserLink id={r.target_id} name={r.target_name || r.target_login} role={r.target_type} />,
                    action:       r => <span title={r.action}>{actionLabel(r.action)}</span>,
                }}
            />
            {selected && (
                <LogDetailModal onClose={() => setSelected(null)}>
                    <ActionLogDetail row={selected} />
                </LogDetailModal>
            )}
        </div>
    );
};
