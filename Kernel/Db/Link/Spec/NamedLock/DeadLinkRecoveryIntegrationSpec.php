<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\Db\DbException;
    use ReflectionProperty;

    describe('NamedLock — восстановление умершего соединения', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbAvailable = NamedLockSpecEnv::probe();
        });

        afterAll(function () use (&$dbAvailable): void {
            NamedLockSpecEnv::cleanup($dbAvailable);
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
    });
}
