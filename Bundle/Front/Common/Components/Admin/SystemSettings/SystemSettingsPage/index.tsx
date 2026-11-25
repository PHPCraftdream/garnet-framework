/**
 * Экран системных настроек: состояние, сохранение и переключение вкладок.
 *
 * Файл называется index, чтобы путь импорта остался прежним: приложение
 * реэкспортирует экран по @common/Components/SystemSettings/SystemSettingsPage,
 * и превращение файла в папку не должно этого касаться.
 *
 * Все вкладки лежат внутри одной формы и сохраняются одним запросом —
 * поэтому состояние живёт здесь, а вкладки получают его пропсами. Иначе
 * каждая вкладка вела бы свою половину правды об одном и том же объекте
 * настроек.
 */
import * as React from 'react';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {EntityHistoryRow} from '@common/Components/Admin/EntityHistory/EntityHistoryTable';
import {EntityHistoryDetailModal} from '@common/Components/Admin/EntityHistory/EntityHistoryDetailModal';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {Settings} from 'lucide-react';
import {SettingsData, SystemSettingsPageProps, TabKey} from './types';
import {OpcacheResetPanel} from './OpcacheResetPanel';
import {VersionInfoPanel} from './VersionInfoPanel';
import {SettingsTabStrip} from './SettingsTabStrip';
import {SmtpTab} from './Tabs/SmtpTab';
import {RegistrationTab, PenaltyTab, ContactsTab} from './Tabs/GeneralTabs';
import {SeoTab} from './Tabs/SeoTab';
import {HistoryTab} from './Tabs/HistoryTab';

type PageState = {
    settings: SettingsData;
    activeTab: TabKey;
    testEmail: string;
    testMailType: string;
    message: {type: 'success' | 'error'; text: string} | null;
    historyRows: EntityHistoryRow[];
    historyLoading: boolean;
    historyDetail: EntityHistoryRow | null;
    sending: boolean;
    sendingTestEmail: boolean;
};

export class SystemSettingsPage extends AntiHookComponent<SystemSettingsPageProps> {
    /** Stores the settings-page state and its loading and submission flags. */
    private readonly page = new Carburetor<PageState>({
        settings: this.props.settings,
        activeTab: 'smtp',
        testEmail: '',
        testMailType: '',
        message: null,
        historyRows: [],
        historyLoading: false,
        historyDetail: null,
        sending: false,
        sendingTestEmail: false,
    });
    /** Selects the state fields consumed by the page render. */
    private readonly view = this.connectSelection(() => this.page, data => ({
        settings: data.settings,
        activeTab: data.activeTab,
        testEmail: data.testEmail,
        testMailType: data.testMailType,
        message: data.message,
        historyRows: data.historyRows,
        historyLoading: data.historyLoading,
        historyDetail: data.historyDetail,
        sending: data.sending,
        sendingTestEmail: data.sendingTestEmail,
    }));
    /** Prevents concurrent settings submissions. */
    private sendingGuard = false;
    /** Prevents concurrent test-email requests. */
    private testEmailGuard = false;
    /** Tracks the inputs used for the last history load. */
    private historyDependencies: {activeTab: TabKey; historyListUrl: string | undefined} | null = null;

    /** Starts loading history when the page mounts. */
    public componentDidMount(): void {
        super.componentDidMount();
        this.syncHistory();
    }

    /** Reloads history when the page's relevant props or state change. */
    public componentDidUpdate(previousProps: Readonly<SystemSettingsPageProps>): void {
        super.componentDidUpdate(previousProps);
        this.syncHistory();
    }

    /** Clears history dependencies before unmounting. */
    public componentWillUnmount(): void {
        this.historyDependencies = null;
        super.componentWillUnmount();
    }

    /** Loads history when its tab or endpoint changes. */
    private syncHistory(): void {
        const activeTab = this.page.getData().activeTab;
        const {historyListUrl} = this.props;
        const previous = this.historyDependencies;
        if (previous && previous.activeTab === activeTab && previous.historyListUrl === historyListUrl) return;
        this.historyDependencies = {activeTab, historyListUrl};
        if (activeTab === 'history') void this.loadHistory();
    }

    /** Replaces the settings held by the page. */
    private readonly setSettings: React.Dispatch<React.SetStateAction<SettingsData>> = (value) => {
        const state = this.page.getData();
        const settings = typeof value === 'function' ? value(this.page.snapshot().settings) : value;
        this.page.setData({...state, settings});
    };

    /** Changes the selected settings tab. */
    private readonly setActiveTab = (activeTab: TabKey): void => {
        this.page.setData({...this.page.getData(), activeTab});
    };

    /** Updates the test-email address in page state. */
    private readonly setTestEmail: React.Dispatch<React.SetStateAction<string>> = (value) => {
        const state = this.page.getData();
        this.page.setData({...state, testEmail: typeof value === 'function' ? value(state.testEmail) : value});
    };

    /** Updates the selected test-email message type. */
    private readonly setTestMailType: React.Dispatch<React.SetStateAction<string>> = (value) => {
        const state = this.page.getData();
        this.page.setData({...state, testMailType: typeof value === 'function' ? value(state.testMailType) : value});
    };

    /** Selects or clears the history row shown in the detail modal. */
    private readonly setHistoryDetail = (historyDetail: EntityHistoryRow | null): void => {
        this.page.setData({...this.page.getData(), historyDetail});
    };

    /** Closes the selected history-row detail. */
    private readonly closeHistoryDetail = (): void => { this.setHistoryDetail(null); };
    /** Reloads the history list. */
    private readonly refreshHistory = (): void => { void this.loadHistory(); };

    /** Fetches and stores the history rows when an endpoint is configured. */
    private readonly loadHistory = async (): Promise<void> => {
        const {historyListUrl} = this.props;
        if (!historyListUrl) return;
        this.page.setData({...this.page.getData(), historyLoading: true});
        try {
            const resp = await sendPost<
                {limit: number},
                {success: boolean; rows: EntityHistoryRow[]}
            >(historyListUrl, {limit: 200});
            this.page.setData({...this.page.getData(), historyRows: resp.rows ?? []});
        } finally {
            this.page.setData({...this.page.getData(), historyLoading: false});
        }
    };

    /** Updates one SMTP setting while preserving the other SMTP values. */
    private readonly updateSmtp = (field: keyof SettingsData['smtp'], value: string | boolean): void => {
        this.setSettings(prev => ({...prev, smtp: {...prev.smtp, [field]: value}}));
    };

    /** Saves the current settings and reports the result to the user. */
    private readonly handleSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (this.sendingGuard) return;
        this.sendingGuard = true;
        const {settings} = this.page.getData();
        const {saveUrl, labels} = this.props;
        this.page.setData({...this.page.getData(), sending: true});
        void (async () => {
            try {
                const response = await sendPost<any, {success: boolean; settings: SettingsData}>(saveUrl, {
                    registrations_enabled: settings.registrationsEnabled ? 1 : 0,
                    smtp_enabled: settings.smtp.enabled ? 1 : 0,
                    smtp_scheme: settings.smtp.scheme,
                    smtp_host: settings.smtp.host,
                    smtp_port: settings.smtp.port,
                    smtp_user: settings.smtp.user,
                    smtp_password: settings.smtp.password,
                    smtp_from: settings.smtp.from,
                    smtp_verify_peer: settings.smtp.verify_peer ? 1 : 0,
                    cancellation_penalty_percent: settings.cancellationPenaltyPercent,
                    support_contact_email: settings.supportContacts?.email ?? '',
                    support_contact_phone: settings.supportContacts?.phone ?? '',
                    support_contact_telegram: settings.supportContacts?.telegram ?? '',
                    seo_description: settings.seo?.description ?? '',
                    seo_og_image: settings.seo?.ogImage ?? '',
                    seo_twitter_site: settings.seo?.twitterSite ?? '',
                });
                if ((response as any)?.error) {
                    const errorText = (response as any).error || labels.error;
                    this.page.setData({...this.page.getData(), message: {type: 'error', text: errorText}});
                    showToast(errorText, 'danger');
                    return;
                }
                const nextSettings = (response as any)?.settings as SettingsData | undefined;
                if (nextSettings) this.setSettings(nextSettings);
                this.page.setData({...this.page.getData(), message: {type: 'success', text: labels.saved}});
                showToast(labels.saved, 'success');
            } catch (err: any) {
                const errorText = err?.message || labels.error;
                this.page.setData({...this.page.getData(), message: {type: 'error', text: errorText}});
                showToast(errorText, 'danger');
            } finally {
                this.sendingGuard = false;
                this.page.setData({...this.page.getData(), sending: false});
            }
        })();
    };

    /** Sends a test email using the current SMTP settings. */
    private readonly handleSendTestEmail = (): void => {
        if (this.testEmailGuard) return;
        this.testEmailGuard = true;
        const {settings, testEmail, testMailType} = this.page.getData();
        const {testEmailUrl, labels} = this.props;
        this.page.setData({...this.page.getData(), sendingTestEmail: true});
        void (async () => {
            try {
                const response = await sendPost<any, {success: boolean; message?: string}>(testEmailUrl, {
                    test_email: testEmail,
                    mail_type: testMailType,
                    smtp_enabled: settings.smtp.enabled ? 1 : 0,
                    smtp_scheme: settings.smtp.scheme,
                    smtp_host: settings.smtp.host,
                    smtp_port: settings.smtp.port,
                    smtp_user: settings.smtp.user,
                    smtp_password: settings.smtp.password,
                    smtp_from: settings.smtp.from,
                    smtp_verify_peer: settings.smtp.verify_peer ? 1 : 0,
                });
                if ((response as any)?.error) {
                    const errorText = (response as any).error || labels.error;
                    showToast(errorText, 'danger');
                    return;
                }
                const successText = (response as any)?.message || labels.testEmailSuccess;
                showToast(successText, 'success');
            } catch (err: any) {
                const errorText = err?.response?.error || err?.message || labels.error;
                showToast(errorText, 'danger');
            } finally {
                this.testEmailGuard = false;
                this.page.setData({...this.page.getData(), sendingTestEmail: false});
            }
        })();
    };

    /** Renders the active settings form tab. */
    private renderForm(state: PageState) {
        const {settings, activeTab, testEmail, testMailType, sending, sendingTestEmail} = state;
        const {labels, uploadImageUrl, deleteImageUrl, mailTypes = []} = this.props;
        return (
            <form className="settings-page-form" onSubmit={this.handleSubmit}>
                {activeTab === 'smtp' && (
                    <SmtpTab
                        settings={settings}
                        updateSmtp={this.updateSmtp}
                        labels={labels}
                        sending={sending}
                        testEmail={testEmail}
                        setTestEmail={this.setTestEmail}
                        testMailType={testMailType}
                        setTestMailType={this.setTestMailType}
                        sendingTestEmail={sendingTestEmail}
                        onSendTestEmail={this.handleSendTestEmail}
                        mailTypes={mailTypes}
                    />
                )}
                {activeTab === 'registration' && (
                    <RegistrationTab
                        settings={settings}
                        setSettings={this.setSettings}
                        labels={labels}
                        sending={sending}
                    />
                )}
                {activeTab === 'penalty' && labels.cancellationPenaltyTab && (
                    <PenaltyTab settings={settings} setSettings={this.setSettings} labels={labels} sending={sending} />
                )}
                {activeTab === 'contacts' && labels.supportContactsTab && (
                    <ContactsTab settings={settings} setSettings={this.setSettings} labels={labels} sending={sending} />
                )}
                {activeTab === 'seo' && labels.seoTab && (
                    <SeoTab
                        settings={settings}
                        setSettings={this.setSettings}
                        labels={labels}
                        sending={sending}
                        uploadImageUrl={uploadImageUrl}
                        deleteImageUrl={deleteImageUrl}
                    />
                )}
            </form>
        );
    }

    /** Renders optional panels and the history tab. */
    private renderPanels(state: PageState) {
        const {activeTab, historyRows, historyLoading} = state;
        const {opcacheResetUrl, versionInfo, historyListUrl, labels} = this.props;
        return (
            <>
                {opcacheResetUrl && <OpcacheResetPanel url={opcacheResetUrl} labels={labels} />}
                {versionInfo && <VersionInfoPanel info={versionInfo} labels={labels} />}
                {activeTab === 'history' && historyListUrl && (
                    <HistoryTab
                        labels={labels}
                        historyRows={historyRows}
                        historyLoading={historyLoading}
                        onRefresh={this.refreshHistory}
                        onRowClick={this.setHistoryDetail}
                    />
                )}
            </>
        );
    }

    /** Renders the page's message, tab strip, form, and panels. */
    private renderSection(state: PageState) {
        const {message, activeTab} = state;
        const {labels, historyListUrl} = this.props;
        return (
            <div className="section-soft">
                {message && (
                    <div className={message.type === 'success' ? 'settings-page-success' : 'settings-page-error'}>
                        {message.text}
                    </div>
                )}
                <SettingsTabStrip
                    activeTab={activeTab}
                    labels={labels}
                    historyListUrl={historyListUrl}
                    onSelect={this.setActiveTab}
                />
                {this.renderForm(state)}
                {this.renderPanels(state)}
            </div>
        );
    }

    /** Renders the system-settings page. */
    public render() {
        const state = this.view() as PageState;
        const {labels} = this.props;
        return (
            <div className="settings-page" data-test-id="admin-system-settings">
                <PageHeader
                    title={labels.title}
                    subtitle={labels.subtitle}
                    icon={<Settings size={22} aria-hidden="true" />}
                />
                {this.renderSection(state)}
                {state.historyDetail && (
                    <EntityHistoryDetailModal row={state.historyDetail} onClose={this.closeHistoryDetail} />
                )}
            </div>
        );
    }
}
