/** Parses JSON, returning the fallback for null input or invalid JSON. */
export const parseJson = <T>(str: string | null, def: T | null = null): T | null => {
    if (str === null) {
        return def;
    }

    try {
        return JSON.parse(str) as T;
    } catch {
        return def;
    }
}
