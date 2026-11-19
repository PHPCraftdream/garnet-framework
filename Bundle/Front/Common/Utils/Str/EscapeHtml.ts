const escapeMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    "/": '&#x2F;'
};

function escapeMatch(m: string): string {
    return escapeMap[m];
}

export const escapeHtml = (text: string): string => {
    return text.replace(/[&<>"'/]/g, escapeMatch);
}
