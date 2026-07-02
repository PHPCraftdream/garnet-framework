const STORAGE_KEY = 'garnet-theme';

export type Theme = 'light' | 'dark';

export function getTheme(): Theme {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored === 'dark' ? 'dark' : 'light';
}

export function setTheme(theme: Theme): void {
    localStorage.setItem(STORAGE_KEY, theme);
    document.documentElement.setAttribute('data-theme', theme);
}

export function initTheme(): void {
    const theme = getTheme();
    document.documentElement.setAttribute('data-theme', theme);
}
