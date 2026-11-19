import * as React from 'react';
import type {SystemSettingsLabels} from './types';

interface Props {
    labels: SystemSettingsLabels;
    sending: boolean;
    onReset: () => void;
}

/** Controlled presentation; the reset request belongs to OpcacheResetPanel. */
export function OpcacheResetView({labels, sending, onReset}: Props) {
    return (
        <section className="settings-host-panel" data-test-id="opcache-reset-section">
            <h2 className="settings-host-title">{labels.opcacheResetTitle ?? 'OPcache'}</h2>
            <p className="settings-host-hint">{labels.opcacheResetHint ?? 'Reset the FPM PHP OPcache after a deploy.'}</p>
            <button type="button" data-test-id="opcache-reset-btn" className="settings-host-button" onClick={onReset} disabled={sending}>
                {sending ? labels.saving : (labels.opcacheResetBtn ?? 'Reset OPcache')}
            </button>
        </section>
    );
}
