/**
 * Три короткие вкладки, живущие одним и тем же: галочка или пара полей
 * плюс сохранение.
 *
 * Держать их в одном файле честнее, чем в трёх по двадцать строк: у них
 * общий набор пропсов и общая форма, и разложенные по отдельным файлам они
 * дали бы три места вместо одного, не добавив ясности.
 */
import * as React from 'react';
import {SettingsData, SystemSettingsLabels} from '../types';
import {SaveButton} from './SaveButton';

interface TabProps {
    settings: SettingsData;
    setSettings: React.Dispatch<React.SetStateAction<SettingsData>>;
    labels: SystemSettingsLabels;
    sending: boolean;
}

export const RegistrationTab: React.FC<TabProps> = ({settings, setSettings, labels, sending}) => (
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

    <SaveButton labels={labels} sending={sending} />
</section>
);

export const PenaltyTab: React.FC<TabProps> = ({settings, setSettings, labels, sending}) => (
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

    <SaveButton labels={labels} sending={sending} />
</section>
);

export const ContactsTab: React.FC<TabProps> = ({settings, setSettings, labels, sending}) => (
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

    <SaveButton labels={labels} sending={sending} />
</section>
);
