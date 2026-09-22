import {ToastType, ToastEventDetail, TOAST_EVENT} from './toastEvent';
import {ToastManager} from './toastManager';

export type {ToastType, ToastEventDetail} from './toastEvent';

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
