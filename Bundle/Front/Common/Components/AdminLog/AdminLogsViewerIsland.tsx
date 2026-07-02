import * as React from 'react';
import {useState, useEffect, useCallback, useMemo, lazy, Suspense} from 'react';
import {sendPost} from '@common/Api/sendPost';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {ActionLog, MailLogEntry, CronLogEntry, JsErrorEntry, GridConfig} from './types';
import {LogsSection} from './LogsSection';
import {MailLogSection} from './MailLogSection';
import {AdminRequestLogIsland} from './AdminRequestLogIsland';
import {AdminErrorsLogIsland} from './AdminErrorsLogIsland';
import {CronLogSection} from './CronLogSection';
import {UserDetailContext} from '../AdminPanel/UserDetailContext';
import {PageHeader} from '@common/Components/PageHeader';
import {ScrollText} from 'lucide-react';
import {goTo} from '@common/Dom/Nav/GoTo';

const JsErrorLogSection = lazy(() => import('./JsErrorLogSection').then(m => ({default: m.JsErrorLogSection})));

type TabId = 'actions' | 'mails' | 'requests' | 'errors' | 'cron' | 'js-errors';

const ALL_TABS: TabId[] = ['actions', 'mails', 'requests', 'errors', 'cron', 'js-errors'];

interface ActionsBlock {
    gridConfig: GridConfig;
    logs: ActionLog[];
    loaded: boolean;
}

interface MailsBlock {
    gridConfig: GridConfig;
    logs: MailLogEntry[];
    loaded: boolean;
}

interface RequestsBlock {
    dates: string[];
}

interface ErrorsBlock {
    dates: string[];
}

interface CronBlock {
    logs: CronLogEntry[];
    loaded: boolean;
}

interface JsErrorsBlock {
    logs: JsErrorEntry[];
    loaded: boolean;
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

interface ActionsResponse {
    logs: ActionLog[];
}

interface MailsResponse {
    logs: MailLogEntry[];
}

interface CronResponse {
    logs: CronLogEntry[];
}

interface JsErrorsResponse {
    logs: JsErrorEntry[];
}

export const AdminLogsViewerIsland: React.FC<Props> = ({
    initialTab,
    endpoints,
    actions: initialActions,
    mails: initialMails,
    requests: initialRequests,
    errors: initialErrors,
    cron: initialCron,
    jsErrors: initialJsErrors,
}) => {
    const [tab, setTab] = useState<TabId>(() => readInitialTab(initialTab));

    const [actionsLogs, setActionsLogs] = useState<ActionLog[]>(initialActions.logs);
    const [actionsLoaded, setActionsLoaded] = useState<boolean>(initialActions.loaded);
    const [actionsLoading, setActionsLoading] = useState<boolean>(false);

    const [mailsLogs, setMailsLogs] = useState<MailLogEntry[]>(initialMails.logs);
    const [mailsLoaded, setMailsLoaded] = useState<boolean>(initialMails.loaded);
    const [mailsLoading, setMailsLoading] = useState<boolean>(false);

    const [cronLogs, setCronLogs] = useState<CronLogEntry[]>(initialCron.logs);
    const [cronLoaded, setCronLoaded] = useState<boolean>(initialCron.loaded);
    const [cronLoading, setCronLoading] = useState<boolean>(false);

    const [jsErrorLogs, setJsErrorLogs] = useState<JsErrorEntry[]>(initialJsErrors.logs);
    const [jsErrorsLoaded, setJsErrorsLoaded] = useState<boolean>(initialJsErrors.loaded);
    const [jsErrorsLoading, setJsErrorsLoading] = useState<boolean>(false);

    const loadActions = useCallback(async (): Promise<void> => {
        setActionsLoading(true);
        try {
            const res = await sendPost<object, ActionsResponse>(endpoints.actions, {});
            setActionsLogs(res.logs ?? []);
            setActionsLoaded(true);
        } finally {
            setActionsLoading(false);
        }
    }, [endpoints.actions]);

    const loadMails = useCallback(async (): Promise<void> => {
        setMailsLoading(true);
        try {
            const res = await sendPost<object, MailsResponse>(endpoints.mails, {});
            setMailsLogs(res.logs ?? []);
            setMailsLoaded(true);
        } finally {
            setMailsLoading(false);
        }
    }, [endpoints.mails]);

    const loadCron = useCallback(async (): Promise<void> => {
        setCronLoading(true);
        try {
            const res = await sendPost<object, CronResponse>(endpoints.cron, {});
            setCronLogs(res.logs ?? []);
            setCronLoaded(true);
        } finally {
            setCronLoading(false);
        }
    }, [endpoints.cron]);

    const loadJsErrors = useCallback(async (): Promise<void> => {
        setJsErrorsLoading(true);
        try {
            const res = await sendPost<object, JsErrorsResponse>(endpoints['js-errors'], {});
            setJsErrorLogs(res.logs ?? []);
            setJsErrorsLoaded(true);
        } finally {
            setJsErrorsLoading(false);
        }
    }, [endpoints]);

    // Lazy-load on first activation of a not-yet-loaded tab.
    useEffect(() => {
        if (tab === 'actions' && !actionsLoaded && !actionsLoading) {
            void loadActions();
        }
        if (tab === 'mails' && !mailsLoaded && !mailsLoading) {
            void loadMails();
        }
        if (tab === 'cron' && !cronLoaded && !cronLoading) {
            void loadCron();
        }
        if (tab === 'js-errors' && !jsErrorsLoaded && !jsErrorsLoading) {
            void loadJsErrors();
        }
    }, [tab, actionsLoaded, actionsLoading, mailsLoaded, mailsLoading, cronLoaded, cronLoading, jsErrorsLoaded, jsErrorsLoading, loadActions, loadMails, loadCron, loadJsErrors]);

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
                <LogsSection logs={actionsLogs} config={initialActions.gridConfig} />
            )}
            {tab === 'mails' && (
                <MailLogSection logs={mailsLogs} config={initialMails.gridConfig} />
            )}
            {tab === 'requests' && (
                <AdminRequestLogIsland dates={initialRequests.dates} pageUrl={endpoints.requests} />
            )}
            {tab === 'errors' && (
                <AdminErrorsLogIsland dates={initialErrors.dates} pageUrl={endpoints.errors} />
            )}
            {tab === 'cron' && (
                <CronLogSection logs={cronLogs} />
            )}
            {tab === 'js-errors' && (
                <Suspense fallback={null}>
                    <JsErrorLogSection logs={jsErrorLogs} />
                </Suspense>
            )}
            </div>
        </div>
        </UserDetailContext.Provider>
    );
};
