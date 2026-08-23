<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link;

use mysqli_sql_exception;
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
 * that acquired it (via DbPool::newLink() through acquireLink(), never the
 * pool's shared getLink()/QueryEx path, which can hand back a different,
 * currently-free connection on every call) and always routes both GET_LOCK
 * and RELEASE_LOCK for that name through the same link.
 *
 * Because the pinned link is pushed into DbPool's shared pool (newLink()
 * registers it there too), unrelated code elsewhere in the request can
 * borrow that same connection via DbPool::getLink() for an async query
 * while we hold the named lock. release() therefore drains any in-flight
 * async work on the pinned link (isBusy()/poll()) before issuing
 * RELEASE_LOCK, instead of letting query() throw "Link is busy".
 *
 * All distinct lock names acquired by this process share ONE dedicated
 * connection (self::$sharedLink), opened lazily on the first acquire()
 * and reused for every subsequent name, instead of opening a brand new
 * connection per name. This is safe because MySQL's GET_LOCK/RELEASE_LOCK
 * allow a single connection to hold multiple DIFFERENT named locks at
 * once (only re-acquiring the SAME name replaces/reentrant-counts on that
 * connection — see MySQL docs for GET_LOCK); self::$owners is already
 * keyed per-name, so it naturally supports one connection backing many
 * held locks simultaneously. Without this, a process taking N distinct
 * named locks (e.g. a CLI job looping per-account locks) would leak N
 * never-reclaimed connections into DbPool, pushing toward
 * max_connections.
 *
 * The shared connection is not liveness-checked on reuse: acquireLink()
 * only tests self::$sharedLink for null. A link that died naturally on
 * the server side (wait_timeout expiry, KILL CONNECTION, network drop)
 * is instead detected reactively, inside acquire(): the mysqli
 * "connection is gone" error signature on the GET_LOCK query triggers
 * a discard of all tracking state (MySQL released every lock the dead
 * session held when it dropped the session) and a single retry on a
 * freshly opened link. See the catch inside acquire() and
 * isConnectionGoneException().
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
     * @var IDbMySQLiLink|null The single connection shared by every
     *      distinct lock name this process acquires (see class docblock).
     *      Opened lazily by acquireLink() on first use. Reset to null by
     *      reset() when it can no longer be assumed usable (e.g. after
     *      DbPool::closeAll()), so the next acquire() opens a fresh one;
     *      acquire() also replaces it reactively when the underlying
     *      connection has died naturally (see the catch in acquire()).
     */
    protected static ?IDbMySQLiLink $sharedLink = null;

    /**
     * @var bool Whether this class has already subscribed to
     *      DbPool::onCloseAll(). Guards against registering the same
     *      reset() callback multiple times (e.g. if this class is
     *      "loaded" more than once in ways that re-run static init logic
     *      in test processes).
     */
    protected static bool $closeAllHookRegistered = false;

    /**
     * Return the single connection shared across all lock names held by
     * this process, opening it on first use. See class docblock for why
     * one connection can safely back multiple distinct named locks.
     *
     * Also ensures this class is subscribed to DbPool::onCloseAll() so
     * that reset() runs whenever the pool's connections are closed out
     * from under us (see reset()'s docblock).
     *
     * @return IDbMySQLiLink
     */
    protected static function acquireLink(): IDbMySQLiLink {
        if (!static::$closeAllHookRegistered) {
            DbPool::onCloseAll(static function (): void {
                static::reset();
            });

            static::$closeAllHookRegistered = true;
        }

        if (static::$sharedLink === null) {
            static::$sharedLink = DbPool::get()->newLink();
        }

        return static::$sharedLink;
    }

    /**
     * Whether $e is the signature of the shared connection being gone,
     * as surfaced through DbMySQLiLink::query()'s wrapping: the previous
     * exception is the mysqli driver error CR_SERVER_GONE_ERROR (2006,
     * "MySQL server has gone away") or CR_SERVER_LOST (2013, "Lost
     * connection to MySQL server during query") -- what a query sees
     * after the server dropped the session (wait_timeout expiry,
     * KILL CONNECTION, network drop).
     *
     * Deliberately narrow so acquire()'s retry-once path can never mask
     * an unrelated failure: it matches neither the wrapper's own
     * "Link is busy" DbException (which has no previous exception at
     * all) nor any other mysqli error code. The codes are read off the
     * previous mysqli_sql_exception (the driver's own classification)
     * rather than the wrapping DbException.
     *
     * This recognizes the default mysqli error mode (PHP 8.1+ throws
     * mysqli_sql_exception). Apps that explicitly disable mysqli error
     * reporting see a different shape, which this intentionally does
     * not match -- the framework does not defend against dead
     * connections anywhere else either.
     *
     * @param DbException $e
     * @return bool
     */
    protected static function isConnectionGoneException(DbException $e): bool {
        $previous = $e->getPrevious();

        if (!$previous instanceof mysqli_sql_exception) {
            return false;
        }

        return in_array($previous->getCode(), [2006, 2013], true);
    }

    /**
     * Discard all tracked lock ownership state without attempting to
     * RELEASE_LOCK first.
     *
     * This is NOT a graceful release: it exists for the case where the
     * underlying connections may already be closed/unusable (e.g. called
     * from DbPool::closeAll() via the onCloseAll() hook, or a long-running
     * CLI worker that reconnects periodically), so issuing RELEASE_LOCK
     * queries here would itself fail. Clearing $owners and $sharedLink
     * simply makes this process forget it was ever holding anything, so
     * the next acquire() for any name starts clean with a fresh
     * connection. Do NOT call this as a substitute for release() in normal
     * control flow -- MySQL's own lock accounting is unaffected by this
     * method; only this process's bookkeeping is reset.
     *
     * Deliberately does NOT close the underlying mysqli handle before
     * nulling $sharedLink, for two reasons:
     *
     *  1. Its primary caller is the DbPool::onCloseAll() hook, which
     *     runs AFTER closeAll() has already closed every handle; closing
     *     again would throw \Error ("mysqli object is already closed",
     *     PHP 8.1+) out of the middle of closeAll().
     *  2. For the manual CLI-worker use case the link stays registered
     *     in DbPool's shared list (there is no removal API): a locally
     *     closed handle still looks idle to DbPool::getLink() (isBusy()
     *     is wrapper state, not connection state), so unrelated queries
     *     would be handed a dead handle -- and a later closeAll() would
     *     hit the same double-close \Error. The companion call for
     *     actually retiring the connection is DbPool::closeAll() itself,
     *     which closes every handle (releasing all its locks
     *     server-side) and then triggers this reset via the hook.
     *
     * @return void
     */
    public static function reset(): void {
        static::$owners = [];
        static::$sharedLink = null;
    }

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

        $link = static::acquireLink();

        try {
            $rows = $link->query('SELECT GET_LOCK(?, ?) AS lk', [$name, $timeoutSec]);
        } catch (DbException $e) {
            // Since every name shares ONE link, a single natural
            // connection death would otherwise poison locking for the
            // whole process: every subsequent acquire() for ANY name
            // would query the dead handle. Retry once on a fresh link,
            // but ONLY for the well-identified "connection is gone"
            // mysqli signature -- unrelated failures are rethrown
            // untouched, since blanket-retrying those could mask real
            // bugs.
            if (!static::isConnectionGoneException($e)) {
                throw $e;
            }

            // The retry cannot double-hold anything: advisory locks are
            // per-connection and MySQL released everything the dead
            // session held when it dropped the session, so the failed
            // GET_LOCK never took (or no longer holds) effect.
            //
            // reset() also discards every $owners entry: they all pin
            // names to this same dead link (all names share it), and
            // acquire()'s reentrant fast path above would otherwise
            // return true for names MySQL no longer holds for us -- the
            // same stale-bookkeeping lie task #177 guarded release()
            // against.
            static::reset();

            $link = static::acquireLink();

            $rows = $link->query('SELECT GET_LOCK(?, ?) AS lk', [$name, $timeoutSec]);
        }

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
     * in-flight work first (via DbPool::pollLinks()) instead of letting
     * RELEASE_LOCK's query() throw "Link is busy" — that failure mode has
     * nothing to do with lock ownership and must not orphan the lock.
     *
     * The $owners entry for this name is ALWAYS resolved once release()
     * reaches the actual RELEASE_LOCK attempt, no matter how that attempt
     * ends -- success, a 0/NULL "you don't own it" state error, or a
     * genuine exception thrown by the drain/query itself (e.g. a dead
     * connection). See the try/finally inside. A stale entry must never
     * survive a failed release: acquire()'s reentrant fast path trusts it
     * and would return true WITHOUT a real GET_LOCK, so callers would
     * believe they hold a lock MySQL never granted them (mutual exclusion
     * silently void -- unacceptable on money paths like AccountBalance).
     * Forgetting the entry is the safe direction: the next acquire()
     * re-issues a real GET_LOCK (instant and reentrant if this connection
     * still holds the name; correctly refused if someone else does), and a
     * failed release() could never be retried into success anyway -- 0/NULL
     * mean MySQL itself knows this connection does not own the lock, and a
     * dead connection has already had all its locks released by the server.
     *
     * @param string $name The lock name to release.
     * @return void
     * @throws DbException If RELEASE_LOCK reports we didn't own the lock (0)
     *                      or that the lock name didn't exist on the owning
     *                      connection (NULL) — either indicates a state bug
     *                      rather than a normal double-release. This is
     *                      deliberately still allowed to throw (unlike the
     *                      busy-link case above, which is drained silently
     *                      and only throws if its bounded drain deadline
     *                      expires): a "you don't own this lock" result means
     *                      something outside this class already released it
     *                      behind our back, which is a real bug worth
     *                      surfacing loudly rather than swallowing.
     *                      Separately, draining a busy owning link is
     *                      bounded by DbPool::pollLinks()' deadline, whose
     *                      timeout DbException propagates (see the inline
     *                      comment in release()).
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

        // Everything from here on can fail in ways we cannot recover from
        // by keeping the $owners entry (see docblock): the drain may throw
        // on a dead connection, query() may throw ("MySQL server has gone
        // away" etc.), or the 0/NULL state errors below throw deliberately.
        // In ALL of those cases the entry must be dropped, never left as a
        // corpse that acquire()'s reentrant fast path would blindly trust.
        try {
            // Drain any in-flight async query left on this connection by
            // unrelated code that borrowed it from DbPool's shared pool
            // before issuing RELEASE_LOCK, so we never hit query()'s
            // "Link is busy" DbException here.
            //
            // Delegated to DbPool::pollLinks() (finishAll=true) instead of
            // a hand-rolled `while (isBusy()) poll()` loop: poll() wraps a
            // NON-blocking mysqli_poll(..., 0), so such a loop is a 100%-CPU
            // busy-spin (~82k iterations/s measured) for the whole duration
            // of the in-flight query -- and an unbounded hang if the link
            // never goes idle. pollLinks() waits in a real 50ms kernel-level
            // mysqli_poll() per iteration and enforces the same bounded
            // deadline as pollFinishAll().
            //
            // If that deadline expires while the link is still busy,
            // pollLinks()' own purpose-built timeout DbException propagates;
            // release() does NOT proceed to RELEASE_LOCK on a still-busy
            // link (that would only trade the clear timeout error for
            // query()'s opaque "Link is busy"). This follows the existing
            // precedent: every other pollLinks()/pollFinishAll() caller
            // (Account::readDataAsyncPollFinishAll(),
            // Session::readDataAsyncPollFinishAll()) likewise lets the
            // timeout DbException bubble up and merely declares @throws.
            // The finally below still drops the $owners entry in that case,
            // so a timed-out release cannot leave a stale-owner corpse.
            $links = [$link];
            DbPool::pollLinks($links);

            $rows = $link->query('SELECT RELEASE_LOCK(?) AS lk', [$name]);

            $first = is_array($rows) ? ($rows[0] ?? null) : null;
            $raw = is_array($first) ? ($first['lk'] ?? null) : null;

            // RELEASE_LOCK returns: 1 = released, 0 = held by someone else /
            // not owned by this connection, NULL = lock name did not exist.
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
        } finally {
            unset(static::$owners[$name]);
        }
    }
}
