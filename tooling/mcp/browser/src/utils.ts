import { readFileSync, existsSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';

/**
 * Search for `.garnet_debug_token` starting from `startDir`, walking up to `maxLevels` parent dirs.
 */
export function findTokenFile(startDir?: string, maxLevels = 5): string | null {
  let dir = startDir ?? process.cwd();

  for (let i = 0; i <= maxLevels; i++) {
    const candidate = join(dir, '.garnet_debug_token');
    if (existsSync(candidate) && statSync(candidate).isFile()) {
      return candidate;
    }
    const parent = dirname(dir);
    if (parent === dir) break; // filesystem root
    dir = parent;
  }

  return null;
}

/**
 * Read the debug token from the token file, trimmed. Throws if not found.
 */
export function readToken(startDir?: string): string {
  const file = findTokenFile(startDir);
  if (!file) {
    throw new Error(
      'Cannot find .garnet_debug_token in current directory or parent directories. ' +
      'Create the file with a secret token value.'
    );
  }
  const token = readFileSync(file, 'utf-8').trim();
  if (!token) {
    throw new Error(`.garnet_debug_token at ${file} is empty`);
  }
  return token;
}

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
