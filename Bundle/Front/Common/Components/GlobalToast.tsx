import * as React from 'react';
// The event contract lives in a React-free module so the low-level API layer
// can dispatch toasts without importing THIS component module (doing so pulled
// React/JSX into the deepest API chunk → React #130 on auth/registration).
import {ToastType, TOAST_EVENT, ToastEventDetail} from './toastEvent';

export type {ToastType, ToastEventDetail} from './toastEvent';
export {TOAST_EVENT} from './toastEvent';

interface ToastEntry {
    message: string;
    type: ToastType;
    visible: boolean;
}

type Listener = (entry: ToastEntry) => void;

/** Global toast manager — singleton, works across React trees (islands) */
class ToastManagerClass {
    private listeners: Set<Listener> = new Set();
    private timer: ReturnType<typeof setTimeout> | null = null;
    private remaining = 0;
    private startTime = 0;
    private current: ToastEntry = {message: '', type: 'primary', visible: false};

    constructor() {
        if (typeof window !== 'undefined') {
            window.addEventListener(TOAST_EVENT, (e: Event) => {
                const d = (e as CustomEvent<ToastEventDetail>).detail;
                if (d?.message) this.show(d.message, d.type);
            });
        }
    }

    show(message: string, type: ToastType = 'primary') {
        this.clearTimer();
        this.current = {message, type, visible: true};
        this.notify();
        this.startTimer(4000);
    }

    hide() {
        this.clearTimer();
        this.current = {...this.current, visible: false};
        this.notify();
    }

    pause() {
        if (this.timer) {
            this.remaining = Math.max(0, this.remaining - (Date.now() - this.startTime));
            this.clearTimer();
        }
    }

    resume() {
        if (this.remaining > 0) this.startTimer(this.remaining);
    }

    subscribe(fn: Listener) {
        this.listeners.add(fn);
        fn(this.current);
        return () => { this.listeners.delete(fn); };
    }

    private startTimer(ms: number) {
        this.clearTimer();
        this.remaining = ms;
        this.startTime = Date.now();
        this.timer = setTimeout(() => this.hide(), ms);
    }

    private clearTimer() {
        if (this.timer) { clearTimeout(this.timer); this.timer = null; }
    }

    private notify() {
        this.listeners.forEach(fn => fn(this.current));
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
    const [entry, setEntry] = React.useState<ToastEntry>({message: '', type: 'primary', visible: false});

    React.useEffect(() => ToastManager.subscribe(setEntry), []);

    return (
        <div className="toast-container">
            <div
                role="alert"
                aria-live="assertive"
                aria-atomic="true"
                className={`toast ${entry.visible ? 'show' : ''} ${typeClasses[entry.type] || 'text-bg-primary'}`}
                onMouseEnter={() => ToastManager.pause()}
                onMouseLeave={() => ToastManager.resume()}
            >
                <div className="flex items-center">
                    <div className="toast-body">{entry.message}</div>
                    <button
                        type="button"
                        className="btn-close btn-close-white mr-2 ml-auto"
                        aria-label="Close"
                        onClick={() => ToastManager.hide()}
                    />
                </div>
            </div>
        </div>
    );
};
