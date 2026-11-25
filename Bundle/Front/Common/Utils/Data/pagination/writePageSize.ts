const STORAGE_KEY = 'garnet.pageSize';

/** Stores the selected page size and notifies controls in the current tab. */
export function writePageSize(n: number): void {
    try {
        localStorage.setItem(STORAGE_KEY, String(n));
        if (typeof window !== 'undefined') {
            const event = new CustomEvent<number>(
                'garnet:pageSizeChange',
                {detail: n},
            );
            window.dispatchEvent(event);
        }
    } catch {
        // Storage may be unavailable; the control's local state remains usable.
    }
}
