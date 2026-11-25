import type {Theme} from './themeTypes';
const STORAGE_KEY = 'garnet-theme';

/** Reads the saved appearance, defaulting to light mode. */
export function getTheme(): Theme {
    return localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
}
