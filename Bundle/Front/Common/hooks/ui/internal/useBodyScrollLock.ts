import {useEffect} from 'react';

/** Locks body scrolling while enabled.
 *
 * Restores the prior overflow value on cleanup, including when modal locks are stacked.
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
