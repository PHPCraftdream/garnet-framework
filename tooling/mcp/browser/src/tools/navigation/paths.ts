/**
 * Приведение пути к виду, который понимает текущая ОС.
 */

import { resolve as pathResolve } from 'node:path';

/**
 * Normalise a file path so `/tmp/x.png`, `D:\tmp\x.png`, `D:/tmp/x.png`
 * and `C:\Users\...` all resolve to the correct native absolute path on
 * the current OS. Handles Git-Bash/MSYS `/c/Users/…` → `C:\Users\…` on
 * Windows and is a no-op on POSIX for already-absolute paths.
 */
export function toNativePath(p: string): string {
  // Git-Bash / MSYS mount: /c/Users/… → C:\Users\…
  if (process.platform === 'win32' && /^\/[a-zA-Z]\//.test(p)) {
    p = p[1].toUpperCase() + ':' + p.slice(2);
  }
  return pathResolve(p);
}
