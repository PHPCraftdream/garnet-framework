<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use Exception;
use mysqli_sql_exception;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use ReflectionMethod;
use ReflectionProperty;

describe('NamedLock Integration', function (): void {
    $dbAvailable = false;

    // Clean up any lingering locks after all tests
    afterAll(function () use (&$dbAvailable): void {
        if (!$dbAvailable) {
            return;
        }

        NamedLock::release('test_named_lock_1');
        NamedLock::release('test_named_lock_2');
        DbPool::closeAll();
    });

    beforeAll(function () use (&$dbAvailable): void {
        $dbConfigPath = __DIR__ . '/../../../../TestsInit/TestConfig/db.ini';

        if (!file_exists($dbConfigPath)) {
            echo "db.ini not found at {$dbConfigPath}\n";

            return;
        }

        $config = parse_ini_file($dbConfigPath);

        if (!isset($config['enabled']) || $config['enabled'] !== '1') {
            echo "enabled != 1 in db.ini\n";

            return;
        }

        IniConfig::defineDbIni($dbConfigPath);

        try {
            echo "Attempting to connect to database...\n";
            $pool = DbPool::get();
            $link = $pool->newLink();
            $result = $link->query('SELECT 1', []);

            if ($result) {
                echo "Database connection successful, setting dbAvailable = true\n";
                $dbAvailable = true;
            }
        } catch (Exception $e) {
            echo 'Database connection failed: ' . $e->getMessage() . "\n";
        }
    });

    describe('tryAcquire()', function () use (&$dbAvailable): void {
        it('acquires lock successfully when not held', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_1';
            NamedLock::release($lockName); // Clean up first

            $result = NamedLock::tryAcquire($lockName);

            expect($result)->toBe(true);

            // Clean up
            NamedLock::release($lockName);
        });

        it('returns false when lock is held by another connection', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_2';
            NamedLock::release($lockName); // Clean up first

            // Acquire lock on connection 1
            $pool = DbPool::get();
            $link1 = $pool->newLink();

            $link1->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            // Try to acquire on connection 2 (via QueryEx which uses pool's free link)
            $result = NamedLock::tryAcquire($lockName);

            expect($result)->toBe(false);

            // Clean up: release from connection 1
            $link1->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('allows reentrant acquisition on same connection', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_3';
            NamedLock::release($lockName); // Clean up first

            // Acquire once
            $result1 = NamedLock::tryAcquire($lockName);
            expect($result1)->toBe(true);

            // Acquire again on same connection (should succeed - reentrant)
            $result2 = NamedLock::tryAcquire($lockName);
            expect($result2)->toBe(true);

            // Clean up
            NamedLock::release($lockName);
        });

        it('stays held until every acquire() has a matching release() (reentrancy hold-count regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_reentrancy_holdcount';
            NamedLock::release($lockName); // Clean up first

            // Acquire the same name twice in this process (e.g. nested
            // withAccountLock()/recalculate() calls in AccountBalance).
            $result1 = NamedLock::tryAcquire($lockName);
            expect($result1)->toBe(true);

            $result2 = NamedLock::tryAcquire($lockName);
            expect($result2)->toBe(true);

            // First release() must NOT actually free the lock yet -- the
            // hold count only drops from 2 to 1. Prove it's still held by
            // trying (and failing) to acquire it from an independent
            // connection in between the two release() calls.
            NamedLock::release($lockName);

            $pool = DbPool::get();
            $probeLink = $pool->newLink();
            $stillHeld = $probeLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($stillHeld))->toBe(true);
            expect((int)($stillHeld[0]['lk'] ?? null))->toBe(0);

            // Second, matching release() must now actually free it.
            NamedLock::release($lockName);

            $result3 = $probeLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result3))->toBe(true);
            expect((int)($result3[0]['lk'] ?? null))->toBe(1);

            // Clean up
            $probeLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });
    });

    describe('acquire() with blocking timeout', function () use (&$dbAvailable): void {
        it('acquires lock immediately when not held', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_4';
            NamedLock::release($lockName); // Clean up first

            $result = NamedLock::acquire($lockName, 1);

            expect($result)->toBe(true);

            // Clean up
            NamedLock::release($lockName);
        });

        it('throws DbException when lock is held and timeout expires', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_5';
            NamedLock::release($lockName); // Clean up first

            // Acquire lock on connection 1
            $pool = DbPool::get();
            $link1 = $pool->newLink();
            $link1->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            // Try to acquire on connection 2 with timeout - should throw
            $exception = null;

            try {
                NamedLock::acquire($lockName, 1);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBeAnInstanceOf(DbException::class);
            expect($exception->getMessage())->toContain('Failed to acquire lock');
            expect($exception->getMessage())->toContain($lockName);
            expect($exception->getMessage())->toContain('timeout');

            // Clean up: release from connection 1
            $link1->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });
    });

    describe('release()', function () use (&$dbAvailable): void {
        it('actually frees the lock', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_6';
            NamedLock::release($lockName); // Clean up first

            // Acquire lock
            $result1 = NamedLock::tryAcquire($lockName);
            expect($result1)->toBe(true);

            // Release it
            NamedLock::release($lockName);

            // Now another connection should be able to acquire it
            $pool = DbPool::get();
            $link1 = $pool->newLink();
            $result2 = $link1->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result2))->toBe(true);
            expect(isset($result2[0]['lk']))->toBe(true);
            expect((int)$result2[0]['lk'])->toBe(1);

            // Clean up
            $link1->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('is safe to call when no lock is held', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_7';
            NamedLock::release($lockName); // Ensure not held

            // Should not throw
            NamedLock::release($lockName);

            expect(true)->toBe(true); // Just verify we got here
        });

        it('releases on the owning connection even when the pool has another free link busy with an async query (F-01 regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_8';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            // Make sure the pool already has at least one OTHER free link
            // sitting around before NamedLock acquires anything. Historically,
            // DbPool::getLink() (used internally by QueryEx, which the old
            // NamedLock implementation went through) hands out ANY currently
            // idle link -- so having a decoy free link in the pool is what
            // let RELEASE_LOCK land on the wrong connection.
            $decoyLink = $pool->newLink();

            // Acquire the named lock. With the fix, this pins the lock to
            // whichever connection actually issued GET_LOCK, not to
            // whatever DbPool::getLink() happens to return later.
            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            // Now put the decoy link under an in-flight ASYNC query so it is
            // "busy" for the rest of this test -- this reproduces the
            // original bug's precondition where the owning connection would
            // be busy and DbPool::getLink() would silently substitute some
            // other free connection for RELEASE_LOCK. Here we deliberately
            // leave the decoy (non-owning) link busy to prove release() does
            // NOT depend on, or get confused by, pool link availability.
            $decoyLink->queryAsync('SELECT SLEEP(0.2) AS s');
            expect($decoyLink->isBusy())->toBe(true);

            // Release must still succeed by routing RELEASE_LOCK to the
            // exact connection that acquired the lock, regardless of what
            // else is busy in the pool.
            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);

            // Drain the decoy's async query so it doesn't leak into other specs.
            while ($decoyLink->isBusy()) {
                $decoyLink->poll();
            }

            // The definitive proof the lock was actually released: a brand
            // new, independent connection can now acquire the same name.
            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect(isset($result[0]['lk']))->toBe(true);
            expect((int)$result[0]['lk'])->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('releases cleanly when the OWNING connection itself is busy with an async query (F-01 recommendation 3)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_owner_busy';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            // Acquire the named lock. This pins it to whichever connection
            // actually issued GET_LOCK.
            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            // Get the real owning link via reflection (the public API
            // intentionally does not expose the pinned connection) and put
            // THAT SAME connection -- not a decoy -- under an in-flight
            // async query. This reproduces the actual F-01 scenario: some
            // unrelated code borrows the owning connection from DbPool's
            // shared pool (e.g. via DbPool::getLink() for Account::saveData()
            // -> insertBatchAsync()) while the named lock is held.
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $ownerLink = $owners[$lockName]['link'];

            $ownerLink->queryAsync('SELECT SLEEP(0.2) AS s');
            expect($ownerLink->isBusy())->toBe(true);

            // release() must drain the in-flight async work on the owning
            // connection itself and then complete successfully -- no throw,
            // no orphaned lock -- instead of hitting query()'s "Link is
            // busy" DbException.
            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);

            // Definitive proof the lock was actually released: an
            // independent connection can now acquire the same name.
            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect(isset($result[0]['lk']))->toBe(true);
            expect((int)$result[0]['lk'])->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('drains a busy owning link without busy-spinning (bounded CPU across SELECT SLEEP(0.3))', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_owner_busy_drain_no_spin';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            // Put the OWNING connection under an in-flight async query with
            // a fixed 0.3s duration, so release() has real work to drain
            // (same reflection approach as the F-01 test above: the public
            // API intentionally does not expose the pinned connection).
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $ownerLink = $owners[$lockName]['link'];

            $ownerLink->queryAsync('SELECT SLEEP(0.3) AS s');
            expect($ownerLink->isBusy())->toBe(true);

            // Measure wall-clock AND process CPU time across release().
            // The old drain loop (`while (isBusy()) poll()`) wrapped a
            // NON-blocking mysqli_poll(..., 0), i.e. a 100%-CPU spin that
            // burns roughly cpu == elapsed (~0.3s here). The pollLinks()
            // drain must still take >= ~0.3s of wall time (it genuinely
            // waits for the query) while consuming almost no CPU.
            $cpuOf = static fn (array $ru): float => (float)$ru['ru_utime.tv_sec'] + (float)$ru['ru_stime.tv_sec']
                    + ((float)$ru['ru_utime.tv_usec'] + (float)$ru['ru_stime.tv_usec']) / 1000000.0;

            $cpuBefore = $cpuOf(getrusage());
            $startWall = microtime(true);

            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            $elapsed = microtime(true) - $startWall;
            $cpuUsed = $cpuOf(getrusage()) - $cpuBefore;

            expect($exception)->toBe(null);

            // The drain genuinely waited out the async query...
            expect($elapsed)->toBeGreaterThan(0.28);

            // ...without burning CPU while waiting. Against the old
            // busy-spin this fails loudly (cpuUsed ~= 0.3s); the 0.15s
            // margin leaves room for the handful of real milliseconds of
            // RELEASE_LOCK round-trip work plus CI jitter.
            expect($cpuUsed)->toBeLessThan(0.15);

            // Definitive proof the lock was actually released: an
            // independent connection can now acquire the same name.
            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect(isset($result[0]['lk']))->toBe(true);
            expect((int)$result[0]['lk'])->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('throws when RELEASE_LOCK reports this connection did not own the lock', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_9';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            // Acquire the lock via NamedLock (pins it to connection A,
            // recorded in NamedLock's internal owner map).
            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            // Take the lock away from connection A without NamedLock's
            // knowledge: release it directly via the exact link NamedLock
            // recorded (read via reflection on the protected owner map,
            // since the public API intentionally does not expose the
            // pinned connection), then have a second, independent
            // connection acquire the same name. NamedLock still believes
            // connection A owns it, so the next NamedLock::release() call
            // must observe RELEASE_LOCK returning 0 (not the owner) and
            // surface that as an error instead of silently returning.
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $ownerLinkA = $owners[$lockName]['link'];

            $ownerLinkA->query('SELECT RELEASE_LOCK(?)', [$lockName]);

            $rawLinkB = $pool->newLink();
            $rawLinkB->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBeAnInstanceOf(DbException::class);

            // Clean up: release from the connection that actually holds it.
            $rawLinkB->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('recovers after release() throws: no stale $owners entry, next tryAcquire() does a real GET_LOCK (#177 regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_release_throw_recovery';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            // Acquire via NamedLock: pins the lock to connection A (the
            // shared link) and records an $owners entry for it.
            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            // Sabotage the ownership state behind NamedLock's back:
            // release the lock directly on connection A (read via
            // reflection, since the public API does not expose the pinned
            // connection), then have an independent connection B take the
            // same name. MySQL now holds this lock for B; NamedLock still
            // believes A owns it.
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $ownerLinkA = $owners[$lockName]['link'];

            $ownerLinkA->query('SELECT RELEASE_LOCK(?)', [$lockName]);

            $linkB = $pool->newLink();
            $linkB->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            // release() must observe RELEASE_LOCK returning 0 (connection
            // A no longer owns the lock) and throw the state error...
            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBeAnInstanceOf(DbException::class);

            // ...and, critically, must not leave a stale $owners entry
            // behind. The next tryAcquire() has to issue a REAL GET_LOCK
            // against MySQL instead of trusting bookkeeping and blindly
            // returning true. Connection B genuinely holds the lock at
            // this point, so a real query returns 0 and tryAcquire()
            // must return false. (Against the bug, the stale entry hits
            // the reentrant fast path, skips the query entirely, returns
            // true -- silently voiding mutual exclusion for B.)
            $reacquired = NamedLock::tryAcquire($lockName);

            expect($reacquired)->toBe(false);

            $ownersAfter = $ownersProp->getValue();
            expect(isset($ownersAfter[$lockName]))->toBe(false);

            // Clean up: release from the connection that actually holds it.
            $linkB->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('completes the release when the drain reaps a FAILED async query and the link is idle again (#184 regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_release_drain_error';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            // Unrelated code borrows the OWNING link (read via reflection,
            // same technique as the tests above) and dispatches an async
            // query that FAILS server-side (unknown table). The failure
            // surfaces only when release()'s drain reaps it -- and
            // DbMySQLiLink::poll() clears the link's busy flag in its
            // catch BEFORE rethrowing, so the link is actually idle by
            // the time the drain exception reaches release(). A release
            // abandoned here (pre-#184 behavior) left MySQL's advisory
            // hold count permanently un-decremented.
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $ownerLink = $owners[$lockName]['link'];

            $ownerLink->queryAsync('SELECT * FROM test_named_lock_no_such_table_xyz');
            expect($ownerLink->isBusy())->toBe(true);

            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            // The failed async query was not ours; the release itself must
            // still complete.
            expect($exception)->toBe(null);

            // Definitive proof the lock was actually released: an
            // independent connection can now acquire the same name.
            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect(isset($result[0]['lk']))->toBe(true);
            expect((int)$result[0]['lk'])->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });
    });

    describe('connection reuse across distinct lock names', function () use (&$dbAvailable): void {
        it('does not grow the pool link count when acquiring N different lock names in sequence', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockNames = [
                'test_named_lock_reuse_1',
                'test_named_lock_reuse_2',
                'test_named_lock_reuse_3',
                'test_named_lock_reuse_4',
                'test_named_lock_reuse_5',
            ];

            foreach ($lockNames as $lockName) {
                NamedLock::release($lockName); // Clean up first
            }

            $pool = DbPool::get();

            // Prime the shared link (if not already open from an earlier
            // test) and record the baseline pool size AFTER that, so this
            // test only asserts on growth caused by acquiring further
            // distinct names, not on whether NamedLock's shared link
            // already happens to exist.
            NamedLock::tryAcquire($lockNames[0]);
            $baseline = $pool->getLinksCount();

            // Acquire the remaining names, interleaving so multiple names
            // are held at once at some point (2, 3 and 4 all held
            // simultaneously before any release()).
            expect(NamedLock::tryAcquire($lockNames[1]))->toBe(true);
            expect(NamedLock::tryAcquire($lockNames[2]))->toBe(true);
            expect(NamedLock::tryAcquire($lockNames[3]))->toBe(true);

            // Release one while others are still held.
            NamedLock::release($lockNames[1]);

            expect(NamedLock::tryAcquire($lockNames[4]))->toBe(true);

            expect($pool->getLinksCount())->toBe($baseline);

            // Clean up remaining held locks.
            NamedLock::release($lockNames[0]);
            NamedLock::release($lockNames[2]);
            NamedLock::release($lockNames[3]);
            NamedLock::release($lockNames[4]);
        });
    });

    describe('release-pending policy on a genuine drain timeout (#185)', function () use (&$dbAvailable): void {
        it('keeps the entry as release-pending on a genuine drain timeout, and a later release() completes it', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_release_timeout_pending';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');

            // Genuinely busy for longer than DbPool::pollLinks()' whole
            // 10s drain deadline: RELEASE_LOCK truly cannot be attempted
            // while this runs. This test therefore takes ~12s by design.
            $ownerLink = $ownersProp->getValue()[$lockName]['link'];
            $ownerLink->queryAsync('SELECT SLEEP(11.5) AS s');
            expect($ownerLink->isBusy())->toBe(true);

            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            // The bounded-deadline timeout still surfaces loudly...
            expect($exception)->toBeAnInstanceOf(DbException::class);
            expect($exception->getMessage())->toContain('timed out');

            // ...but the release is PENDING, not silently forgotten: the
            // entry survives, flagged, still pinned to the busy link.
            // (Pre-#185 behavior: the entry was dropped outright, leaking
            // MySQL's hold count forever.)
            $ownersAfter = $ownersProp->getValue();

            expect(isset($ownersAfter[$lockName]))->toBe(true);
            expect(($ownersAfter[$lockName]['releasePending'] ?? null))->toBe(true);
            expect($ownerLink->isBusy())->toBe(true);

            // Once the unrelated work is done (link idle again), a later
            // release() for the same name completes the pending
            // RELEASE_LOCK.
            $drain = [$ownerLink];
            DbPool::pollLinks($drain);
            expect($ownerLink->isBusy())->toBe(false);

            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);

            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect((int)($result[0]['lk'] ?? null))->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('a pending release is flushed by the next acquire() for the same name before re-acquiring', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_release_timeout_flush_on_acquire';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            // Simulate the exact state a genuine drain timeout leaves
            // behind (reproducing a real 10s timeout here would needlessly
            // double the suite runtime -- the e2e test above proves how
            // that state arises): the live entry, flagged release-pending,
            // on an idle link that still holds the lock server-side.
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            $owners = $ownersProp->getValue();
            $owners[$lockName]['releasePending'] = true;
            $ownersProp->setValue($owners);

            // This acquire() must FIRST finish the pending release
            // (RELEASE_LOCK really issued, MySQL hold count back to 0)
            // and THEN issue a real GET_LOCK...
            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            $ownersAfter = $ownersProp->getValue();

            expect(isset($ownersAfter[$lockName]))->toBe(true);
            expect((int)$ownersAfter[$lockName]['count'])->toBe(1);
            expect(array_key_exists('releasePending', $ownersAfter[$lockName]))->toBe(false);

            // ...so ONE matching release() actually frees it. If acquire()
            // had blindly taken the reentrant fast path instead (the
            // pre-#185 behavior), the hold count would be 2, this
            // release() would only decrement it, and the probe below
            // would get 0.
            NamedLock::release($lockName);

            $probe = $pool->newLink();
            $result = $probe->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect(is_array($result))->toBe(true);
            expect((int)($result[0]['lk'] ?? null))->toBe(1);

            // Clean up
            $probe->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });
    });

    describe('drain-before-query on a busy shared link (#186)', function () use (&$dbAvailable): void {
        it('acquires a genuinely-free lock while the shared link is busy with an unrelated async query', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockNameHeld = 'test_named_lock_acquire_drain_held';
            $lockNameFree = 'test_named_lock_acquire_drain_free';

            NamedLock::release($lockNameHeld); // Clean up first
            NamedLock::release($lockNameFree); // Clean up first

            $pool = DbPool::get();

            // Prime the shared link with one acquire/release cycle so
            // sharedLink exists and is idle, holding nothing.
            expect(NamedLock::tryAcquire($lockNameHeld))->toBe(true);
            NamedLock::release($lockNameHeld);

            // Prove the target name is genuinely free via an independent
            // connection.
            $probe = $pool->newLink();
            $free = $probe->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameFree]);

            expect((int)($free[0]['lk'] ?? null))->toBe(1);
            $probe->query('SELECT RELEASE_LOCK(?)', [$lockNameFree]);

            // Unrelated code borrows the shared link and leaves it busy.
            $sharedProp = new ReflectionProperty(NamedLock::class, 'sharedLink');
            $sharedLink = $sharedProp->getValue();

            $sharedLink->queryAsync('SELECT SLEEP(0.25) AS s');
            expect($sharedLink->isBusy())->toBe(true);

            // Before #186 this threw DbException('Link is busy') even
            // though the lock name was free.
            $exception = null;
            $acquired = false;

            try {
                $acquired = NamedLock::tryAcquire($lockNameFree);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);
            expect($acquired)->toBe(true);

            // We really hold it: an independent connection cannot take it.
            $contended = $probe->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameFree]);

            expect((int)($contended[0]['lk'] ?? null))->toBe(0);

            NamedLock::release($lockNameFree);

            // ...and releasing actually freed it.
            $freed = $probe->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameFree]);

            expect((int)($freed[0]['lk'] ?? null))->toBe(1);

            // Clean up
            $probe->query('SELECT RELEASE_LOCK(?)', [$lockNameFree]);
        });
    });

    describe('dead shared link recovery', function () use (&$dbAvailable): void {
        it('recovers on a fresh connection when the shared link dies server-side, purging stale owners (#180 regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockNameA = 'test_named_lock_dead_recovery_a';
            $lockNameB = 'test_named_lock_dead_recovery_b';

            NamedLock::release($lockNameA); // Clean up first
            NamedLock::release($lockNameB); // Clean up first

            $pool = DbPool::get();

            // Acquire lock A through NamedLock: primes the shared link
            // (if an earlier test has not already) and pins A to it.
            $acquiredA = NamedLock::tryAcquire($lockNameA);
            expect($acquiredA)->toBe(true);

            // Read the shared link via reflection (the public API
            // intentionally does not expose it) and ask MySQL which
            // server-side connection id it currently is.
            $sharedProp = new ReflectionProperty(NamedLock::class, 'sharedLink');
            $sharedLink = $sharedProp->getValue();

            $idRows = $sharedLink->query('SELECT CONNECTION_ID() AS id', []);
            $connId = (int)($idRows[0]['id'] ?? 0);
            expect($connId)->toBeGreaterThan(0);

            // Kill the shared connection SERVER-SIDE from an independent
            // connection. This is the faithful simulation of a natural
            // death (wait_timeout expiry, KILL CONNECTION, network
            // drop): the next query on the link sees exactly the
            // production failure shape, mysqli 2006 "MySQL server has
            // gone away" (or 2013 "lost connection"). A local
            // ->close() would produce a different, non-production
            // failure shape ("mysqli object is already closed").
            $killer = $pool->newLink();
            $killer->query("KILL CONNECTION {$connId}", []);
            usleep(100000); // let the server finish dropping the session

            // A tryAcquire() for a DIFFERENT name must recover: detect
            // the dead shared link, open a fresh one and get the lock.
            // Before the fix this threw DbException for every name in
            // this process from here on.
            $acquiredB = NamedLock::tryAcquire($lockNameB);
            expect($acquiredB)->toBe(true);

            // The retry must have happened on a genuinely new link...
            $newSharedLink = $sharedProp->getValue();
            expect($newSharedLink === $sharedLink)->toBe(false);

            // ...that really holds B (an independent connection cannot
            // take it)...
            $verify = $pool->newLink();

            $resultB = $verify->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameB]);
            expect(is_array($resultB))->toBe(true);
            expect(isset($resultB[0]['lk']))->toBe(true);
            expect((int)$resultB[0]['lk'])->toBe(0);

            // ...while A's stale bookkeeping must have been purged too:
            // MySQL auto-released A when it killed the session, so the
            // independent connection can now take A cleanly.
            $resultA = $verify->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameA]);
            expect(is_array($resultA))->toBe(true);
            expect(isset($resultA[0]['lk']))->toBe(true);
            expect((int)$resultA[0]['lk'])->toBe(1);

            // And release() of B must work, routing to the new owning
            // link.
            $exception = null;

            try {
                NamedLock::release($lockNameB);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);

            $resultB2 = $verify->query('SELECT GET_LOCK(?, 0) AS lk', [$lockNameB]);
            expect(is_array($resultB2))->toBe(true);
            expect(isset($resultB2[0]['lk']))->toBe(true);
            expect((int)$resultB2[0]['lk'])->toBe(1);

            // Clean up
            $verify->query('SELECT RELEASE_LOCK(?)', [$lockNameA]);
            $verify->query('SELECT RELEASE_LOCK(?)', [$lockNameB]);
        });

        it('release() treats an already-dead connection as benign: no throw, state fully reset (#187 regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_release_dead_connection';
            NamedLock::release($lockName); // Clean up first

            $pool = DbPool::get();

            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            $sharedProp = new ReflectionProperty(NamedLock::class, 'sharedLink');
            $sharedLink = $sharedProp->getValue();

            $idRows = $sharedLink->query('SELECT CONNECTION_ID() AS id', []);
            $connId = (int)($idRows[0]['id'] ?? 0);
            expect($connId)->toBeGreaterThan(0);

            // Natural-death simulation, same technique as the #180 test
            // above: KILL from an independent connection produces exactly
            // the production failure shape (mysqli 2006/2013).
            $killer = $pool->newLink();
            $killer->query("KILL CONNECTION {$connId}", []);
            usleep(100000); // let the server finish dropping the session

            // The server already released every lock that session held --
            // release() must treat this as already-done, not as an error.
            $exception = null;

            try {
                NamedLock::release($lockName);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);

            // The dead handle is not carried forward into the next
            // acquireLink()...
            expect($sharedProp->getValue())->toBe(null);

            // ...and no stale $owners entries survive (they all pinned to
            // the dead link).
            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            expect($ownersProp->getValue())->toBe([]);

            // The next acquire() opens a fresh connection and gets the
            // lock cleanly (MySQL freed it when the session died).
            expect(NamedLock::tryAcquire($lockName))->toBe(true);

            NamedLock::release($lockName);

            $verifyLink = $pool->newLink();
            $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$lockName]);

            expect((int)($result[0]['lk'] ?? null))->toBe(1);

            // Clean up
            $verifyLink->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });

        it('survives the full compound scenario: busy-link acquire, natural death, benign release, clean re-acquire', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockNameA = 'test_named_lock_compound_a';
            $lockNameB = 'test_named_lock_compound_b';

            NamedLock::release($lockNameA); // Clean up first
            NamedLock::release($lockNameB); // Clean up first

            $pool = DbPool::get();

            // 1. acquire(A) succeeds normally on the shared connection.
            expect(NamedLock::tryAcquire($lockNameA))->toBe(true);

            // 2. Unrelated code borrows the shared link and leaves it
            //    busy with an in-flight async query.
            $sharedProp = new ReflectionProperty(NamedLock::class, 'sharedLink');
            $sharedLink = $sharedProp->getValue();

            $sharedLink->queryAsync('SELECT SLEEP(0.25) AS s');
            expect($sharedLink->isBusy())->toBe(true);

            // 3. acquire(B), a different name, must drain first and
            //    succeed despite the busy link.
            expect(NamedLock::tryAcquire($lockNameB))->toBe(true);

            // 4. The shared connection dies naturally (KILL, faithful
            //    simulation per the #180 test above) while A and B are
            //    still logically held per $owners.
            $idRows = $sharedLink->query('SELECT CONNECTION_ID() AS id', []);
            $connId = (int)($idRows[0]['id'] ?? 0);
            expect($connId)->toBeGreaterThan(0);

            $killer = $pool->newLink();
            $killer->query("KILL CONNECTION {$connId}", []);
            usleep(100000); // let the server finish dropping the session

            // 5. release(A) treats the dead connection as benign: no
            //    exception, and all tracking state reset (including B's
            //    now-meaningless entry).
            $exception = null;

            try {
                NamedLock::release($lockNameA);
            } catch (DbException $e) {
                $exception = $e;
            }

            expect($exception)->toBe(null);
            expect($sharedProp->getValue())->toBe(null);

            $ownersProp = new ReflectionProperty(NamedLock::class, 'owners');
            expect($ownersProp->getValue())->toBe([]);

            // 6. acquire(A) again: recovers on a fresh connection and
            //    re-acquires cleanly (MySQL freed A when the session
            //    died).
            expect(NamedLock::tryAcquire($lockNameA))->toBe(true);

            // 7. The fresh hold is real: releasing it frees the name for
            //    an independent connection, and B (freed by the session
            //    death in step 4) is equally available again.
            NamedLock::release($lockNameA);

            $verifyLink = $pool->newLink();

            foreach ([$lockNameA, $lockNameB] as $probeName) {
                $result = $verifyLink->query('SELECT GET_LOCK(?, 0) AS lk', [$probeName]);

                expect((int)($result[0]['lk'] ?? null))->toBe(1);
                $verifyLink->query('SELECT RELEASE_LOCK(?)', [$probeName]);
            }
        });
    });

    describe('DbPool::closeAll() integration', function () use (&$dbAvailable): void {
        it('clears NamedLock::$owners so a subsequent tryAcquire() works cleanly on a fresh connection', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $lockName = 'test_named_lock_close_all';
            NamedLock::release($lockName); // Clean up first

            $acquired = NamedLock::tryAcquire($lockName);
            expect($acquired)->toBe(true);

            $ownersPropBefore = new ReflectionProperty(NamedLock::class, 'owners');
            $ownersBefore = $ownersPropBefore->getValue();

            expect(isset($ownersBefore[$lockName]))->toBe(true);

            // Simulate the connection lifecycle event this is guarding
            // against: the pool closes every mysqli handle it knows about,
            // including the one NamedLock pinned for this lock name.
            DbPool::closeAll();

            $ownersPropAfter = new ReflectionProperty(NamedLock::class, 'owners');
            $ownersAfter = $ownersPropAfter->getValue();

            // No stale reference to the now-closed connection should
            // remain.
            expect($ownersAfter)->toBe([]);

            // A subsequent tryAcquire() for the same name must work
            // cleanly on a fresh connection -- no attempt to touch the
            // closed handle.
            $exception = null;

            try {
                $reacquired = NamedLock::tryAcquire($lockName);
            } catch (DbException $e) {
                $exception = $e;
                $reacquired = false;
            }

            expect($exception)->toBe(null);
            expect($reacquired)->toBe(true);

            // Clean up.
            NamedLock::release($lockName);
        });
    });

    describe('isConnectionGoneException()', function (): void {
        // Unit-level checks (no DB needed): the method classifies the
        // wrapped mysqli driver error, so a constructed
        // mysqli_sql_exception carrying the code is a faithful input.
        $goneCodes = [
            2006 => 'CR_SERVER_GONE_ERROR',
            2013 => 'CR_SERVER_LOST',
            2055 => 'CR_SERVER_LOST_EXTENDED',
            4031 => 'ER_CLIENT_INTERACTION_TIMEOUT (MySQL 8.0.24+ wait_timeout expiry)',
        ];

        foreach ($goneCodes as $code => $label) {
            it("recognizes mysqli error code {$code} ({$label})", function () use ($code): void {
                $method = new ReflectionMethod(NamedLock::class, 'isConnectionGoneException');

                $e = new DbException('wrapped', 0, new mysqli_sql_exception('gone', $code));

                expect($method->invoke(null, $e))->toBe(true);
            });
        }

        it('does not match a DbException with no previous exception (e.g. "Link is busy")', function (): void {
            $method = new ReflectionMethod(NamedLock::class, 'isConnectionGoneException');

            expect($method->invoke(null, new DbException('Link is busy')))->toBe(false);
        });

        it('does not match unrelated mysqli error codes', function (): void {
            $method = new ReflectionMethod(NamedLock::class, 'isConnectionGoneException');

            $e = new DbException('wrapped', 0, new mysqli_sql_exception('Unknown table', 1146));

            expect($method->invoke(null, $e))->toBe(false);
        });
    });
});
