/**
 * Format a unix-ms timestamp as HH:MM:SS.
 */
export function formatTimestamp(ms: number): string {
  const d = new Date(ms);
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

/**
 * Convert a simple glob pattern (e.g. `net.*`) into a RegExp.
 * Supports `*` (any chars) and `?` (single char).
 */
export function globToRegex(pattern: string): RegExp {
  let escaped = pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&');
  escaped = escaped.replace(/\*/g, '.*');
  escaped = escaped.replace(/\?/g, '.');
  return new RegExp('^' + escaped + '$');
}

/**
 * Return text result in MCP format.
 */
export function textResult(text: string, isError = false) {
  return {
    content: [{ type: 'text' as const, text }],
    ...(isError ? { isError: true } : {}),
  };
}

/**
 * Return image result in MCP format.
 */
export function imageResult(base64: string, mimeType = 'image/png') {
  return {
    content: [{ type: 'image' as const, data: base64, mimeType }],
  };
}
