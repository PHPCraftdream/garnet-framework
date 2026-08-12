<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link;

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;

/**
 * Wrapper around MySQL named advisory locks (GET_LOCK / RELEASE_LOCK).
 *
 * Provides both non-blocking probe mode (timeout=0, returns bool immediately)
 * and blocking mode with timeout (throws exception on timeout). Useful for
 * serialising work across multiple processes without row-level locks.
 *
 * Locks are connection-scoped: reentrant on the same connection but blocking
 * across different connections. All locks are automatically released when
 * the connection closes.
 */
class NamedLock {
    /**
     * Try to acquire a named advisory lock in non-blocking mode.
     *
     * @param string $name The lock name (any valid string, scoped to MySQL server).
     * @return bool true if the lock was acquired, false if another connection
     *              holds the lock or if a deadlock error occurred.
     */
    public static function tryAcquire(string $name): bool {
        return static::acquire($name, 0);
    }

    /**
     * Acquire a named advisory lock, blocking up to the specified timeout.
     *
     * @param string $name The lock name (any valid string, scoped to MySQL server).
     * @param int $timeoutSec Maximum seconds to wait for the lock. 0 = non-blocking.
     * @return bool true if the lock was acquired, false if a deadlock error occurred.
     * @throws DbException If the lock could not be acquired before timeout.
     */
    public static function acquire(string $name, int $timeoutSec): bool {
        $rows = QueryEx::get()->exFetch('SELECT GET_LOCK(?, ?) AS lk', [$name, $timeoutSec]);

        if (!is_array($rows)) {
            return false;
        }

        $first = $rows[0] ?? null;

        if (!is_array($first)) {
            return false;
        }

        $result = (int)($first['lk'] ?? null);

        // GET_LOCK returns: 1 = success, 0 = timeout, NULL = error
        if ($result === 1) {
            return true;
        }

        if ($result === 0 && $timeoutSec > 0) {
            throw new DbException(
                "Failed to acquire lock '{$name}' after {$timeoutSec}s timeout"
            );
        }

        return false;
    }

    /**
     * Release a lock previously acquired by tryAcquire() or acquire().
     *
     * Safe to call when no lock is held — RELEASE_LOCK returns NULL in that case.
     *
     * @param string $name The lock name to release.
     * @return void
     */
    public static function release(string $name): void {
        QueryEx::get()->ex('SELECT RELEASE_LOCK(?)', [$name]);
    }
}
