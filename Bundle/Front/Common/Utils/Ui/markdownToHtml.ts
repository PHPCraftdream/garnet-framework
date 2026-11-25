import {appUrl} from '@common/Utils/Url/appUrl';
import DOMPurify from 'dompurify';

const SAFE_TAGS = ['a', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'hr', 'li', 'ol', 'p', 'strong', 'ul'];
const SAFE_ATTR = ['class', 'href', 'rel', 'target'];
const SAFE_URI = /^(?:(?:https?|mailto|tel):|\/|#|\?)/i;

/** Converts a page slug token to a safe-path anchor snippet. */
function pageLink(_: string, slug: string): string {
    return `<a href="${appUrl('/page/view~' + slug)}" class="text-accent">${slug}</a>`;
}

/** Removes the markdown quote prefix from a line. */
function blockquoteLine(line: string): string {
    return line.replace(/^>\s+/, '');
}

/** Converts a markdown blockquote into blockquote markup. */
function blockquote(match: string): string {
    const lines = match.trim().split('\n').map(blockquoteLine);
    return '<blockquote>' + lines.join('<br/>') + '</blockquote>';
}

/** Converts a markdown unordered-list line to an item. */
function unorderedItem(line: string): string {
    const text = line.replace(/^[-*]\s+/, '');
    return `<li>${text}</li>`;
}

/** Wraps markdown unordered-list lines in a list element. */
function unorderedList(match: string): string {
    const items = match.trim().split('\n').map(unorderedItem).join('');
    return `<ul>${items}</ul>`;
}

/** Converts a markdown ordered-list line to an item. */
function orderedItem(line: string): string {
    const text = line.replace(/^\d+\.\s+/, '');
    return `<li>${text}</li>`;
}

/** Wraps markdown ordered-list lines in a list element. */
function orderedList(match: string): string {
    const items = match.trim().split('\n').map(orderedItem).join('');
    return `<ol>${items}</ol>`;
}

/** Wraps a plain-text markdown block in a paragraph when needed. */
function paragraph(block: string): string {
    block = block.trim();
    if (!block) return '';
    // Don't wrap if already wrapped in a block element
    if (/^<(?:ul|ol|li|hr|div|p|h[1-6]|blockquote|table|pre)/i.test(block)) {
        return block;
    }
    // Single newlines within a paragraph become <br>
    return '<p>' + block.replace(/\n/g, '<br/>') + '</p>';
}

/**
 * Lightweight markdown-to-HTML converter.
 * Supports: **bold**, *italic*, [link](url), - list items, ordered lists, paragraphs, line breaks, hr,
 * ## headings, > blockquotes, {link:slug} page links.
 */
function markdownToHtml(md: string): string {
    if (!md) return '';

    // Replace {link:slug} with a visual placeholder
    let html = md.replace(/\{link:([a-z0-9_-]+)\}/gi, pageLink);

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
    html = html.replace(/^(?:>\s+.+\n?)+/gm, blockquote);

    // Process unordered lists: lines starting with - or *
    html = html.replace(/^(?:[-*]\s+.+\n?)+/gm, unorderedList);

    // Process ordered lists: lines starting with 1. 2. etc
    html = html.replace(/^(?:\d+\.\s+.+\n?)+/gm, orderedList);

    // Paragraphs: double newline
    html = html
        .split(/\n{2,}/)
        .map(paragraph)
        .filter(Boolean)
        .join('\n');

    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: SAFE_TAGS,
        ALLOWED_ATTR: SAFE_ATTR,
        ALLOW_DATA_ATTR: false,
        ALLOWED_URI_REGEXP: SAFE_URI,
    });
}

export {markdownToHtml};
