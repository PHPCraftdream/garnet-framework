import {useState, useCallback} from 'react';
import {getTheme, setTheme, Theme} from '@common/Utils/ThemeManager';

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(getTheme);

    const toggleTheme = useCallback(() => {
        const next: Theme = theme === 'light' ? 'dark' : 'light';
        setTheme(next);
        setThemeState(next);
    }, [theme]);

    return {theme, toggleTheme} as const;
}
