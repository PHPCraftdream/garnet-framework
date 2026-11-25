const escapeMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    "/": '&#x2F;'
};

/** Replaces one unsafe HTML character with its escaped entity. */
function escapeMatch(m: string): string {
    return escapeMap[m];
}

/** Escapes HTML-significant characters in text. */
export const escapeHtml = (text: string): string => {
    return text.replace(/[&<>"'/]/g, escapeMatch);
}
