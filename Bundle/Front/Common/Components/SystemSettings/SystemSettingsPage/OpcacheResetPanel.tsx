/**
 * Сброс OPcache на боевом хосте — кнопка для владельца.
 *
 * Живёт отдельно от формы настроек намеренно: это не настройка, а
 * разовое действие над хостом, и в сохранение формы оно не входит.
 */

import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {showToast} from '@common/Components/GlobalToast';
import {useSending} from '@common/hooks/useSending';
import {SystemSettingsLabels} from './types';

export const OpcacheResetPanel: React.FC<{url: string; labels: SystemSettingsLabels}> = ({url, labels}) => {
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

