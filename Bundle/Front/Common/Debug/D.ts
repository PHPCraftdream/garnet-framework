/**
 * Garnet Debug Logger
 *
 * Lightweight debug logger for business code. Ships to zero bytes in production.
 *
 * Usage:
 *   import { D } from '@common/Debug/D';
 *   D('booking.create', { slotId: 5, cost: 500 });
 *   D('auth.login', 'success');
 *
 * Build modes:
 *   - Production (__GARNET_DEBUG__ = false): D is an empty function.
 *     The minifier dead-code-eliminates all D() calls and their arguments.
 *     Zero bytes in the production bundle.
 *
 *   - Debug (GARNET_DEBUG=1): D writes to window.__GARNET_LOG__[],
 *     which the garnet-browser-mcp collector drains into its ring buffer.
 *
 * The __GARNET_DEBUG__ constant is replaced at build time by rspack's
 * DefinePlugin (or equivalent). If the constant is not defined at all,
 * D falls back to a no-op — safe for any build configuration.
 */

declare const __GARNET_DEBUG__: boolean;

type DebugLogFn = (cat: string, ...data: any[]) => void;

export const D: DebugLogFn =
  typeof __GARNET_DEBUG__ !== 'undefined' && __GARNET_DEBUG__
    ? (cat: string, ...data: any[]) => {
        const w = window as any;
        if (!w.__GARNET_LOG__) w.__GARNET_LOG__ = [];
        w.__GARNET_LOG__.push({
          t: Date.now(),
          cat,
          src: 'D',
          msg: data.length === 1 ? data[0] : data,
        });
      }
    : (() => {}) as DebugLogFn;
