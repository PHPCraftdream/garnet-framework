import * as React from 'react';
import {lazy, Suspense} from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
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

const JsErrorLogSection = lazy(async () => {
    const {JsErrorLogSection: Section} = await import('../Sections/JsErrorLogSection');
    return {default: Section};
});

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

function openUser(id: number): void {
    goTo(`/admin/#user=${id}`);
}

const LOGS_ICON = <ScrollText size={22} aria-hidden="true" />;

class AdminLogsViewer extends AntiHookComponent<Props> {
    private readonly ui = new Carburetor({tab: readInitialTab(this.props.initialTab)});
    private readonly userContext = {openUser};

    private readonly selectTab = (event: React.MouseEvent<HTMLButtonElement>): void => {
        const id = event.currentTarget.value;
        if (!isTabId(id)) return;
        this.ui.setData({tab: id});
        writeTabToUrl(id);
    };

    private renderTabs(tab: TabId) {
        return (
            <ul className="admin-log-tab-list">
                {ALL_TABS.map(id => (
                    <li key={id} className="admin-log-tab-item">
                        <button
                            type="button"
                            value={id}
                            className="admin-log-tab-button"
                            data-test-id={`tabnav-btn-${id}`}
                            aria-selected={tab === id}
                            onClick={this.selectTab}
                        >
                            {tabLabel(id)}
                        </button>
                    </li>
                ))}
            </ul>
        );
    }

    private renderContent(tab: TabId) {
        const {endpoints, actions, mails, requests, errors, cron, jsErrors} = this.props;
        switch (tab) {
            case 'actions':
                return <LogsSection pageUrl={endpoints.actions} initialData={actions.payload} config={actions.gridConfig} filterOptions={actions.filterOptions} />;
            case 'mails':
                return <MailLogSection pageUrl={endpoints.mails} initialData={mails.payload} config={mails.gridConfig} filterOptions={mails.filterOptions} />;
            case 'requests':
                return <AdminRequestLogIsland dates={requests.dates} pageUrl={endpoints.requests} />;
            case 'errors':
                return <AdminErrorsLogIsland dates={errors.dates} pageUrl={endpoints.errors} />;
            case 'cron':
                return <CronLogSection pageUrl={endpoints.cron} initialData={cron.payload} filterOptions={cron.filterOptions} />;
            case 'js-errors':
                return <Suspense fallback={null}><JsErrorLogSection pageUrl={endpoints['js-errors']} initialData={jsErrors.payload} filterOptions={jsErrors.filterOptions} /></Suspense>;
        }
    }

    render() {
        const {tab} = this.useCarburetor(this.ui);
        return (
            <UserDetailContext.Provider value={this.userContext}>
                <div data-test-id="admin-logs-viewer">
                    <PageHeader title={t.Logs_Title()} icon={LOGS_ICON} />
                    <div className="section-soft">
                        {this.renderTabs(tab)}
                        {this.renderContent(tab)}
                    </div>
                </div>
            </UserDetailContext.Provider>
        );
    }
}

export const AdminLogsViewerIsland: React.FC<Props> = props => <AdminLogsViewer {...props} />;
