import * as React from 'react';
import type {TabProps} from './GeneralTabs';
import {SaveButton} from './SaveButton';

/** Controlled registration settings. */
export class RegistrationTab extends React.Component<TabProps> {
    private readonly handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.props.setSettings(prev => ({...prev, registrationsEnabled: event.target.checked}));
    };

    public render() {
        const {settings, labels, sending} = this.props;
        return (
            <section className="settings-general-panel">
                <div className="settings-general-header">
                    <h2 className="settings-general-title">{labels.registrationTitle}</h2>
                    <p className="settings-general-hint">{labels.registrationHint}</p>
                </div>
                <label className="settings-registration-label">
                    <input type="checkbox" className="settings-registration-checkbox" checked={settings.registrationsEnabled} onChange={this.handleChange} />
                    <span className="settings-registration-text">{labels.registrationEnabled}</span>
                </label>
                <SaveButton labels={labels} sending={sending} />
            </section>
        );
    }
}
