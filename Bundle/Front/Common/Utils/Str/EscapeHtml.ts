const escapeMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    "/": '&#x2F;'
};

export const escapeHtml = (text: string): string => {
    return text.replace(/[&<>"'/]/g, function (m) {
        return escapeMap[m];
    });
}
