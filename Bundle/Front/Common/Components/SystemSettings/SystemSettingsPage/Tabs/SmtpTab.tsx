/**
 * Вкладка SMTP: параметры отправки и тестовое письмо.
 *
 * Тестовое письмо живёт рядом с параметрами намеренно: его смысл — «то,
 * что я только что ввёл, работает», и проверять его по другому экрану
 * означало бы сначала сохранить непроверенное.
 */
import * as React from 'react';
import {SettingsData, SystemSettingsLabels, MailTypeOption} from '../types';
import {SaveButton} from './SaveButton';

interface SmtpTabProps {
    settings: SettingsData;
    updateSmtp: (field: keyof SettingsData['smtp'], value: string | boolean) => void;
    labels: SystemSettingsLabels;
    sending: boolean;
    testEmail: string;
    setTestEmail: (v: string) => void;
    testMailType: string;
    setTestMailType: (v: string) => void;
    sendingTestEmail: boolean;
    onSendTestEmail: () => void;
    mailTypes: MailTypeOption[];
}

export const SmtpTab: React.FC<SmtpTabProps> = ({
    settings, updateSmtp, labels, sending,
    testEmail, setTestEmail, testMailType, setTestMailType,
    sendingTestEmail, onSendTestEmail, mailTypes,
}) => (
    <>
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

        <SaveButton labels={labels} sending={sending} />
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
                onClick={onSendTestEmail}
                data-test-id="admin-system-test-email-send"
            >
                {sendingTestEmail ? labels.testEmailSending : labels.testEmailSend}
            </button>
        </div>
    </section>
</>
    </>
);
