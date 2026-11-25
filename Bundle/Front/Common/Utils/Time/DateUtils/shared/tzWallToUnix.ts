import {tsToParts} from './tsToParts';

/** Converts a timezone wall-clock date into its Unix timestamp. */
export function tzWallToUnix(
    y: number,
    m: number,
    d: number,
    h: number,
    mi: number,
    s: number,
    tz: string,
): number {
    const utcGuess = Date.UTC(y, m - 1, d, h, mi, s);
    const wallParts = tsToParts(Math.floor(utcGuess / 1000), tz);
    const wallAsUtc = Date.UTC(
        Number(wallParts.year),
        Number(wallParts.month) - 1,
        Number(wallParts.day),
        Number(wallParts.hour),
        Number(wallParts.minute),
        0,
    );
    const offsetMs = wallAsUtc - utcGuess;
    return Math.floor((utcGuess - offsetMs) / 1000);
}
