import * as React from 'react';
import {useState, useMemo} from 'react';
import {ActionLog, GridConfig} from './types';
import {AdminLogGrid} from './AdminLogGrid';
import {AdminUserLink} from './AdminUserLink';
import {Combobox} from '@common/Components/ui/Combobox';
import {DateInput} from '@common/Components/ui/DateInput';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {formatTs} from '@common/Utils/DateUtils';
import {LogDetailModal} from './LogDetailModal';
import {ActionLogDetail} from './details/ActionLogDetail';
import {actionLabel} from './actionLabel';

interface Props {
    logs: ActionLog[];
    config: GridConfig;
}

export const LogsSection: React.FC<Props> = ({logs, config}) => {
    const [selected, setSelected] = useState<ActionLog | null>(null);
    const [actorId, setActorId] = useState<string>('');
    const [targetId, setTargetId] = useState<string>('');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');
    const [actionType, setActionType] = useState<string>('');
    const [actorType, setActorType] = useState<string>('');

    const allLabel = t.Admin_Log_Filter_All();

    const actorOptions = useMemo(() => {
        const map = new Map<string, string>();
        for (const r of logs) {
            const id = String(r.actor_id);
            if (!map.has(id)) map.set(id, r.actor_name || r.actor_login || `#${r.actor_id}`);
        }
        const arr = Array.from(map.entries()).map(([value, label]) => ({value, label}));
        arr.sort((a, b) => a.label.localeCompare(b.label));
        return [{value: '', label: allLabel}, ...arr];
    }, [logs, allLabel]);

    const targetOptions = useMemo(() => {
        const map = new Map<string, string>();
        for (const r of logs) {
            const id = String(r.target_id);
            if (!map.has(id)) map.set(id, r.target_name || r.target_login || `#${r.target_id}`);
        }
        const arr = Array.from(map.entries()).map(([value, label]) => ({value, label}));
        arr.sort((a, b) => a.label.localeCompare(b.label));
        return [{value: '', label: allLabel}, ...arr];
    }, [logs, allLabel]);

    const actionOptions = useMemo(() => {
        const set = new Set<string>();
        for (const r of logs) {
            if (r.action) set.add(r.action);
        }
        return Array.from(set).toSorted((a, b) => a.localeCompare(b));
    }, [logs]);

    const actorTypeOptions = useMemo(() => {
        const set = new Set<string>();
        for (const r of logs) {
            if (r.actor_type) set.add(r.actor_type);
        }
        return Array.from(set).toSorted((a, b) => a.localeCompare(b));
    }, [logs]);

    const filteredLogs = useMemo(() => {
        let res = logs;
        if (actorId) res = res.filter(r => String(r.actor_id) === actorId);
        if (targetId) res = res.filter(r => String(r.target_id) === targetId);
        if (actionType) res = res.filter(r => r.action === actionType);
        if (actorType) res = res.filter(r => r.actor_type === actorType);
        if (dateFrom) {
            const tsFrom = Math.floor(new Date(dateFrom + 'T00:00:00Z').getTime() / 1000);
            res = res.filter(r => r.created_at >= tsFrom);
        }
        if (dateTo) {
            const tsTo = Math.floor(new Date(dateTo + 'T23:59:59Z').getTime() / 1000);
            res = res.filter(r => r.created_at <= tsTo);
        }
        return res;
    }, [logs, actorId, targetId, actionType, actorType, dateFrom, dateTo]);

    const resetAll = () => {
        setActorId('');
        setTargetId('');
        setDateFrom('');
        setDateTo('');
        setActionType('');
        setActorType('');
    };

    const hasActiveFilter = !!(actorId || targetId || dateFrom || dateTo || actionType || actorType);

    return (
        <div>
            <div className="admin-log-filters">
                <div className="filter-cell filter-cell-user">
                    <label>{t.Admin_Log_Filter_Actor()}</label>
                    <Combobox
                        options={actorOptions}
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
                        options={targetOptions}
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
                        {actionOptions.map(o => <option key={o} value={o}>{actionLabel(o)}</option>)}
                    </select>
                </div>
                <div className="filter-cell">
                    <label htmlFor="actions-actor-type">{t.Admin_Log_Filter_ActorType()}</label>
                    <select
                        id="actions-actor-type"
                        className="form-select text-sm"
                        value={actorType}
                        onChange={e => setActorType(e.target.value)}
                        data-test-id="actions-actor-type-filter"
                    >
                        <option value="">{allLabel}</option>
                        {actorTypeOptions.map(o => <option key={o} value={o}>{o}</option>)}
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
                    <span className="filter-counter">{filteredLogs.length} / {logs.length}</span>
                </div>
            </div>
            <AdminLogGrid
                rows={filteredLogs}
                config={config}
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
