import * as React from 'react';
import {useState, useMemo, lazy, Suspense} from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {PageResponse} from '@common/hooks/data/usePagination';
import {ActionLog, MailLogEntry, CronLogEntry, JsErrorEntry, GridConfig, ActionsFilterOptions, MailsFilterOptions, CronFilterOptions, JsErrorsFilterOptions} from '../types';
import {LogsSection} from '../Sections/LogsSection';
import {MailLogSection} from '../Sections/MailLogSection';
import {AdminRequestLogIsland} from './AdminRequestLogIsland';
import {AdminErrorsLogIsland} from './AdminErrorsLogIsland';
import {CronLogSection} from '../Sections/CronLogSection';
import {UserDetailContext} from '../../AdminPanel/UserDetailContext';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {ScrollText} from 'lucide-react';
import {goTo} from '@common/Dom/Nav/GoTo';

const JsErrorLogSection = lazy(() => import('../Sections/JsErrorLogSection').then(m => ({default: m.JsErrorLogSection})));

type TabId = 'actions' | 'mails' | 'requests' | 'errors' | 'cron' | 'js-errors';

const ALL_TABS: TabId[] = ['actions', 'mails', 'requests', 'errors', 'cron', 'js-errors'];

interface ActionsBlock {
    gridConfig: GridConfig;
    payload: PageResponse<ActionLog> | null;
    filterOptions: ActionsFilterOptions;
}

interface MailsBlock {
    gridConfig: GridConfig;
    payload: PageResponse<MailLogEntry> | null;
    filterOptions: MailsFilterOptions;
}

interface RequestsBlock {
    dates: string[];
}

interface ErrorsBlock {
    dates: string[];
}

interface CronBlock {
    payload: PageResponse<CronLogEntry> | null;
    filterOptions: CronFilterOptions;
}

interface JsErrorsBlock {
    payload: PageResponse<JsErrorEntry> | null;
    filterOptions: JsErrorsFilterOptions;
}

interface Endpoints {
    actions: string;
    mails: string;
    requests: string;
    errors: string;
    cron: string;
    'js-errors': string;
}

interface Props {
    initialTab: TabId;
    endpoints: Endpoints;
    actions: ActionsBlock;
    mails: MailsBlock;
    requests: RequestsBlock;
    errors: ErrorsBlock;
    cron: CronBlock;
    jsErrors: JsErrorsBlock;
}

const isTabId = (v: string): v is TabId => (ALL_TABS as readonly string[]).includes(v);

const readInitialTab = (fallback: TabId): TabId => {
    if (typeof window === 'undefined') return fallback;
    try {
        const params = new URLSearchParams(window.location.search);
        const q = params.get('tab') ?? '';
        if (isTabId(q)) return q;
    } catch {
        // ignore
    }
    return fallback;
};

const writeTabToUrl = (tab: TabId): void => {
    if (typeof window === 'undefined') return;
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url.toString());
    } catch {
        // ignore
    }
};

const tabLabel = (id: TabId): string => {
    switch (id) {
        case 'actions':   return t.Logs_Tab_Actions();
        case 'mails':     return t.Logs_Tab_Mails();
        case 'requests':  return t.Logs_Tab_Requests();
        case 'errors':    return t.Logs_Tab_Errors();
        case 'cron':      return t.Logs_Tab_Cron();
        case 'js-errors': return t.Logs_Tab_JsErrors();
    }
};

/**
 * Each tab's Section owns its own AdminGrid (fetch-on-mount via
 * usePagination) — conditionally rendering a tab IS the lazy-load: a tab
 * visited for the first time mounts with `initialData=null` and fetches
 * itself, exactly like AdminGrid's `initialData` contract already works
 * everywhere else. No bespoke loaded/loading state needed here anymore.
 */
export const AdminLogsViewerIsland: React.FC<Props> = ({
    initialTab,
    endpoints,
    actions,
    mails,
    requests,
    errors,
    cron,
    jsErrors,
}) => {
    const [tab, setTab] = useState<TabId>(() => readInitialTab(initialTab));

    const selectTab = (id: TabId): void => {
        setTab(id);
        writeTabToUrl(id);
    };

    const userContext = useMemo(() => ({
        openUser: (id: number) => goTo(`/admin/#user=${id}`),
    }), []);

    return (
        <UserDetailContext.Provider value={userContext}>
        <div data-test-id="admin-logs-viewer">
            <PageHeader title={t.Logs_Title()} icon={<ScrollText size={22} aria-hidden="true" />} />

            <div className="section-soft">
            <ul className="flex flex-wrap border-b border-default mb-4">
                {ALL_TABS.map(id => (
                    <li key={id} className="admin-tabnav-item">
                        <button
                            type="button"
                            className={`admin-tabnav-btn ${tab === id ? 'admin-tabnav-btn-active' : ''}`}
                            data-test-id={`tabnav-btn-${id}`}
                            aria-selected={tab === id}
                            onClick={() => selectTab(id)}
                        >
                            {tabLabel(id)}
                        </button>
                    </li>
                ))}
            </ul>

            {tab === 'actions' && (
                <LogsSection pageUrl={endpoints.actions} initialData={actions.payload} config={actions.gridConfig} filterOptions={actions.filterOptions} />
            )}
            {tab === 'mails' && (
                <MailLogSection pageUrl={endpoints.mails} initialData={mails.payload} config={mails.gridConfig} filterOptions={mails.filterOptions} />
            )}
            {tab === 'requests' && (
                <AdminRequestLogIsland dates={requests.dates} pageUrl={endpoints.requests} />
            )}
            {tab === 'errors' && (
                <AdminErrorsLogIsland dates={errors.dates} pageUrl={endpoints.errors} />
            )}
            {tab === 'cron' && (
                <CronLogSection pageUrl={endpoints.cron} initialData={cron.payload} filterOptions={cron.filterOptions} />
            )}
            {tab === 'js-errors' && (
                <Suspense fallback={null}>
                    <JsErrorLogSection pageUrl={endpoints['js-errors']} initialData={jsErrors.payload} filterOptions={jsErrors.filterOptions} />
                </Suspense>
            )}
            </div>
        </div>
        </UserDetailContext.Provider>
    );
};
