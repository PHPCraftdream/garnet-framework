<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link;

use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;

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
 *
 * GET_LOCK and RELEASE_LOCK MUST run on the exact same mysqli connection —
 * MySQL scopes advisory locks per-connection, and RELEASE_LOCK issued from
 * any other connection is a silent no-op (returns 0, lock stays held). This
 * class therefore pins each held lock name to the specific IDbMySQLiLink
 * that acquired it (via DbPool::newLink(), never the pool's shared
 * getLink()/QueryEx path, which can hand back a different, currently-free
 * connection on every call) and always routes both GET_LOCK and
 * RELEASE_LOCK for that name through the same link.
 */
class NamedLock {
    /**
     * @var array<string, IDbMySQLiLink> Lock name => the connection currently holding it.
     */
    protected static array $owners = [];

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
     * Reentrant per lock name: if this process already holds the lock (an
     * entry in self::$owners), the same connection that originally acquired
     * it is reused, matching MySQL's own per-connection reentrancy for
     * GET_LOCK.
     *
     * @param string $name The lock name (any valid string, scoped to MySQL server).
     * @param int $timeoutSec Maximum seconds to wait for the lock. 0 = non-blocking.
     * @return bool true if the lock was acquired, false if a deadlock error occurred.
     * @throws DbException If the lock could not be acquired before timeout.
     */
    public static function acquire(string $name, int $timeoutSec): bool {
        $link = static::$owners[$name] ?? DbPool::get()->newLink();

        $rows = $link->query('SELECT GET_LOCK(?, ?) AS lk', [$name, $timeoutSec]);

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
            static::$owners[$name] = $link;

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
     * Always runs RELEASE_LOCK on the exact connection that acquired the
     * lock (see class docblock), never via a pool-shared link. Safe to call
     * when this process holds no record of the lock — a no-op in that case,
     * matching RELEASE_LOCK's own "NULL when not held" semantics.
     *
     * @param string $name The lock name to release.
     * @return void
     * @throws DbException If RELEASE_LOCK reports we didn't own the lock (0)
     *                      or that the lock name didn't exist on the owning
     *                      connection (NULL) — either indicates a state bug
     *                      rather than a normal double-release.
     */
    public static function release(string $name): void {
        $link = static::$owners[$name] ?? null;

        if ($link === null) {
            return;
        }

        unset(static::$owners[$name]);

        $rows = $link->query('SELECT RELEASE_LOCK(?) AS lk', [$name]);

        $first = is_array($rows) ? ($rows[0] ?? null) : null;
        $raw = is_array($first) ? ($first['lk'] ?? null) : null;

        // RELEASE_LOCK returns: 1 = released, 0 = held by someone else / not
        // owned by this connection, NULL = lock name did not exist.
        if ($raw === null) {
            throw new DbException(
                "RELEASE_LOCK('{$name}') returned NULL: lock did not exist on its owning connection"
            );
        }

        if ((int)$raw !== 1) {
            throw new DbException(
                "RELEASE_LOCK('{$name}') returned {$raw}: this connection did not own the lock"
            );
        }
    }
}
