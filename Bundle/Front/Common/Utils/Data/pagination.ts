/**
 * Centralized page-size state for every grid/list in the app.
 *
 * The selector renders the same options everywhere (10/20/30/40/50/100) and
 * persists the user's choice in localStorage under a single key, so picking
 * "50 per page" on one grid also bumps every other grid the user opens next.
 * Keep the storage key stable — changing it forces every user back to the
 * default the next time their page loads.
 */
export const PAGE_SIZE_OPTIONS = [10, 20, 30, 40, 50, 100] as const;
export const DEFAULT_PAGE_SIZE = 10;

const STORAGE_KEY = 'garnet.pageSize';

/** Read the saved page size, falling back to DEFAULT_PAGE_SIZE for unknown values. */
export function readPageSize(): number {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return DEFAULT_PAGE_SIZE;
        const n = parseInt(raw, 10);
        return (PAGE_SIZE_OPTIONS as readonly number[]).includes(n) ? n : DEFAULT_PAGE_SIZE;
    } catch {
        return DEFAULT_PAGE_SIZE;
    }
}

/** Persist the user's selection and notify in-page subscribers (usePageSize). */
export function writePageSize(n: number): void {
    try {
        localStorage.setItem(STORAGE_KEY, String(n));
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent<number>('garnet:pageSizeChange', {detail: n}));
        }
    } catch {
        // localStorage may be unavailable (private mode, embedded view, etc.) —
        // selection still works for this tab via the React state in usePageSize.
    }
}
