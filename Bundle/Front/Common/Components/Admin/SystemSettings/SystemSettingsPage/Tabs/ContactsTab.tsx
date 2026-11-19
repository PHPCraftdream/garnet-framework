import * as React from 'react';
import type {TabProps} from './GeneralTabs';
import {SaveButton} from './SaveButton';

/** Controlled support-contact settings. */
export class ContactsTab extends React.Component<TabProps> {
    private readonly handleEmail = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.props.setSettings(prev => ({
            ...prev,
            supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), email: event.target.value},
        }));
    };

    private readonly handlePhone = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.props.setSettings(prev => ({
            ...prev,
            supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), phone: event.target.value},
        }));
    };

    private readonly handleTelegram = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.props.setSettings(prev => ({
            ...prev,
            supportContacts: {...(prev.supportContacts ?? {email: '', phone: '', telegram: ''}), telegram: event.target.value},
        }));
    };

    private renderFields() {
        const {settings, labels} = this.props;
        return (
            <div className="settings-fields-grid">
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.supportContactEmail}</span>
                    <input type="email" className="settings-native-field" value={settings.supportContacts?.email ?? ''} onChange={this.handleEmail} />
                </label>
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.supportContactPhone}</span>
                    <input className="settings-native-field" value={settings.supportContacts?.phone ?? ''} onChange={this.handlePhone} />
                </label>
                <label className="settings-field-label-wide">
                    <span className="settings-field-caption">{labels.supportContactTelegram}</span>
                    <input className="settings-native-field" placeholder="@username" value={settings.supportContacts?.telegram ?? ''} onChange={this.handleTelegram} />
                </label>
            </div>
        );
    }

    public render() {
        const {labels, sending} = this.props;
        return (
            <section className="settings-general-panel">
                <div className="settings-general-header">
                    <h2 className="settings-general-title">{labels.supportContactsTitle}</h2>
                    <p className="settings-general-hint">{labels.supportContactsHint}</p>
                </div>
                {this.renderFields()}
                <SaveButton labels={labels} sending={sending} />
            </section>
        );
    }
}
