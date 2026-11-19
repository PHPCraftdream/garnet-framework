import {useCallback, useEffect, useRef, useState, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';
import {showToast} from '@common/Components/Feedback/GlobalToast';
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

class AsyncIconStore extends Carburetor<{state: AsyncIconState}> {
    constructor() { super({state: 'idle'}); }
    readonly snapshot = () => this.getData();
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    readonly setState = (state: AsyncIconState) => {
        const previous = this.getData();
        if (previous.state === state) return;
        const version = this.getVersion();
        this.setData({state});
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

function createAsyncIconStore(): AsyncIconStore { return new AsyncIconStore(); }

export function useAsyncIcon(options: UseAsyncIconOptions = {}): AsyncIconControls {
    const {errorToast, errorMs = 2000, onError, rethrow = false} = options;

    const [store] = useState(createAsyncIconStore);
    const {state} = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const setState = store.setState;
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
    }, [errorToast, errorMs, onError, rethrow, setState]);

    return {state, isLoading: state === 'loading', isError: state === 'error', run};
}
