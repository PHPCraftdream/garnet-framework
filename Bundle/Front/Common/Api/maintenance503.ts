import {TOAST_EVENT, ToastEventDetail} from '@common/Components/toastEvent';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

/** Raise a toast from the low-level API layer WITHOUT importing the toast
 *  singleton — a window event reaches every ToastManager instance, so chunk
 *  splitting can't strand the message on an unsubscribed copy. */
const fireToast = (detail: ToastEventDetail): void => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent<ToastEventDetail>(TOAST_EVENT, {detail}));
    }
};

/**
 * Centralised maintenance (HTTP 503) handling for the API layer.
 *
 * When the server is in maintenance mode every request gets a 503 carrying
 * the HTML maintenance page (not JSON). Each response helper calls this so the
 * whole app reacts the same way — one clear localized toast — instead of every
 * call site choking on an opaque JSON-parse error.
 *
 * Returns true when the status IS a 503 (and the toast was raised), so callers
 * can short-circuit before trying to parse the body.
 */
export const isMaintenance503 = (status: number): boolean => {
    if (status !== 503) {
        return false;
    }
    fireToast({message: I18nFramework.Common_Maintenance(), type: 'warning'});
    return true;
};

/**
 * True if a rejected request error came from maintenance (503). Lets callers
 * (e.g. useAsyncIcon) skip their OWN error toast so the single maintenance
 * toast raised by {@link isMaintenance503} isn't clobbered by a generic one.
 */
export const isMaintenanceError = (e: unknown): boolean => {
    const err = e as {status?: number; response?: {status?: number; maintenance?: boolean}} | null;
    return !!err && (err.status === 503 || err.response?.status === 503 || err.response?.maintenance === true);
};
