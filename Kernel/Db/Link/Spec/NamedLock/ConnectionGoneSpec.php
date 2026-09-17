<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use mysqli_sql_exception;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Exceptions\Db\DbException;
    use ReflectionMethod;

    describe('NamedLock — распознавание разорванного соединения', function (): void {
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
}
