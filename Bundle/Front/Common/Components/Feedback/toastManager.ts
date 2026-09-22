// The event contract lives in a React-free module so the low-level API layer
// can dispatch toasts without importing THIS component module (doing so pulled
// React/JSX into the deepest API chunk → React #130 on auth/registration).
import {ToastType, TOAST_EVENT, ToastEventDetail} from './toastEvent';

export interface ToastEntry {
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
