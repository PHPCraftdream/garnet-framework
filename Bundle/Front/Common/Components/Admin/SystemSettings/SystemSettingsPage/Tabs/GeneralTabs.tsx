import * as React from 'react';
import {SettingsData, SystemSettingsLabels} from '@common/Components/Admin/SystemSettings/SystemSettingsPage/types';

/** Props shared by the settings tabs. */
export interface TabProps {
    settings: SettingsData;
    setSettings: React.Dispatch<React.SetStateAction<SettingsData>>;
    labels: SystemSettingsLabels;
    sending: boolean;
}
