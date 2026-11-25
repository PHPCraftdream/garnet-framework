/** Provides the browser's current IANA timezone identifier. */
export const currentTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
