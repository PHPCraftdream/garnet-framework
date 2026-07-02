export const readNumber = (value: string | number | null | undefined): number => {
    return Number(value || 0) || 0;
}
