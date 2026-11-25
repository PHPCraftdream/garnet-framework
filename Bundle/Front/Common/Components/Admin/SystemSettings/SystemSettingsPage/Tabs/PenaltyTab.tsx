import * as React from 'react';
import type {TabProps} from './GeneralTabs';
import {SaveButton} from './SaveButton';

/** Controlled cancellation-penalty settings. */
export class PenaltyTab extends React.Component<TabProps> {
    /** Clamps the entered percentage and updates cancellation settings. */
    private readonly handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        const raw = event.target.value === '' ? 0 : parseInt(event.target.value, 10);
        const clamped = Number.isFinite(raw) ? Math.max(0, Math.min(100, raw)) : 0;
        this.props.setSettings(prev => ({...prev, cancellationPenaltyPercent: clamped}));
    };

    /** Renders the cancellation penalty input and save action. */
    public render() {
        const {settings, labels, sending} = this.props;
        return (
            <section className="settings-general-panel">
                <div className="settings-general-header">
                    <h2 className="settings-general-title">{labels.cancellationPenaltyTitle}</h2>
                    <p className="settings-general-hint">{labels.cancellationPenaltyHint}</p>
                </div>
                <label className="settings-penalty-label">
                    <span className="settings-penalty-caption">{labels.cancellationPenaltyLabel}</span>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        inputMode="numeric"
                        className="settings-penalty-input"
                        value={Number.isFinite(settings.cancellationPenaltyPercent)
                            ? settings.cancellationPenaltyPercent
                            : 0}
                        onChange={this.handleChange}
                    />
                </label>
                <SaveButton labels={labels} sending={sending} />
            </section>
        );
    }
}
