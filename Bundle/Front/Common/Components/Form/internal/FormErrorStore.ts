const notifyListener = (listener: () => void) => listener();

/** Stores and broadcasts field-level validation errors. */
export class FormErrorStore {
    private errors: Record<string, string> = {};
    private listeners = new Map<string, Set<() => void>>();
    private anyListeners = new Set<() => void>();

    /** Returns the current complete field-error map. */
    public getErrors(): Record<string, string> {
        return this.errors;
    }

    /** Returns one field's error or an empty string. */
    public getError(name: string): string {
        return this.errors[name] ?? '';
    }

    /** Subscribes to changes for one field. */
    public subscribe(name: string, listener: () => void): () => void {
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

    /** Subscribes to any field-error change. */
    public subscribeAny(listener: () => void): () => void {
        this.anyListeners.add(listener);
        return () => { this.anyListeners.delete(listener); };
    }

    /** Replaces errors and notifies only listeners whose values changed. */
    public setErrors(errors: Record<string, string>): void {
        const changed = new Set<string>();
        for (const key of new Set([...Object.keys(this.errors), ...Object.keys(errors)])) {
            if (this.errors[key] !== errors[key]) changed.add(key);
        }
        this.errors = errors;
        for (const name of changed) {
            this.listeners.get(name)?.forEach(notifyListener);
        }
        if (changed.size > 0) {
            this.anyListeners.forEach(notifyListener);
        }
    }
}
