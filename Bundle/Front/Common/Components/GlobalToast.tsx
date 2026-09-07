import * as React from 'react';
// The event contract lives in a React-free module so the low-level API layer
// can dispatch toasts without importing THIS component module (doing so pulled
// React/JSX into the deepest API chunk → React #130 on auth/registration).
import {ToastType, TOAST_EVENT, ToastEventDetail} from './toastEvent';

export type {ToastType, ToastEventDetail} from './toastEvent';
export {TOAST_EVENT} from './toastEvent';

interface ToastEntry {
    id: number;
    message: string;
    type: ToastType;
}

type Listener = (entries: ToastEntry[]) => void;

/**
 * Toasts stack instead of replacing each other.
 *
 * They used to share one slot: `show()` overwrote the current entry, so a
 * burst — five attachments refused for five different reasons — left only the
 * last one on screen and destroyed the rest. The information existed and the
 * display threw it away.
 */
const TOAST_TTL_MS = 4000;

/** A runaway loop should not be able to paper over the whole page. */
const MAX_VISIBLE = 5;

class ToastManagerClass {
    private listeners: Set<Listener> = new Set();
    private timers: Map<number, {timer: ReturnType<typeof setTimeout> | null; remaining: number; startedAt: number}> = new Map();
    private entries: ToastEntry[] = [];
    private nextId = 1;

    constructor() {
        if (typeof window !== 'undefined') {
            window.addEventListener(TOAST_EVENT, (e: Event) => {
                const d = (e as CustomEvent<ToastEventDetail>).detail;
                if (d?.message) this.show(d.message, d.type);
            });
        }
    }

    show(message: string, type: ToastType = 'primary') {
        const id = this.nextId;
        this.nextId += 1;

        this.entries = [...this.entries, {id, message, type}];

        while (this.entries.length > MAX_VISIBLE) {
            const dropped = this.entries[0];
            this.entries = this.entries.slice(1);
            this.clearTimer(dropped.id);
        }

        this.notify();
        this.startTimer(id, TOAST_TTL_MS);
    }

    hide(id: number) {
        this.clearTimer(id);
        this.entries = this.entries.filter(e => e.id !== id);
        this.notify();
    }

    /** Hovering holds a toast open — reading a long reason takes longer than 4s. */
    pause(id: number) {
        const t = this.timers.get(id);

        if (!t?.timer) return;

        clearTimeout(t.timer);
        this.timers.set(id, {
            timer: null,
            remaining: Math.max(0, t.remaining - (Date.now() - t.startedAt)),
            startedAt: Date.now(),
        });
    }

    resume(id: number) {
        const t = this.timers.get(id);

        if (!t || t.timer !== null || t.remaining <= 0) return;

        this.startTimer(id, t.remaining);
    }

    subscribe(fn: Listener) {
        this.listeners.add(fn);
        fn(this.entries);
        return () => { this.listeners.delete(fn); };
    }

    private startTimer(id: number, ms: number) {
        this.clearTimer(id);
        this.timers.set(id, {
            timer: setTimeout(() => this.hide(id), ms),
            remaining: ms,
            startedAt: Date.now(),
        });
    }

    private clearTimer(id: number) {
        const t = this.timers.get(id);

        if (t?.timer) clearTimeout(t.timer);

        this.timers.delete(id);
    }

    private notify() {
        const snapshot = this.entries;
        this.listeners.forEach(fn => fn(snapshot));
    }
}

export const ToastManager = new ToastManagerClass();

/**
 * Shortcut — use from any island.
 *
 * Dispatches the global TOAST_EVENT rather than calling ToastManager.show()
 * directly: bundlers can duplicate this module into more than one chunk, and a
 * direct call would land on whichever ToastManager copy the CALLER imported —
 * not necessarily the one the rendered <GlobalToastRenderer> subscribed to.
 * Every ToastManager instance listens for the event, so the subscribed one
 * always picks it up. Falls back to a direct call when there's no window (SSR).
 */
export const showToast = (message: string, type?: ToastType) => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent<ToastEventDetail>(TOAST_EVENT, {detail: {message, type}}));
    } else {
        ToastManager.show(message, type);
    }
};

const typeClasses: Record<string, string> = {
    primary: 'text-bg-primary',
    success: 'text-bg-success',
    danger: 'text-bg-danger',
    warning: 'text-bg-warning',
};

/** Render ONCE in the layout — subscribes to ToastManager */
export const GlobalToastRenderer: React.FC = () => {
    const [entries, setEntries] = React.useState<ToastEntry[]>([]);

    React.useEffect(() => ToastManager.subscribe(setEntries), []);

    return (
        <div className="toast-container" data-test-id="toast-container">
            {entries.map(entry => (
                <div
                    key={entry.id}
                    role="alert"
                    aria-live="assertive"
                    aria-atomic="true"
                    className={`toast show ${typeClasses[entry.type] || 'text-bg-primary'}`}
                    onMouseEnter={() => ToastManager.pause(entry.id)}
                    onMouseLeave={() => ToastManager.resume(entry.id)}
                >
                    <div className="flex items-center">
                        <div className="toast-body">{entry.message}</div>
                        <button
                            type="button"
                            className="btn-close btn-close-white mr-2 ml-auto"
                            aria-label="Close"
                            onClick={() => ToastManager.hide(entry.id)}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
};
