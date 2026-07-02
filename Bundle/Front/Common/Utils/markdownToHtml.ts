import {appUrl} from '@common/Utils/appUrl';

/**
 * Lightweight markdown-to-HTML converter.
 * Supports: **bold**, *italic*, [link](url), - list items, ordered lists, paragraphs, line breaks, hr,
 * ## headings, > blockquotes, {link:slug} page links.
 */
export function markdownToHtml(md: string): string {
    if (!md) return '';

    // Replace {link:slug} with a visual placeholder
    let html = md.replace(/\{link:([a-z0-9_-]+)\}/gi, (_, slug) => `<a href="${appUrl('/page/view~' + slug)}" class="text-accent">${slug}</a>`);

    html = html
        // Headings: ### before ## (more specific first)
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        // Bold: **text** or __text__
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/__(.+?)__/g, '<strong>$1</strong>')
        // Italic: *text* or _text_ (but not inside **)
        .replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>')
        .replace(/(?<!_)_(?!_)(.+?)(?<!_)_(?!_)/g, '<em>$1</em>')
        // Links: [text](url)
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
        // Horizontal rule: --- or *** or ___
        .replace(/^(---|\*\*\*|___)\s*$/gm, '<hr/>')
    ;

    // Blockquotes: > text
    html = html.replace(/^(?:>\s+.+\n?)+/gm, (match) => {
        const lines = match.trim().split('\n').map(line => line.replace(/^>\s+/, ''));
        return '<blockquote>' + lines.join('<br/>') + '</blockquote>';
    });

    // Process unordered lists: lines starting with - or *
    html = html.replace(/^(?:[-*]\s+.+\n?)+/gm, (match) => {
        const items = match.trim().split('\n').map(line => {
            const text = line.replace(/^[-*]\s+/, '');
            return `<li>${text}</li>`;
        }).join('');
        return `<ul>${items}</ul>`;
    });

    // Process ordered lists: lines starting with 1. 2. etc
    html = html.replace(/^(?:\d+\.\s+.+\n?)+/gm, (match) => {
        const items = match.trim().split('\n').map(line => {
            const text = line.replace(/^\d+\.\s+/, '');
            return `<li>${text}</li>`;
        }).join('');
        return `<ol>${items}</ol>`;
    });

    // Paragraphs: double newline
    html = html
        .split(/\n{2,}/)
        .map(block => {
            block = block.trim();
            if (!block) return '';
            // Don't wrap if already wrapped in a block element
            if (/^<(?:ul|ol|li|hr|div|p|h[1-6]|blockquote|table|pre)/i.test(block)) {
                return block;
            }
            // Single newlines within a paragraph become <br>
            return '<p>' + block.replace(/\n/g, '<br/>') + '</p>';
        })
        .filter(Boolean)
        .join('\n');

    return html;
}
