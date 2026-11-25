import {PAGE_SIZE_OPTIONS} from './PAGE_SIZE_OPTIONS';
import {DEFAULT_PAGE_SIZE} from './DEFAULT_PAGE_SIZE';

const STORAGE_KEY = 'garnet.pageSize';

/** Reads the saved size, falling back when storage contains no supported choice. */
export function readPageSize(): number {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return DEFAULT_PAGE_SIZE;
        const n = parseInt(raw, 10);
        return (PAGE_SIZE_OPTIONS as readonly number[]).includes(n) ? n : DEFAULT_PAGE_SIZE;
    } catch { return DEFAULT_PAGE_SIZE; }
}
