import {useCallback, useEffect, useRef, useState} from 'react';
import {showToast} from '@common/Components/GlobalToast';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {isMaintenanceError} from '@common/Api/maintenance503';

/**
 * Centralised "icon-button request" state machine.
 *
 *   idle  → click → loading (icon swapped for a spinner)
 *   loading → ok    → idle  (original icon restored)
 *   loading → error → error (error icon shown) → after `errorMs` → idle,
 *                     and a global toast is raised.
 *
 * Any island can reuse this so the loader / error-icon / toast / auto-revert
 * behaviour is written ONCE. Pair it with <AsyncIcon> (renders the right glyph
 * for the current state) or <AsyncIconButton> (a ready-made button/link).
 */
export type AsyncIconState = 'idle' | 'loading' | 'error';

export interface UseAsyncIconOptions {
    /** Toast text shown on failure. Defaults to I18nFramework.Common_RequestError(). */
    errorToast?: string;
    /** How long the error icon stays before reverting (ms). Default 2000. */
    errorMs?: number;
    /** Called after the error is handled (toast already shown). */
    onError?: (error: unknown) => void;
    /** Re-throw the error after handling (default false). */
    rethrow?: boolean;
}

export interface AsyncIconControls {
    state: AsyncIconState;
    isLoading: boolean;
    isError: boolean;
    /** Wrap the async action; drives the state machine + toast on failure. */
    run: (action: () => unknown | Promise<unknown>) => Promise<void>;
}

export function useAsyncIcon(options: UseAsyncIconOptions = {}): AsyncIconControls {
    const {errorToast, errorMs = 2000, onError, rethrow = false} = options;

    const [state, setState] = useState<AsyncIconState>('idle');
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);

    const clearTimer = () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    };

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
            clearTimer();
        };
    }, []);

    const run = useCallback(async (action: () => unknown | Promise<unknown>) => {
        clearTimer();
        setState('loading');
        try {
            await action();
            if (mountedRef.current) setState('idle');
        } catch (error) {
            // During maintenance the API layer already raised the single
            // maintenance toast — don't clobber it with a generic error one.
            if (!isMaintenanceError(error)) {
                showToast(errorToast ?? I18nFramework.Common_RequestError(), 'danger');
            }
            onError?.(error);
            if (mountedRef.current) {
                setState('error');
                timerRef.current = setTimeout(() => {
                    if (mountedRef.current) setState('idle');
                }, errorMs);
            }
            if (rethrow) throw error;
        }
    }, [errorToast, errorMs, onError, rethrow]);

    return {state, isLoading: state === 'loading', isError: state === 'error', run};
}
