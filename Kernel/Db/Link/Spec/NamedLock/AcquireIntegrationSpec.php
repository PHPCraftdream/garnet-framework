<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;

    describe('NamedLock — взятие замка', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbAvailable = NamedLockSpecEnv::probe();
        });

        afterAll(function () use (&$dbAvailable): void {
            NamedLockSpecEnv::cleanup($dbAvailable);
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
    });
}
