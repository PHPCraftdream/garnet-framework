/** Escapes double quotes in a value for quoted-string embedding. */
export const escapeValue = (value: string): string => {
    return value.replaceAll('"', "\\\"");
}
