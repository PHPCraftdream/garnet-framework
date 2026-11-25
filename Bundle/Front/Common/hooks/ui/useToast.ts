import {useState, useCallback, useRef, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';

export type ToastType = 'primary' | 'success' | 'danger' | 'warning';

export interface ToastState {
    message: string;
    type: ToastType;
    visible: boolean;
}

export interface ToastControls {
    toast: ToastState;
    showToast: (message: string, type?: ToastType) => void;
    hideToast: () => void;
    /** Pause auto-hide timer (call on mouse enter) */
    pause: () => void;
    /** Resume auto-hide timer (call on mouse leave) */
    resume: () => void;
}

class ToastStore extends Carburetor<ToastState> {
    /** Initializes hidden toast state. */
    constructor() { super({message: '', type: 'primary', visible: false}); }
    /** Returns the current toast snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to toast-state changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the current toast. */
    readonly setToast = (value: ToastState | ((previous: ToastState) => ToastState)) => {
        const previous = this.getData();
        const next = typeof value === 'function' ? value(previous) : value;
        if (Object.is(next, previous)) return;
        const version = this.getVersion();
        this.setData(next);
        if (this.getData() !== previous && this.getVersion() === version) {
            this.markAllChanged();
            this.emitUpdate();
        }
    };
}

/** Creates a toast store. */
function createToastStore(): ToastStore { return new ToastStore(); }

/** Creates the hidden toast state. */
function hiddenToast(previous: ToastState): ToastState { return {...previous, visible: false}; }

/** Provides toast state and actions. */
export function useToast(): ToastControls {
    const [store] = useState(createToastStore);
    const toast = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const setToast = store.setToast;
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const remainingRef = useRef(0);
    const startRef = useRef(0);

    const clearTimer = useCallback(() => {
        if (timerRef.current) { clearTimeout(timerRef.current); timerRef.current = null; }
    }, []);

    const startTimer = useCallback((ms: number) => {
        clearTimer();
        remainingRef.current = ms;
        startRef.current = Date.now();
        timerRef.current = setTimeout(() => setToast(hiddenToast), ms);
    }, [clearTimer, setToast]);

    const showToast = useCallback((message: string, type: ToastType = 'primary') => {
        clearTimer();
        setToast({message, type, visible: true});
        startTimer(4000);
    }, [clearTimer, setToast, startTimer]);

    const hideToast = useCallback(() => {
        clearTimer();
        setToast(hiddenToast);
    }, [clearTimer, setToast]);

    const pause = useCallback(() => {
        if (timerRef.current) {
            remainingRef.current = Math.max(0, remainingRef.current - (Date.now() - startRef.current));
            clearTimer();
        }
    }, [clearTimer]);

    const resume = useCallback(() => {
        if (remainingRef.current > 0) startTimer(remainingRef.current);
    }, [startTimer]);

    return {toast, showToast, hideToast, pause, resume};
}
