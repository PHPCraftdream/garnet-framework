/** SMTP settings and the adjacent test-email controls. */
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

const renderMailOption = (option: MailTypeOption) => <option key={option.id} value={option.id}>{option.label}</option>;

export class SmtpTab extends React.Component<SmtpTabProps> {
    private readonly changeScheme = (e: React.ChangeEvent<HTMLSelectElement>): void => { this.props.updateSmtp('scheme', e.target.value); };
    private readonly changeHost = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('host', e.target.value); };
    private readonly changePort = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('port', e.target.value); };
    private readonly changeUser = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('user', e.target.value); };
    private readonly changePassword = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('password', e.target.value); };
    private readonly changeFrom = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('from', e.target.value); };
    private readonly changeEnabled = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('enabled', e.target.checked); };
    private readonly changeVerifyPeer = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.updateSmtp('verify_peer', e.target.checked); };
    private readonly changeTestEmail = (e: React.ChangeEvent<HTMLInputElement>): void => { this.props.setTestEmail(e.target.value); };
    private readonly changeMailType = (e: React.ChangeEvent<HTMLSelectElement>): void => { this.props.setTestMailType(e.target.value); };

    private renderSmtpFields() {
        const {settings, labels} = this.props;
        return (
            <div className="settings-fields-grid">
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.smtpScheme}</span>
                    <select className="settings-native-field" value={settings.smtp.scheme} onChange={this.changeScheme}>
                        <option value="smtp">smtp</option>
                        <option value="smtps">smtps</option>
                    </select>
                </label>
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.smtpHost}</span>
                    <input className="settings-native-field" value={settings.smtp.host} onChange={this.changeHost} />
                </label>
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.smtpPort}</span>
                    <input className="settings-native-field" inputMode="numeric" value={settings.smtp.port} onChange={this.changePort} />
                </label>
                <label className="settings-field-label">
                    <span className="settings-field-caption">{labels.smtpUser}</span>
                    <input className="settings-native-field" value={settings.smtp.user} onChange={this.changeUser} />
                </label>
                <label className="settings-field-label-wide">
                    <span className="settings-field-caption">{labels.smtpPassword}</span>
                    <input type="password" className="settings-native-field" value={settings.smtp.password} onChange={this.changePassword} />
                </label>
                <label className="settings-field-label-wide">
                    <span className="settings-field-caption">{labels.smtpFrom}</span>
                    <input className="settings-native-field" value={settings.smtp.from} onChange={this.changeFrom} />
                </label>
            </div>
        );
    }

    private renderSmtpOptions() {
        const {settings, labels} = this.props;
        return (
            <div className="settings-smtp-options">
                <label className="settings-smtp-option">
                    <input type="checkbox" className="settings-smtp-checkbox" checked={settings.smtp.enabled} onChange={this.changeEnabled} />
                    <span>
                        <span className="settings-smtp-enabled-title">{labels.smtpEnabled}</span>
                        <span className="settings-smtp-enabled-hint">{labels.smtpEnabledHint}</span>
                    </span>
                </label>
                <label className="settings-smtp-option">
                    <input type="checkbox" className="settings-smtp-checkbox" checked={settings.smtp.verify_peer} onChange={this.changeVerifyPeer} />
                    <span className="settings-smtp-option-title">{labels.smtpVerifyPeer}</span>
                </label>
            </div>
        );
    }

    private renderTestEmail() {
        const {labels, testEmail, testMailType, mailTypes, sendingTestEmail, onSendTestEmail} = this.props;
        return (
            <section className="settings-general-panel">
                <div className="settings-general-header">
                    <h2 className="settings-general-title">{labels.testEmailTitle}</h2>
                    <p className="settings-general-hint">{labels.testEmailHint}</p>
                </div>
                <div className="settings-smtp-test-row">
                    <label className="settings-smtp-test-email-label">
                        <span className="settings-field-caption">{labels.testEmailLabel}</span>
                        <input type="email" className="settings-native-field" placeholder={labels.testEmailPlaceholder} value={testEmail}
                            onChange={this.changeTestEmail} data-test-id="admin-system-test-email-input" />
                    </label>
                    {mailTypes.length > 0 && (
                        <label className="settings-smtp-test-type-label">
                            <span className="settings-field-caption">{labels.testEmailType ?? ''}</span>
                            <select className="settings-native-field" value={testMailType} onChange={this.changeMailType}
                                data-test-id="admin-system-test-email-type">
                                <option value="">{labels.testEmailTypeGeneric ?? ''}</option>
                                {mailTypes.map(renderMailOption)}
                            </select>
                        </label>
                    )}
                    <button type="button" className="settings-test-email-button" disabled={sendingTestEmail} onClick={onSendTestEmail}
                        data-test-id="admin-system-test-email-send">
                        {sendingTestEmail ? labels.testEmailSending : labels.testEmailSend}
                    </button>
                </div>
            </section>
        );
    }

    public render() {
        const {labels, sending} = this.props;
        return (
            <>
                <>
                    <section className="settings-general-panel">
                        <div className="settings-general-header">
                            <h2 className="settings-general-title">{labels.smtpTitle}</h2>
                            <p className="settings-general-hint">{labels.smtpHint}</p>
                        </div>
                        {this.renderSmtpFields()}
                        {this.renderSmtpOptions()}
                        <SaveButton labels={labels} sending={sending} />
                    </section>
                    {this.renderTestEmail()}
                </>
            </>
        );
    }
}
