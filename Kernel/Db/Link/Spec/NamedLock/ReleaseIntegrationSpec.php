<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\Db\DbException;
    use ReflectionProperty;

    describe('NamedLock — освобождение замка', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbAvailable = NamedLockSpecEnv::probe();
        });

        afterAll(function () use (&$dbAvailable): void {
            NamedLockSpecEnv::cleanup($dbAvailable);
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
    });
}
