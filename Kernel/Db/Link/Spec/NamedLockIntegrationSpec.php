<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
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
            $ownersProp->setAccessible(true);
            $owners = $ownersProp->getValue();
            $ownerLinkA = $owners[$lockName];

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
    });
});
