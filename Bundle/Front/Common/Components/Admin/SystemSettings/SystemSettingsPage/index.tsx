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
import {useSending} from '@common/hooks/data/useSending';
import {EntityHistoryRow} from '@common/Components/Admin/EntityHistory/EntityHistoryTable';
import {EntityHistoryDetailModal} from '@common/Components/Admin/EntityHistory/EntityHistoryDetailModal';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {Settings} from 'lucide-react';
import {SettingsData, SystemSettingsPageProps, TabKey} from './types';
import {OpcacheResetPanel} from './OpcacheResetPanel';
import {VersionInfoPanel} from './VersionInfoPanel';
import {SmtpTab} from './Tabs/SmtpTab';
import {RegistrationTab, PenaltyTab, ContactsTab} from './Tabs/GeneralTabs';
import {SeoTab} from './Tabs/SeoTab';
import {HistoryTab} from './Tabs/HistoryTab';

export const SystemSettingsPage: React.FC<SystemSettingsPageProps> = ({settings: initialSettings, saveUrl, testEmailUrl, historyListUrl, uploadImageUrl, deleteImageUrl, opcacheResetUrl, labels, mailTypes = [], versionInfo}) => {
    const [settings, setSettings] = React.useState<SettingsData>(initialSettings);
    const [activeTab, setActiveTab] = React.useState<TabKey>('smtp');
    const [testEmail, setTestEmail] = React.useState('');
    const [testMailType, setTestMailType] = React.useState<string>('');
    const [message, setMessage] = React.useState<{type: 'success' | 'error'; text: string} | null>(null);
    const [historyRows, setHistoryRows] = React.useState<EntityHistoryRow[]>([]);
    const [historyLoading, setHistoryLoading] = React.useState<boolean>(false);
    const [historyDetail, setHistoryDetail] = React.useState<EntityHistoryRow | null>(null);
    const {sending, withSending} = useSending();
    const {sending: sendingTestEmail, withSending: withSendingTestEmail} = useSending();

    const loadHistory = React.useCallback(async (): Promise<void> => {
        if (!historyListUrl) return;
        setHistoryLoading(true);
        try {
            const resp = await sendPost<{limit: number}, {success: boolean; rows: EntityHistoryRow[]}>(
                historyListUrl,
                {limit: 200},
            );
            setHistoryRows(resp.rows ?? []);
        } finally {
            setHistoryLoading(false);
        }
    }, [historyListUrl]);

    React.useEffect(() => {
        if (activeTab === 'history') void loadHistory();
    }, [activeTab, loadHistory]);

    const updateSmtp = (field: keyof SettingsData['smtp'], value: string | boolean) => {
        setSettings((prev) => ({
            ...prev,
            smtp: {
                ...prev.smtp,
                [field]: value,
            },
        }));
    };

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void withSending(async () => {
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
                    setMessage({type: 'error', text: errorText});
                    showToast(errorText, 'danger');
                    return;
                }

                const nextSettings = (response as any)?.settings as SettingsData | undefined;
                if (nextSettings) {
                    setSettings(nextSettings);
                }

                setMessage({type: 'success', text: labels.saved});
                showToast(labels.saved, 'success');
            } catch (err: any) {
                const errorText = err?.message || labels.error;
                setMessage({type: 'error', text: errorText});
                showToast(errorText, 'danger');
            }
        });
    };

    const handleSendTestEmail = () => {
        void withSendingTestEmail(async () => {
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
            }
        });
    };

    const tab = (key: TabKey, label: string, testId?: string) => (
        <li className="mr-1">
            <button
                type="button"
                {...(testId ? {'data-test-id': testId} : {})}
                className={`tab-link ${activeTab === key ? 'tab-link-active' : ''}`}
                onClick={() => setActiveTab(key)}
            >
                {label}
            </button>
        </li>
    );

    return (
        <div className="max-w-4xl space-y-6" data-test-id="admin-system-settings">
            <PageHeader title={labels.title} subtitle={labels.subtitle} icon={<Settings size={22} aria-hidden="true" />} />

            <div className="section-soft">
            {message && (
                <div className={`rounded-lg border border-default px-4 py-3 text-sm ${message.type === 'success' ? 'bg-success-subtle text-on-surface' : 'bg-danger-subtle text-on-surface'}`}>
                    {message.text}
                </div>
            )}

            <ul className="tab-bar">
                {tab('smtp', labels.smtpTab)}
                {tab('registration', labels.registrationTab)}
                {labels.cancellationPenaltyTab && tab('penalty', labels.cancellationPenaltyTab)}
                {labels.supportContactsTab && tab('contacts', labels.supportContactsTab)}
                {labels.seoTab && tab('seo', labels.seoTab)}
                {historyListUrl && tab('history', labels.historyTab, 'system-settings-tab-history')}
            </ul>

            <form className="space-y-6" onSubmit={handleSubmit}>
                {activeTab === 'smtp' && (
                    <SmtpTab
                        settings={settings}
                        updateSmtp={updateSmtp}
                        labels={labels}
                        sending={sending}
                        testEmail={testEmail}
                        setTestEmail={setTestEmail}
                        testMailType={testMailType}
                        setTestMailType={setTestMailType}
                        sendingTestEmail={sendingTestEmail}
                        onSendTestEmail={handleSendTestEmail}
                        mailTypes={mailTypes}
                    />
                )}

                {activeTab === 'registration' && (
                    <RegistrationTab settings={settings} setSettings={setSettings} labels={labels} sending={sending} />
                )}

                {activeTab === 'penalty' && labels.cancellationPenaltyTab && (
                    <PenaltyTab settings={settings} setSettings={setSettings} labels={labels} sending={sending} />
                )}

                {activeTab === 'contacts' && labels.supportContactsTab && (
                    <ContactsTab settings={settings} setSettings={setSettings} labels={labels} sending={sending} />
                )}

                {activeTab === 'seo' && labels.seoTab && (
                    <SeoTab
                        settings={settings}
                        setSettings={setSettings}
                        labels={labels}
                        sending={sending}
                        uploadImageUrl={uploadImageUrl}
                        deleteImageUrl={deleteImageUrl}
                    />
                )}
            </form>

            {opcacheResetUrl && (
                <OpcacheResetPanel url={opcacheResetUrl} labels={labels} />
            )}

            {versionInfo && (
                <VersionInfoPanel info={versionInfo} labels={labels} />
            )}

            {activeTab === 'history' && historyListUrl && (
                <HistoryTab
                    labels={labels}
                    historyRows={historyRows}
                    historyLoading={historyLoading}
                    onRefresh={() => void loadHistory()}
                    onRowClick={setHistoryDetail}
                />
            )}
            </div>

            {historyDetail && (
                <EntityHistoryDetailModal
                    row={historyDetail}
                    onClose={() => setHistoryDetail(null)}
                />
            )}
        </div>
    );
};
