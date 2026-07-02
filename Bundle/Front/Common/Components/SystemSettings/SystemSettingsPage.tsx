import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {showToast} from '@common/Components/GlobalToast';
import {useSending} from '@common/hooks/useSending';
import {EntityHistoryRow, EntityHistoryTable} from '@common/Components/EntityHistory/EntityHistoryTable';
import {EntityHistoryDetailModal} from '@common/Components/EntityHistory/EntityHistoryDetailModal';
import {PageHeader} from '@common/Components/PageHeader';
import {ImageUploadField} from '@common/Components/ImageUploadField';
import {Settings} from 'lucide-react';


export type SettingsData = {
    registrationsEnabled: boolean;
    cancellationPenaltyPercent: number;
    supportContacts: {
        email: string;
        phone: string;
        telegram: string;
    };
    smtp: {
        enabled: boolean;
        scheme: string;
        host: string;
        port: string;
        user: string;
        password: string;
        from: string;
        verify_peer: boolean;
    };
    seo?: {
        description?: string;
        ogImage?: string;
        twitterSite?: string;
    };
};

export type SystemSettingsLabels = {
    title: string;
    subtitle: string;
    registrationTitle: string;
    registrationTab: string;
    registrationEnabled: string;
    registrationHint: string;
    smtpTitle: string;
    smtpTab: string;
    smtpHint: string;
    smtpEnabled: string;
    smtpEnabledHint: string;
    smtpVerifyPeer: string;
    smtpScheme: string;
    smtpHost: string;
    smtpPort: string;
    smtpUser: string;
    smtpPassword: string;
    smtpFrom: string;
    save: string;
    saving: string;
    saved: string;
    testEmailTitle: string;
    testEmailHint: string;
    testEmailLabel: string;
    testEmailPlaceholder: string;
    testEmailSend: string;
    testEmailSending: string;
    testEmailSuccess: string;
    error: string;
    cancellationPenaltyTab?: string;
    cancellationPenaltyTitle?: string;
    cancellationPenaltyLabel?: string;
    cancellationPenaltyHint?: string;
    cancellationPenaltyInvalid?: string;
    testEmailType?: string;
    testEmailTypeGeneric?: string;
    supportContactsTab?: string;
    supportContactsTitle?: string;
    supportContactsHint?: string;
    supportContactEmail?: string;
    supportContactPhone?: string;
    supportContactTelegram?: string;
    seoTab?: string;
    seoTitle?: string;
    seoHint?: string;
    seoDescription?: string;
    seoDescriptionHint?: string;
    seoOgImage?: string;
    seoOgImageHint?: string;
    seoOgImageUpload?: string;
    seoOgImageRemove?: string;
    seoOgImageRemoveConfirm?: string;
    seoTwitterSite?: string;
    seoTwitterSitePlaceholder?: string;
    historyTab: string;
    historyTitle: string;
    historyHint: string;
    historyEmpty: string;
    historyLoading: string;
    historyRefresh: string;
    opcacheResetTitle?: string;
    opcacheResetHint?: string;
    opcacheResetBtn?: string;
    opcacheResetSuccess?: string;
    opcacheResetUnavailable?: string;
};

const OpcacheResetPanel: React.FC<{url: string; labels: SystemSettingsLabels}> = ({url, labels}) => {
    const {sending, withSending} = useSending();
    const handleClick = (): void => {
        void withSending(async () => {
            try {
                const r = await sendPost<Record<string, never>, {success?: boolean; opcache_reset?: boolean; error?: string; sapi?: string}>(url, {});
                if (r?.success) {
                    showToast(labels.opcacheResetSuccess ?? 'OPcache reset OK', 'success');
                } else {
                    showToast(r?.error ?? labels.opcacheResetUnavailable ?? 'OPcache not available', 'danger');
                }
            } catch (err) {
                showToast(err instanceof Error ? err.message : labels.error, 'danger');
            }
        });
    };
    return (
        <section className="rounded-lg border border-default bg-surface p-5 mt-6" data-test-id="opcache-reset-section">
            <h2 className="text-lg font-semibold text-on-surface mb-2">
                {labels.opcacheResetTitle ?? 'OPcache'}
            </h2>
            <p className="text-sm text-muted mb-3">
                {labels.opcacheResetHint ?? 'Reset the FPM PHP OPcache after a deploy.'}
            </p>
            <button
                type="button"
                data-test-id="opcache-reset-btn"
                className="btn btn-outline-primary"
                onClick={handleClick}
                disabled={sending}
            >
                {sending ? labels.saving : (labels.opcacheResetBtn ?? 'Reset OPcache')}
            </button>
        </section>
    );
};

export interface MailTypeOption {
    id: string;
    label: string;
}

export interface SystemSettingsPageProps {
    settings: SettingsData;
    saveUrl: string;
    testEmailUrl: string;
    /** Optional endpoint for the audit-log "History" tab. When omitted, the tab is hidden. */
    historyListUrl?: string;
    /** Endpoints for the SEO OG-image upload field. */
    uploadImageUrl?: string;
    deleteImageUrl?: string;
    /** Owner-only OPcache reset endpoint. When omitted, the button is hidden. */
    opcacheResetUrl?: string;
    labels: SystemSettingsLabels;
    mailTypes?: MailTypeOption[];
}

type TabKey = 'smtp' | 'registration' | 'penalty' | 'contacts' | 'seo' | 'history';

export const SystemSettingsPage: React.FC<SystemSettingsPageProps> = ({settings: initialSettings, saveUrl, testEmailUrl, historyListUrl, uploadImageUrl, deleteImageUrl, opcacheResetUrl, labels, mailTypes = []}) => {
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
                <li className="mr-1">
                    <button
                        type="button"
                        className={`tab-link ${activeTab === 'smtp' ? 'tab-link-active' : ''}`}
                        onClick={() => setActiveTab('smtp')}
                    >
                        {labels.smtpTab}
                    </button>
                </li>
                <li className="mr-1">
                    <button
                        type="button"
                        className={`tab-link ${activeTab === 'registration' ? 'tab-link-active' : ''}`}
                        onClick={() => setActiveTab('registration')}
                    >
                        {labels.registrationTab}
                    </button>
                </li>
                {labels.cancellationPenaltyTab && (
                    <li className="mr-1">
                        <button
                            type="button"
                            className={`tab-link ${activeTab === 'penalty' ? 'tab-link-active' : ''}`}
                            onClick={() => setActiveTab('penalty')}
                        >
                            {labels.cancellationPenaltyTab}
                        </button>
                    </li>
                )}
                {labels.supportContactsTab && (
                    <li className="mr-1">
                        <button
                            type="button"
                            className={`tab-link ${activeTab === 'contacts' ? 'tab-link-active' : ''}`}
                            onClick={() => setActiveTab('contacts')}
                        >
                            {labels.supportContactsTab}
                        </button>
                    </li>
                )}
                {labels.seoTab && (
                    <li className="mr-1">
                        <button
                            type="button"
                            className={`tab-link ${activeTab === 'seo' ? 'tab-link-active' : ''}`}
                            onClick={() => setActiveTab('seo')}
                        >
                            {labels.seoTab}
                        </button>
                    </li>
                )}
                {historyListUrl && (
                    <li className="mr-1">
                        <button
                            type="button"
                            data-test-id="system-settings-tab-history"
                            className={`tab-link ${activeTab === 'history' ? 'tab-link-active' : ''}`}
                            onClick={() => setActiveTab('history')}
                        >
                            {labels.historyTab}
                        </button>
                    </li>
                )}
            </ul>

            <form className="space-y-6" onSubmit={handleSubmit}>
                {activeTab === 'smtp' && (
                    <>
                        <section className="rounded-lg border border-default bg-surface p-5">
                            <div className="mb-4">
                                <h2 className="text-lg font-semibold text-on-surface">{labels.smtpTitle}</h2>
                                <p className="mt-1 text-sm text-secondary">{labels.smtpHint}</p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpScheme}</span>
                                    <select
                                        className="form-control w-full border-default"
                                        value={settings.smtp.scheme}
                                        onChange={(e) => updateSmtp('scheme', e.target.value)}
                                    >
                                        <option value="smtp">smtp</option>
                                        <option value="smtps">smtps</option>
                                    </select>
                                </label>
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpHost}</span>
                                    <input className="form-control w-full border-default" value={settings.smtp.host} onChange={(e) => updateSmtp('host', e.target.value)} />
                                </label>
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpPort}</span>
                                    <input className="form-control w-full border-default" inputMode="numeric" value={settings.smtp.port} onChange={(e) => updateSmtp('port', e.target.value)} />
                                </label>
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpUser}</span>
                                    <input className="form-control w-full border-default" value={settings.smtp.user} onChange={(e) => updateSmtp('user', e.target.value)} />
                                </label>
                                <label className="block md:col-span-2">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpPassword}</span>
                                    <input type="password" className="form-control w-full border-default" value={settings.smtp.password} onChange={(e) => updateSmtp('password', e.target.value)} />
                                </label>
                                <label className="block md:col-span-2">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.smtpFrom}</span>
                                    <input className="form-control w-full border-default" value={settings.smtp.from} onChange={(e) => updateSmtp('from', e.target.value)} />
                                </label>
                            </div>

                            <div className="mt-4 space-y-3">
                                <label className="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="mt-1"
                                        checked={settings.smtp.enabled}
                                        onChange={(e) => updateSmtp('enabled', e.target.checked)}
                                    />
                                    <span>
                                        <span className="block font-medium text-on-surface">{labels.smtpEnabled}</span>
                                        <span className="block text-sm text-secondary">{labels.smtpEnabledHint}</span>
                                    </span>
                                </label>

                                <label className="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="mt-1"
                                        checked={settings.smtp.verify_peer}
                                        onChange={(e) => updateSmtp('verify_peer', e.target.checked)}
                                    />
                                    <span className="font-medium text-on-surface">{labels.smtpVerifyPeer}</span>
                                </label>
                            </div>

                            <div className="mt-6">
                                <button
                                    type="submit"
                                    className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                    disabled={sending}
                                >
                                    {sending ? labels.saving : labels.save}
                                </button>
                            </div>
                        </section>

                        <section className="rounded-lg border border-default bg-surface p-5">
                            <div className="mb-4">
                                <h2 className="text-lg font-semibold text-on-surface">{labels.testEmailTitle}</h2>
                                <p className="mt-1 text-sm text-secondary">{labels.testEmailHint}</p>
                            </div>

                            <div className="flex flex-col gap-3 md:flex-row md:items-end">
                                <label className="block flex-1">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.testEmailLabel}</span>
                                    <input
                                        type="email"
                                        className="form-control w-full border-default"
                                        placeholder={labels.testEmailPlaceholder}
                                        value={testEmail}
                                        onChange={(e) => setTestEmail(e.target.value)}
                                        data-test-id="admin-system-test-email-input"
                                    />
                                </label>

                                {mailTypes.length > 0 && (
                                    <label className="block md:w-64">
                                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.testEmailType ?? ''}</span>
                                        <select
                                            className="form-control w-full border-default"
                                            value={testMailType}
                                            onChange={(e) => setTestMailType(e.target.value)}
                                            data-test-id="admin-system-test-email-type"
                                        >
                                            <option value="">{labels.testEmailTypeGeneric ?? ''}</option>
                                            {mailTypes.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.label}</option>
                                            ))}
                                        </select>
                                    </label>
                                )}

                                <button
                                    type="button"
                                    className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                    disabled={sendingTestEmail}
                                    onClick={handleSendTestEmail}
                                    data-test-id="admin-system-test-email-send"
                                >
                                    {sendingTestEmail ? labels.testEmailSending : labels.testEmailSend}
                                </button>
                            </div>
                        </section>
                    </>
                )}

                {activeTab === 'registration' && (
                    <section className="rounded-lg border border-default bg-surface p-5">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-on-surface">{labels.registrationTitle}</h2>
                            <p className="mt-1 text-sm text-secondary">{labels.registrationHint}</p>
                        </div>

                        <label className="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={settings.registrationsEnabled}
                                onChange={(e) => setSettings((prev) => ({...prev, registrationsEnabled: e.target.checked}))}
                            />
                            <span className="font-medium text-on-surface">{labels.registrationEnabled}</span>
                        </label>

                        <div className="mt-6">
                            <button
                                type="submit"
                                className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                disabled={sending}
                            >
                                {sending ? labels.saving : labels.save}
                            </button>
                        </div>
                    </section>
                )}

                {activeTab === 'penalty' && labels.cancellationPenaltyTab && (
                    <section className="rounded-lg border border-default bg-surface p-5">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-on-surface">{labels.cancellationPenaltyTitle}</h2>
                            <p className="mt-1 text-sm text-secondary">{labels.cancellationPenaltyHint}</p>
                        </div>

                        <label className="block max-w-xs">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.cancellationPenaltyLabel}</span>
                            <input
                                type="number"
                                min={0}
                                max={100}
                                step={1}
                                inputMode="numeric"
                                className="form-control w-full border-default"
                                value={Number.isFinite(settings.cancellationPenaltyPercent) ? settings.cancellationPenaltyPercent : 0}
                                onChange={(e) => {
                                    const raw = e.target.value === '' ? 0 : parseInt(e.target.value, 10);
                                    const clamped = Number.isFinite(raw) ? Math.max(0, Math.min(100, raw)) : 0;
                                    setSettings((prev) => ({...prev, cancellationPenaltyPercent: clamped}));
                                }}
                            />
                        </label>

                        <div className="mt-6">
                            <button
                                type="submit"
                                className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                disabled={sending}
                            >
                                {sending ? labels.saving : labels.save}
                            </button>
                        </div>
                    </section>
                )}

                {activeTab === 'contacts' && labels.supportContactsTab && (
                    <section className="rounded-lg border border-default bg-surface p-5">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-on-surface">{labels.supportContactsTitle}</h2>
                            <p className="mt-1 text-sm text-secondary">{labels.supportContactsHint}</p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="block">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.supportContactEmail}</span>
                                <input
                                    type="email"
                                    className="form-control w-full border-default"
                                    value={settings.supportContacts?.email ?? ''}
                                    onChange={(e) => setSettings(prev => ({
                                        ...prev,
                                        supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), email: e.target.value}
                                    }))}
                                />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.supportContactPhone}</span>
                                <input
                                    className="form-control w-full border-default"
                                    value={settings.supportContacts?.phone ?? ''}
                                    onChange={(e) => setSettings(prev => ({
                                        ...prev,
                                        supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), phone: e.target.value}
                                    }))}
                                />
                            </label>
                            <label className="block md:col-span-2">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.supportContactTelegram}</span>
                                <input
                                    className="form-control w-full border-default"
                                    placeholder="@username"
                                    value={settings.supportContacts?.telegram ?? ''}
                                    onChange={(e) => setSettings(prev => ({
                                        ...prev,
                                        supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), telegram: e.target.value}
                                    }))}
                                />
                            </label>
                        </div>

                        <div className="mt-6">
                            <button
                                type="submit"
                                className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                disabled={sending}
                            >
                                {sending ? labels.saving : labels.save}
                            </button>
                        </div>
                    </section>
                )}
                {activeTab === 'seo' && labels.seoTab && (
                    <section className="rounded-lg border border-default bg-surface p-5">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold text-on-surface">{labels.seoTitle}</h2>
                            <p className="mt-1 text-sm text-secondary">{labels.seoHint}</p>
                        </div>

                        <div className="grid gap-4">
                            <label className="block">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoDescription}</span>
                                {labels.seoDescriptionHint && (
                                    <span className="mb-1 block text-xs text-secondary">{labels.seoDescriptionHint}</span>
                                )}
                                <textarea
                                    rows={3}
                                    className="form-control w-full border-default"
                                    value={settings.seo?.description ?? ''}
                                    onChange={(e) => setSettings(prev => ({
                                        ...prev,
                                        seo: {...prev.seo, description: e.target.value},
                                    }))}
                                />
                            </label>
                            <div className="block">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoOgImage}</span>
                                {labels.seoOgImageHint && (
                                    <span className="mb-2 block text-xs text-secondary">{labels.seoOgImageHint}</span>
                                )}
                                {uploadImageUrl && deleteImageUrl ? (
                                    <ImageUploadField
                                        value={settings.seo?.ogImage ?? ''}
                                        onChange={(url) => setSettings(prev => ({
                                            ...prev,
                                            seo: {...prev.seo, ogImage: url},
                                        }))}
                                        uploadUrl={uploadImageUrl}
                                        deleteUrl={deleteImageUrl}
                                        uploadLabel={labels.seoOgImageUpload}
                                        removeLabel={labels.seoOgImageRemove}
                                        removeConfirm={labels.seoOgImageRemoveConfirm}
                                        errorLabel={labels.error}
                                        disabled={sending}
                                    />
                                ) : (
                                    <input
                                        type="url"
                                        className="form-control w-full border-default"
                                        value={settings.seo?.ogImage ?? ''}
                                        onChange={(e) => setSettings(prev => ({
                                            ...prev,
                                            seo: {...prev.seo, ogImage: e.target.value},
                                        }))}
                                    />
                                )}
                            </div>
                            <label className="block max-w-xs">
                                <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoTwitterSite}</span>
                                <input
                                    className="form-control w-full border-default"
                                    placeholder={labels.seoTwitterSitePlaceholder ?? '@yoursite'}
                                    value={settings.seo?.twitterSite ?? ''}
                                    onChange={(e) => setSettings(prev => ({
                                        ...prev,
                                        seo: {...prev.seo, twitterSite: e.target.value},
                                    }))}
                                />
                            </label>
                        </div>

                        <div className="mt-6">
                            <button
                                type="submit"
                                className="rounded-lg bg-accent px-4 py-2 font-medium text-accent-text hover:bg-accent-hover disabled:opacity-60"
                                disabled={sending}
                            >
                                {sending ? labels.saving : labels.save}
                            </button>
                        </div>
                    </section>
                )}
            </form>

            {opcacheResetUrl && (
                <OpcacheResetPanel url={opcacheResetUrl} labels={labels} />
            )}

            {activeTab === 'history' && historyListUrl && (
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
                            onClick={() => void loadHistory()}
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
                            onRowClick={setHistoryDetail}
                            showAction={false}
                            dataTestId="system-settings-history-table"
                            rowTestIdPrefix="system-settings-history-row"
                        />
                    )}
                </section>
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
