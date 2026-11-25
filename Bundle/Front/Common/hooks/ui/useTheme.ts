import {useState, useCallback, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {getTheme} from '@common/Utils/Ui/theme/getTheme';
import {setTheme} from '@common/Utils/Ui/theme/setTheme';
import type {Theme} from '@common/Utils/Ui/theme/themeTypes';

/** Creates a theme store. */
function createThemeStore(): ThemeStore { return new ThemeStore(); }

class ThemeStore extends Carburetor<{theme: Theme}> {
    /** Initializes the current theme state. */
    constructor() { super({theme: getTheme()}); }
    /** Returns the current theme snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to theme-state changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the selected theme. */
    readonly setTheme = (theme: Theme) => {
        const previous = this.getData();
        if (previous.theme === theme) return;
        const version = this.getVersion();
        this.setData({theme});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/** Reads and updates the selected theme. */
export function useTheme() {
    const [store] = useState(createThemeStore);
    const {theme} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);

    const toggleTheme = useCallback(() => {
        const next: Theme = theme === 'light' ? 'dark' : 'light';
        setTheme(next);
        store.setTheme(next);
    }, [theme, store]);

    return {theme, toggleTheme} as const;
}
