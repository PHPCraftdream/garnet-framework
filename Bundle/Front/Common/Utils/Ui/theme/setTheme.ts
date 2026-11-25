import type {Theme} from './themeTypes';
const STORAGE_KEY = 'garnet-theme';

/** Persists an appearance choice and applies it to the document. */
export function setTheme(theme: Theme): void {
    localStorage.setItem(STORAGE_KEY, theme);
    document.documentElement.setAttribute('data-theme', theme);
}
