import {useState, useCallback, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {getTheme, setTheme, Theme} from '@common/Utils/Ui/ThemeManager';

function createThemeStore(): ThemeStore { return new ThemeStore(); }

class ThemeStore extends Carburetor<{theme: Theme}> {
    constructor() { super({theme: getTheme()}); }
    readonly snapshot = () => this.getData();
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
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
