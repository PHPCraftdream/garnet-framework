<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;

/**
 * A multi-row upsert deadlocks with a concurrent one when its VALUES list is not in the order of
 * the UNIQUE index. The expected order below is what MySQL returns for `ORDER BY param` on a
 * `utf8mb4_unicode_ci` column holding these keys.
 */
describe('QueryTools::sortKeysInIndexOrder', function (): void {
    $mysqlOrder = [
        '_x', '1a', 'a', 'a_1', 'a_b', 'A0', 'a1', 'a2', 'aB', 'alpha', 'auth_code', 'auth_code_ut',
        'auth_login', 'auth_login_type', 'AUTH_PHASE', 'auth_tries', 'csrf', 'locale', 'pageSize', 'token',
        'tz', 'x-y', 'x.y', 'Zeta',
    ];

    it('orders the keys like the index of a case-insensitive column', function () use ($mysqlOrder): void {
        $shuffled = array_reverse($mysqlOrder);
        $map = array_combine($shuffled, array_fill(0, count($shuffled), 'v'));

        expect(array_keys(QueryTools::sortKeysInIndexOrder($map)))->toBe($mysqlOrder);
    });

    it('puts auth_login before AUTH_PHASE, which byte order reverses', function (): void {
        $sorted = QueryTools::sortKeysInIndexOrder(['AUTH_PHASE' => 'PHASE_DONE', 'auth_login' => 'a@b.test']);

        expect(array_keys($sorted))->toBe(['auth_login', 'AUTH_PHASE']);
    });

    it('gives two writers with opposite change orders the identical map', function (): void {
        $first = QueryTools::sortKeysInIndexOrder(['csrf' => '1', 'AUTH_PHASE' => '2', 'auth_login' => '3']);
        $second = QueryTools::sortKeysInIndexOrder(['auth_login' => '3', 'AUTH_PHASE' => '2', 'csrf' => '1']);

        expect($first)->toBe($second);
    });

    it('keeps every value attached to its key', function (): void {
        expect(QueryTools::sortKeysInIndexOrder(['b' => 2, 'A' => 1]))->toBe(['A' => 1, 'b' => 2]);
    });

    it('returns nothing for nothing', function (): void {
        expect(QueryTools::sortKeysInIndexOrder([]))->toBe([]);
    });
});
