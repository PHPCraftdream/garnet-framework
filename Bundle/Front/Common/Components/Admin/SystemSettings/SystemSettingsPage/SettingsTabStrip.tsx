import * as React from 'react';
import type {SystemSettingsLabels, TabKey} from './types';

/** Props for a selectable settings tab strip. */
interface Props {
    activeTab: TabKey;
    labels: SystemSettingsLabels;
    historyListUrl?: string;
    onSelect: (key: TabKey) => void;
}

/** Props for one settings tab button. */
interface TabButtonProps {
    tabKey: TabKey;
    label: string;
    active: boolean;
    testId?: string;
    onSelect: (key: TabKey) => void;
}

class SettingsTabButton extends React.Component<TabButtonProps> {
    /** Selects this button's tab. */
    private readonly handleClick = (): void => { this.props.onSelect(this.props.tabKey); };

    /** Renders this settings tab button. */
    public render() {
        const {label, active, testId} = this.props;
        return (
            <li className="settings-tab-item">
                <button
                    type="button"
                    data-test-id={testId}
                    className="settings-tab-link"
                    data-active={active ? 'true' : undefined}
                    onClick={this.handleClick}
                >
                    {label}
                </button>
            </li>
        );
    }
}

/** Controlled System Settings tabs; selection remains owned by the page. */
export function SettingsTabStrip({activeTab, labels, historyListUrl, onSelect}: Props) {
    return (
        <ul className="settings-tabs-bar">
            <SettingsTabButton
                tabKey="smtp"
                label={labels.smtpTab}
                active={activeTab === 'smtp'}
                onSelect={onSelect}
            />
            <SettingsTabButton
                tabKey="registration"
                label={labels.registrationTab}
                active={activeTab === 'registration'}
                onSelect={onSelect}
            />
            {labels.cancellationPenaltyTab && (
                <SettingsTabButton
                    tabKey="penalty"
                    label={labels.cancellationPenaltyTab}
                    active={activeTab === 'penalty'}
                    onSelect={onSelect}
                />
            )}
            {labels.supportContactsTab && (
                <SettingsTabButton
                    tabKey="contacts"
                    label={labels.supportContactsTab}
                    active={activeTab === 'contacts'}
                    onSelect={onSelect}
                />
            )}
            {labels.seoTab && (
                <SettingsTabButton
                    tabKey="seo"
                    label={labels.seoTab}
                    active={activeTab === 'seo'}
                    onSelect={onSelect}
                />
            )}
            {historyListUrl && (
                <SettingsTabButton
                    tabKey="history"
                    label={labels.historyTab}
                    active={activeTab === 'history'}
                    testId="system-settings-tab-history"
                    onSelect={onSelect}
                />
            )}
        </ul>
    );
}
