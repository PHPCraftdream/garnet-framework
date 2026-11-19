import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {Sun, Moon} from 'lucide-react';
import {getTheme, setTheme} from '@common/Utils/Ui/ThemeManager';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export class ThemeToggle extends AntiHookComponent<{testId?: string}> {
    private readonly themeState = new Carburetor({theme: getTheme()});

    private readonly toggleTheme = () => {
        const next = this.themeState.getData().theme === 'light' ? 'dark' : 'light';
        setTheme(next);
        this.themeState.setData({theme: next});
    };

    public render() {
        const {testId = 'theme-toggle-btn'} = this.props;
        const {theme} = this.useCarburetor(this.themeState);
        const label = theme === 'light' ? I18nFramework.Theme_SwitchToDark() : I18nFramework.Theme_SwitchToLight();

        return (
            <button
                type="button"
                className="common-theme-toggle"
                data-test-id={testId}
                onClick={this.toggleTheme}
                title={label}
                aria-label={label}
            >
                {theme === 'light' ? <Moon size={18} aria-hidden="true" /> : <Sun size={18} aria-hidden="true" />}
            </button>
        );
    }
}
