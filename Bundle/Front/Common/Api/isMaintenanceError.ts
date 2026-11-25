/**
 * True if a rejected request error came from maintenance (503). Lets callers
 * (e.g. useAsyncIcon) skip their OWN error toast so the single maintenance
 * toast raised by {@link isMaintenance503} isn't clobbered by a generic one.
 */
export const isMaintenanceError = (e: unknown): boolean => {
    const err = e as {status?: number; response?: {status?: number; maintenance?: boolean}} | null;
    return !!err && (err.status === 503 || err.response?.status === 503 || err.response?.maintenance === true);
};
