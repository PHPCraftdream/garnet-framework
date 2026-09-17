<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\Db\DbException;
    use ReflectionProperty;

    describe('NamedLock — согласие с DbPool::closeAll()', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbAvailable = NamedLockSpecEnv::probe();
        });

        afterAll(function () use (&$dbAvailable): void {
            NamedLockSpecEnv::cleanup($dbAvailable);
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
    });
}
