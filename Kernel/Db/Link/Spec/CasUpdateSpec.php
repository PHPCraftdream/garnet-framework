<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec {
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use RuntimeException;

    describe('CasUpdate', function (): void {
        describe('isDuplicateKeyError()', function (): void {
            it('returns true for error code 1062', function (): void {
                $e = new DbException('some error', 1062);
                expect(CasUpdate::isDuplicateKeyError($e))->toBe(true);
            });

            it('returns true for message containing "Duplicate entry"', function (): void {
                $e = new RuntimeException("Duplicate entry 'abc' for key 'login'", 0);
                expect(CasUpdate::isDuplicateKeyError($e))->toBe(true);
            });

            it('returns false for unrelated exception', function (): void {
                $e = new RuntimeException('Connection refused', 2002);
                expect(CasUpdate::isDuplicateKeyError($e))->toBe(false);
            });

            it('returns true when code is 1062 even with generic message', function (): void {
                $e = new RuntimeException('unknown error', 1062);
                expect(CasUpdate::isDuplicateKeyError($e))->toBe(true);
            });

            it('returns false for code 1061 (duplicate key name, not entry)', function (): void {
                $e = new DbException('Duplicate key name', 1061);
                expect(CasUpdate::isDuplicateKeyError($e))->toBe(false);
            });
        });
    });
}
