import {getTheme} from './getTheme';

/** Applies the saved appearance during application startup. */
export function initTheme(): void {
    document.documentElement.setAttribute('data-theme', getTheme());
}
