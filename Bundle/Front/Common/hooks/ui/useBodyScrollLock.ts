import {useEffect} from 'react';

/**
 * Locks body scroll when `isLocked` is true.
 * Saves the previous overflow value on mount and restores it on unmount/unlock.
 * Safe with stacked modals: if two modals are open, the second sees 'hidden'
 * (set by the first), so on unmount it restores 'hidden'. The first then
 * restores the original value.
 */
export function useBodyScrollLock(isLocked: boolean): void {
    useEffect(() => {
        if (!isLocked) return;

        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = prev;
        };
    }, [isLocked]);
}
