interface GarnetUser {
    accountId?: number;
    name?: string;
    timezone?: string;
}

/** Returns the configured account timezone when available. */
export function userTz(): string | undefined {
    if (typeof window === 'undefined') return undefined;
    const user = (window as Window & {__GARNET_USER__?: GarnetUser}).__GARNET_USER__;
    const tz = user?.timezone;
    return tz && tz.length > 0 ? tz : undefined;
}
