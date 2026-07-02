import {useContext, useSyncExternalStore} from 'react';
import * as React from 'react';

type ErrorMap = Record<string, string>;

/**
 * Pub-sub store for field-level validation errors.
 *
 * Components call `useFormErrors(name)` to subscribe to a single field's
 * error string. Only the component whose error changed re-renders —
 * sibling fields stay untouched.
 */
class FormErrorStore {
    private errors: ErrorMap = {};
    private listeners = new Map<string, Set<() => void>>();
    private anyListeners = new Set<() => void>();

    getErrors(): ErrorMap {
        return this.errors;
    }

    getError(name: string): string {
        return this.errors[name] ?? '';
    }

    subscribe(name: string, listener: () => void): () => void {
        let set = this.listeners.get(name);
        if (!set) {
            set = new Set();
            this.listeners.set(name, set);
        }
        set.add(listener);
        return () => {
            set!.delete(listener);
            if (set!.size === 0) this.listeners.delete(name);
        };
    }

    subscribeAny(listener: () => void): () => void {
        this.anyListeners.add(listener);
        return () => { this.anyListeners.delete(listener); };
    }

    setErrors(errors: ErrorMap): void {
        const changed = new Set<string>();
        for (const k of new Set([...Object.keys(this.errors), ...Object.keys(errors)])) {
            if (this.errors[k] !== errors[k]) changed.add(k);
        }
        this.errors = errors;
        for (const name of changed) {
            this.listeners.get(name)?.forEach(l => l());
        }
        if (changed.size > 0) {
            this.anyListeners.forEach(l => l());
        }
    }
}

const FormErrorContext = React.createContext<FormErrorStore | null>(null);

/**
 * Internal provider component. Wrap a form in this to enable
 * `useFormErrors` for descendant fields.
 */
export const FormErrorProvider = FormErrorContext.Provider;

/**
 * Hook that subscribes to a single field's validation error.
 * Returns `''` when there is no error for the given field.
 *
 * If `name` is omitted, returns the full error map (debug / rare).
 */
export function useFormErrors(name?: string): string | ErrorMap {
    const store = useContext(FormErrorContext);
    if (!store) return name ? '' : {};

    if (name === undefined) {
        return useSyncExternalStore(
            (cb) => store.subscribeAny(cb),
            () => store.getErrors(),
        );
    }

    return useSyncExternalStore(
        (cb) => store.subscribe(name, cb),
        () => store.getError(name),
    );
}

export {FormErrorStore};
