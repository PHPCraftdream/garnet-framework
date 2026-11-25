/**
 * Convert a string or numeric value to a number, defaulting invalid values to zero.
 *
 * @param value - Value to convert.
 * @returns The converted number, or zero when conversion is falsy.
 */
export const readNumber = (value: string | number | null | undefined): number => {
    return Number(value || 0) || 0;
}
