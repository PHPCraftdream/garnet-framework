import * as React from 'react';
import {Sun, Moon} from 'lucide-react';
import {useTheme} from '@common/hooks/useTheme';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export const ThemeToggle: React.FC<{testId?: string}> = ({testId = 'theme-toggle-btn'}) => {
    const {theme, toggleTheme} = useTheme();
    const label = theme === 'light' ? I18nFramework.Theme_SwitchToDark() : I18nFramework.Theme_SwitchToLight();

    return (
        <button
            type="button"
            className="common-theme-toggle"
            data-test-id={testId}
            onClick={toggleTheme}
            title={label}
            aria-label={label}
        >
            {theme === 'light' ? <Moon size={18} aria-hidden="true" /> : <Sun size={18} aria-hidden="true" />}
        </button>
    );
};
