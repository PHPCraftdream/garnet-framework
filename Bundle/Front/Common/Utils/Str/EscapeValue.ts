export const escapeValue = (value: string): string => {
    return value.replaceAll('"', "\\" + '"');
}
