/** Shared tab props and legacy entry point for general settings. */
import * as React from 'react';
import {SettingsData, SystemSettingsLabels} from '@common/Components/Admin/SystemSettings/SystemSettingsPage/types';

export interface TabProps {
    settings: SettingsData;
    setSettings: React.Dispatch<React.SetStateAction<SettingsData>>;
    labels: SystemSettingsLabels;
    sending: boolean;
}

export {RegistrationTab} from './RegistrationTab';
export {PenaltyTab} from './PenaltyTab';

export {ContactsTab} from './ContactsTab';
