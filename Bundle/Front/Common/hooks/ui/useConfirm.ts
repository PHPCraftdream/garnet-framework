import {useState, useCallback, useRef, useSyncExternalStore} from 'react';
import {Carburetor} from '@react-carburetor/core';

export interface ConfirmState {
    visible: boolean;
    message: string;
    items: string[];
    confirmLabel?: string;
    variant?: 'success' | 'danger';
}

export interface ConfirmOptions {
    items?: string[];
    confirmLabel?: string;
    variant?: 'success' | 'danger';
}

class ConfirmStore extends Carburetor<ConfirmState> {
    /** Initializes hidden confirmation state. */
    constructor() { super({visible: false, message: '', items: []}); }
    /** Returns the current confirmation snapshot. */
    readonly snapshot = () => this.getData();
    /** Subscribes to confirmation-state changes. */
    readonly subscribeSnapshot = (listener: () => void) => {
        const id = this.subscribe(listener);
        return () => this.unsubscribe(id);
    };
    /** Updates the confirmation state. */
    readonly setConfirmState = (value: ConfirmState | ((previous: ConfirmState) => ConfirmState)) => {
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

/** Creates a confirmation store. */
function createConfirmStore(): ConfirmStore { return new ConfirmStore(); }

/** Closes the active confirmation. */
function closeConfirmation(previous: ConfirmState): ConfirmState { return {...previous, visible: false}; }

/** Provides confirmation state and actions. */
export function useConfirm() {
    const [store] = useState(createConfirmStore);
    const confirmState = useSyncExternalStore(store.subscribeSnapshot, store.snapshot, store.snapshot);
    const setConfirmState = store.setConfirmState;
    const resolveRef = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback((message: string, opts: string[] | ConfirmOptions = []): Promise<boolean> => {
        return new Promise(resolve => {
            resolveRef.current = resolve;
            // Backwards-compatible: if opts is an array, treat as items
            if (Array.isArray(opts)) {
                setConfirmState({visible: true, message, items: opts});
            } else {
                setConfirmState({
                    visible: true,
                    message,
                    items: opts.items ?? [],
                    confirmLabel: opts.confirmLabel,
                    variant: opts.variant,
                });
            }
        });
    }, [setConfirmState]);

    const handleConfirm = useCallback(() => {
        setConfirmState(closeConfirmation);
        resolveRef.current?.(true);
        resolveRef.current = null;
    }, [setConfirmState]);

    const handleCancel = useCallback(() => {
        setConfirmState(closeConfirmation);
        resolveRef.current?.(false);
        resolveRef.current = null;
    }, [setConfirmState]);

    return {confirmState, confirm, handleConfirm, handleCancel};
}
