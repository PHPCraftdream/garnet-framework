/** Converts a history value to its compact display representation. */
export const renderHistoryValue = (value: unknown): string => {
    if (value === null || value === undefined) return '∅';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};
