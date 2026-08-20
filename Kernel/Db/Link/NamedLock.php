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
 *
 * Because the pinned link is pushed into DbPool's shared pool (newLink()
 * registers it there too), unrelated code elsewhere in the request can
 * borrow that same connection via DbPool::getLink() for an async query
 * while we hold the named lock. release() therefore drains any in-flight
 * async work on the pinned link (isBusy()/poll()) before issuing
 * RELEASE_LOCK, instead of letting query() throw "Link is busy".
 */
class NamedLock {
    /**
     * @var array<string, array{link: IDbMySQLiLink, count: int}> Lock name =>
     *      the connection currently holding it plus how many nested
     *      acquire() calls (this process) are holding it, so a matching
     *      number of release() calls is required before RELEASE_LOCK is
     *      actually issued. Mirrors MySQL's own per-connection GET_LOCK
     *      reentrancy/hold-count semantics.
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
     * entry in self::$owners), the hold count is simply incremented and the
     * same connection that originally acquired it is reused WITHOUT issuing
     * another GET_LOCK query — MySQL already knows this connection holds
     * the lock, so a matching number of release() calls is required to
     * actually unlock it (see the $owners docblock).
     *
     * @param string $name The lock name (any valid string, scoped to MySQL server).
     * @param int $timeoutSec Maximum seconds to wait for the lock. 0 = non-blocking.
     * @return bool true if the lock was acquired, false if a deadlock error occurred.
     * @throws DbException If the lock could not be acquired before timeout.
     */
    public static function acquire(string $name, int $timeoutSec): bool {
        $existing = static::$owners[$name] ?? null;

        if ($existing !== null) {
            $existing['count']++;
            static::$owners[$name] = $existing;

            return true;
        }

        $link = DbPool::get()->newLink();

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
            static::$owners[$name] = ['link' => $link, 'count' => 1];

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
     * Reentrant: only decrements the hold count. RELEASE_LOCK is only
     * actually issued once the count reaches 0 (see the $owners docblock
     * and acquire()'s reentrant path).
     *
     * Because the pinned link is shared-pool-visible, unrelated code may be
     * running an async query on it when this is called. We drain that
     * in-flight work first (isBusy()/poll()) instead of letting
     * RELEASE_LOCK's query() throw "Link is busy" — that failure mode has
     * nothing to do with lock ownership and must not orphan the lock.
     *
     * The $owners entry for this name is only removed AFTER RELEASE_LOCK
     * has actually succeeded (or after we've decided not to retry it). If
     * the query throws for a genuine state error (see below), the entry is
     * intentionally left in place so a subsequent release() call can retry
     * rather than silently forgetting a lock that MySQL still holds.
     *
     * @param string $name The lock name to release.
     * @return void
     * @throws DbException If RELEASE_LOCK reports we didn't own the lock (0)
     *                      or that the lock name didn't exist on the owning
     *                      connection (NULL) — either indicates a state bug
     *                      rather than a normal double-release. This is
     *                      deliberately still allowed to throw (unlike the
     *                      busy-link case above, which is drained and never
     *                      throws): a "you don't own this lock" result means
     *                      something outside this class already released it
     *                      behind our back, which is a real bug worth
     *                      surfacing loudly rather than swallowing.
     */
    public static function release(string $name): void {
        $owner = static::$owners[$name] ?? null;

        if ($owner === null) {
            return;
        }

        if ($owner['count'] > 1) {
            $owner['count']--;
            static::$owners[$name] = $owner;

            return;
        }

        $link = $owner['link'];

        // Drain any in-flight async query left on this connection by
        // unrelated code that borrowed it from DbPool's shared pool before
        // issuing RELEASE_LOCK, so we never hit query()'s "Link is busy"
        // DbException here.
        while ($link->isBusy()) {
            $link->poll();
        }

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

        unset(static::$owners[$name]);
    }
}
