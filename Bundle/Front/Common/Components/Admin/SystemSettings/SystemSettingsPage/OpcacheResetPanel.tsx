/**
 * Сброс OPcache на боевом хосте — кнопка для владельца.
 *
 * Живёт отдельно от формы настроек намеренно: это не настройка, а
 * разовое действие над хостом, и в сохранение формы оно не входит.
 */

import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {useSending} from '@common/hooks/data/useSending';
import {SystemSettingsLabels} from './types';
import {OpcacheResetView} from './OpcacheResetView';

type Props = {url: string; labels: SystemSettingsLabels};

class OpcacheResetController extends AntiHookComponent<Props & ReturnType<typeof useSending>> {
    private readonly reset = async (): Promise<void> => {
        const {url, labels} = this.props;
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
    };

    private readonly handleClick = (): void => {
        void this.props.withSending(this.reset);
    };

    public render() {
        return <OpcacheResetView labels={this.props.labels} sending={this.props.sending} onReset={this.handleClick} />;
    }
}

export function OpcacheResetPanel(props: Props) {
    const sending = useSending();
    return <OpcacheResetController {...props} {...sending} />;
}

