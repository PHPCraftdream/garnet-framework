/**
 * Get the current Unix timestamp in whole seconds.
 *
 * @returns Current time since the Unix epoch in seconds.
 */
export const unixTime = () => {
    return Math.floor(Date.now() / 1000);
};
