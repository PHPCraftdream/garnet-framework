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
 * while we hold the named lock. Both acquire() and release() therefore
 * drain any in-flight async work on that link first (via drainLink(),
 * which delegates to DbPool::pollLinks()) before issuing their own
 * GET_LOCK / RELEASE_LOCK query, instead of letting query() throw
 * "Link is busy".
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
 * freshly opened link; release() treats the same signature as benign
 * (the dead session's locks are already gone server-side). See the
 * catch inside acquire(), release(), and isConnectionGoneException().
 */
class NamedLock {
    /**
     * @var array<string, array{link: IDbMySQLiLink, count: int, releasePending?: bool}> Lock name =>
     *      the connection currently holding it plus how many nested
     *      acquire() calls (this process) are holding it, so a matching
     *      number of release() calls is required before RELEASE_LOCK is
     *      actually issued. Mirrors MySQL's own per-connection GET_LOCK
     *      reentrancy/hold-count semantics.
     *
     *      releasePending is set only by release() when a genuine drain
     *      timeout (the link still busy after DbPool::pollLinks()' whole
     *      bounded deadline) makes RELEASE_LOCK impossible to attempt:
     *      the entry is kept so the next acquire()/release() for this
     *      name finishes the RELEASE_LOCK before doing anything else
     *      (see release()).
     *
     *      INVARIANT: every entry's "link" is ALWAYS the same object as
     *      self::$sharedLink. True today because acquireLink() opens ONE
     *      connection shared by all names, and reset() is the only code
     *      that nulls self::$sharedLink, always clearing $owners in the
     *      same call. Both connection-death recovery paths -- acquire()'s
     *      retry-once catch and release()'s benign dead-connection path --
     *      rely on this: they reset() ALL of $owners because every entry
     *      is equally void once the shared connection dies (MySQL released
     *      them all when the session dropped). If a future change ever
     *      reintroduces per-name connections, or anything else that could
     *      leave $owners entries and self::$sharedLink pointing at
     *      different links, those reset() calls must be revisited too --
     *      do not weaken this invariant without updating them.
     */
    protected static array $owners = [];

    /**
     * @var IDbMySQLiLink|null The single connection shared by every
     *      distinct lock name this process acquires (see class docblock).
     *      Opened lazily by acquireLink() on first use. Reset to null by
     *      reset() when it can no longer be assumed usable (e.g. after
     *      DbPool::closeAll()), so the next acquire() opens a fresh one;
     *      acquire() also replaces it reactively when the underlying
     *      connection has died naturally (see the catch in acquire()),
     *      as does release() when it detects the same death (see the
     *      catch in release()).
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
     * as surfaced through DbMySQLiLink::query()/poll()'s wrapping: the
     * previous exception is a mysqli driver error for a dropped
     * connection -- CR_SERVER_GONE_ERROR (2006, "MySQL server has gone
     * away"), CR_SERVER_LOST (2013, "Lost connection to MySQL server
     * during query"), CR_SERVER_LOST_EXTENDED (2055), or
     * ER_CLIENT_INTERACTION_TIMEOUT (4031, MySQL 8.0.24+ -- the actual
     * wire-level signature that the most common natural death there,
     * wait_timeout expiry, produces) -- i.e. what a query sees after the
     * server dropped the session (wait_timeout expiry, KILL CONNECTION,
     * network drop).
     *
     * Deliberately narrow so the recovery paths can never mask an
     * unrelated failure: it matches neither the wrapper's own
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

        return in_array($previous->getCode(), [2006, 2013, 2055, 4031], true);
    }

    /**
     * Drain any in-flight async query left on $link by unrelated code
     * that borrowed it from DbPool's shared pool, so the caller's own
     * subsequent sync query() never hits "Link is busy".
     *
     * Delegated to DbPool::pollLinks() (finishAll=true) instead of a
     * hand-rolled `while (isBusy()) poll()` loop: poll() wraps a
     * NON-blocking mysqli_poll(..., 0), so such a loop is a 100%-CPU
     * busy-spin (~82k iterations/s measured) for the whole duration of
     * the in-flight query -- and an unbounded hang if the link never
     * goes idle. pollLinks() waits in a real 50ms kernel-level
     * mysqli_poll() per iteration and enforces the same bounded
     * deadline as pollFinishAll().
     *
     * Exactly two failure shapes are rethrown; everything else is
     * deliberately swallowed:
     *
     *  - "Connection gone" (isConnectionGoneException()): rethrown so
     *    the caller's own connection-death handling fires (acquire():
     *    reset + retry once on a fresh link; release(): benign, the
     *    dead session's locks were already freed server-side).
     *
     *  - pollLinks()' own deadline timeout (link genuinely still busy
     *    for the whole deadline): rethrown -- no query can be issued on
     *    a still-busy link, and that in-flight work belongs to whoever
     *    borrowed the link, not to us.
     *
     *  - Any OTHER DbException -- i.e. pollLinks() propagating poll()'s
     *    wrap of a FAILED async query (unknown table, deadlock, ...):
     *    SWALLOWED. DbMySQLiLink::poll() clears the link's busy flag in
     *    its catch BEFORE rethrowing, so by the time the exception gets
     *    here the link is idle again: the failure belonged to the
     *    unrelated async query, not to the connection, and the caller's
     *    own query is the next real health probe. Treating this shape
     *    as fatal (as release() did before) abandoned operations that
     *    would have succeeded -- e.g. it left MySQL's advisory hold
     *    count permanently un-decremented, denying the lock name to
     *    every other process forever.
     *
     * @param IDbMySQLiLink $link
     * @return void
     * @throws DbException The connection is gone, or the drain deadline
     *                     expired while the link was still busy.
     */
    protected static function drainLink(IDbMySQLiLink $link): void {
        try {
            $links = [$link];

            DbPool::pollLinks($links);
        } catch (DbException $e) {
            if (!static::isConnectionGoneException($e) && !$link->isBusy()) {
                return;
            }

            throw $e;
        }
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
     * $owners and $sharedLink are always cleared together -- see the
     * INVARIANT on self::$owners for why they must never diverge.
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
     * If this name's entry is flagged releasePending (a previous release()
     * hit a genuine drain timeout), the pending RELEASE_LOCK is finished
     * FIRST — acquire() never builds on top of a half-released name.
     *
     * Like release(), this drains the shared link (drainLink()) before its
     * GET_LOCK query, so unrelated in-flight async work on the
     * shared-pool-visible link cannot make a genuinely-free lock name
     * spuriously fail with "Link is busy".
     *
     * @param string $name The lock name (any valid string, scoped to MySQL server).
     * @param int $timeoutSec Maximum seconds to wait for the lock. 0 = non-blocking.
     * @return bool true if the lock was acquired, false if a deadlock error occurred.
     * @throws DbException If the lock could not be acquired before timeout.
     */
    public static function acquire(string $name, int $timeoutSec): bool {
        $existing = static::$owners[$name] ?? null;

        if ($existing !== null) {
            if (!empty($existing['releasePending'])) {
                // A previous release() for this name hit a genuine drain
                // timeout and left the RELEASE_LOCK pending. Finish it
                // BEFORE anything else: the entry's count is 1, so this
                // runs release()'s full drain + RELEASE_LOCK path. It may
                // itself throw (another timeout, or the 0/NULL state
                // errors), which is correct — acquire() must not build
                // on top of a half-released name. On success the entry
                // is gone and control falls through to a real GET_LOCK.
                static::release($name);
            } else {
                $existing['count']++;
                static::$owners[$name] = $existing;

                return true;
            }
        }

        $link = static::acquireLink();

        try {
            // Drain first, mirroring release(): the shared link is
            // registered in DbPool's shared pool, so unrelated code can
            // have it busy with an in-flight async query at any moment.
            // Without this drain, query() below would throw "Link is
            // busy" — a DbException with NO wrapped previous exception,
            // so deliberately NOT a connection-gone case for the catch
            // below — and a genuinely-free lock name would spuriously
            // fail. drainLink() itself resolves the false-positive-busy
            // shapes; only the genuine timeout and connection-gone
            // shapes reach the handling below / the caller.
            static::drainLink($link);

            $rows = $link->query('SELECT GET_LOCK(?, ?) AS lk', [$name, $timeoutSec]);
        } catch (DbException $e) {
            // Since every name shares ONE link, a single natural
            // connection death would otherwise poison locking for the
            // whole process: every subsequent acquire() for ANY name
            // would query the dead handle. Retry once on a fresh link,
            // but ONLY for the well-identified "connection is gone"
            // mysqli signature -- unrelated failures (including the
            // drain's bounded-deadline timeout) are rethrown untouched,
            // since blanket-retrying those could mask real bugs.
            if (!static::isConnectionGoneException($e)) {
                throw $e;
            }

            // The retry cannot double-hold anything: advisory locks are
            // per-connection and MySQL released everything the dead
            // session held when it dropped the session, so the failed
            // GET_LOCK never took (or no longer holds) effect.
            //
            // reset() also discards every $owners entry: they all pin
            // names to this same dead link (all names share it — see
            // the INVARIANT on self::$owners), and acquire()'s reentrant
            // fast path above would otherwise return true for names
            // MySQL no longer holds for us -- the same stale-bookkeeping
            // lie task #177 guarded release() against.
            static::reset();

            $link = static::acquireLink();

            // The fresh link was just opened and never borrowed, so no
            // drain is needed before its query.
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
     * in-flight work first (via drainLink()) instead of letting
     * RELEASE_LOCK's query() throw "Link is busy" — that failure mode has
     * nothing to do with lock ownership and must not orphan the lock.
     *
     * Failure taxonomy for the drain + actual RELEASE_LOCK attempt, every
     * branch explicit. (A stale $owners entry must never survive into a
     * state it does not truthfully describe: acquire()'s reentrant fast
     * path trusts it and would return true WITHOUT a real GET_LOCK, so
     * callers would believe they hold a lock MySQL never granted them —
     * mutual exclusion silently void, unacceptable on money paths like
     * AccountBalance.)
     *
     *  - The drain reaps a FAILED async query: the exception says nothing
     *    about the connection (DbMySQLiLink::poll() clears the busy flag
     *    before rethrowing, so the link is idle again) and is swallowed
     *    inside drainLink(); the release proceeds normally and CAN
     *    succeed. This was #184: abandoning the release here left
     *    MySQL's hold count permanently un-decremented (the reentrant
     *    acquire() fast path never re-issues GET_LOCK, so nothing else
     *    would ever bring the count back down), denying the lock name
     *    to every other process forever.
     *
     *  - "Connection gone" (isConnectionGoneException()), from either
     *    the drain or the RELEASE_LOCK query itself: the most benign
     *    possible outcome. The server already released every lock that
     *    dead session held when the connection dropped, so there is
     *    nothing left to do. reset() discards ALL tracking state — every
     *    $owners entry pins to this same dead link (see the INVARIANT on
     *    self::$owners), so leaving any of them would feed acquire()'s
     *    reentrant fast path a lie — and nulls $sharedLink so the dead
     *    handle is not carried forward into the next acquireLink() call.
     *
     *  - A genuine drain timeout (the link still busy after pollLinks()'
     *    whole bounded deadline — RELEASE_LOCK truly cannot be attempted
     *    safely): the $owners entry is KEPT, flagged releasePending, and
     *    pollLinks()' timeout DbException propagates. The next acquire()
     *    or release() for this name finishes the pending RELEASE_LOCK
     *    before doing anything else, so the hold count can no longer
     *    drift up silently — but the failure still surfaces loudly.
     *    Chosen over retiring the whole shared connection because the
     *    connection is alive (merely busy with someone else's in-flight
     *    work — not a death, unlike the branch above), any other held
     *    names on it remain truthfully tracked, and the drastic option
     *    (DbPool::closeAll()) would also destroy every unrelated pooled
     *    connection mid-request.
     *
     *  - RELEASE_LOCK returning 0 or NULL: state bug — the entry is
     *    dropped and a DbException thrown (see @throws).
     *
     *  - Any other failure of the RELEASE_LOCK query itself: the entry
     *    is dropped and the exception rethrown — the safe direction
     *    (the next acquire() re-issues a real GET_LOCK, which is instant
     *    and reentrant if this connection still holds the name, and
     *    correctly refused if someone else does).
     *
     * @param string $name The lock name to release.
     * @return void
     * @throws DbException If RELEASE_LOCK reports we didn't own the lock (0)
     *                      or that the lock name didn't exist on the owning
     *                      connection (NULL) — either indicates a state bug
     *                      rather than a normal double-release. This is
     *                      deliberately still allowed to throw (unlike the
     *                      busy-link cases above, which are drained, and
     *                      the dead-connection case, which is benign): a
     *                      "you don't own this lock" result means something
     *                      outside this class already released it behind
     *                      our back, which is a real bug worth surfacing
     *                      loudly rather than swallowing. Separately, a
     *                      genuine drain timeout propagates pollLinks()'
     *                      bounded-deadline timeout DbException (with the
     *                      entry kept as release-pending), and any other
     *                      query failure is rethrown untouched.
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

        try {
            // Drain any in-flight async query left on this connection by
            // unrelated code that borrowed it from DbPool's shared pool
            // before issuing RELEASE_LOCK, so we never hit query()'s
            // "Link is busy" DbException here (see drainLink()).
            static::drainLink($link);

            $rows = $link->query('SELECT RELEASE_LOCK(?) AS lk', [$name]);
        } catch (DbException $e) {
            if (static::isConnectionGoneException($e)) {
                // Dead connection — surfaced by either the drain or the
                // RELEASE_LOCK query itself. MySQL already released every
                // lock this session held when it dropped it; reset()
                // clears all tracking state (every entry pins to this
                // same dead link) and nulls $sharedLink so the next
                // acquire() opens a fresh connection instead of
                // rediscovering the death the hard way.
                static::reset();

                return;
            }

            if ($link->isBusy()) {
                // The only still-busy failure shape drainLink() lets
                // through: a genuine drain timeout. RELEASE_LOCK cannot
                // be attempted; keep the entry flagged releasePending so
                // the next acquire()/release() for this name finishes
                // the release first, and let the bounded-deadline
                // timeout surface loudly.
                $owner['releasePending'] = true;
                static::$owners[$name] = $owner;

                throw $e;
            }

            // Any other failure (of the RELEASE_LOCK query itself; the
            // drain's false-positive shapes were already resolved inside
            // drainLink()): drop the entry — never leave a corpse the
            // reentrant fast path would blindly trust — and rethrow.
            unset(static::$owners[$name]);

            throw $e;
        }

        // The attempt itself resolved: the entry must not survive even
        // if the 0/NULL state checks below throw.
        unset(static::$owners[$name]);

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
    }
}
