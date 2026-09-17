<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use ReflectionProperty;

    describe('NamedLock — общее соединение под замком', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbAvailable = NamedLockSpecEnv::probe();
        });

        afterAll(function () use (&$dbAvailable): void {
            NamedLockSpecEnv::cleanup($dbAvailable);
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
                $ownersProp->setValue(null, $owners);

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
    });
}
