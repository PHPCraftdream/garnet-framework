<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionData;

/**
 * The rows of one session flush go to MySQL as a single multi-row upsert whose row locks follow
 * the VALUES order. Requests of one session flush the same params in different orders, so the
 * order has to be fixed or two of them can deadlock (error 1213).
 */
describe('SessionData::buildDataRows', function (): void {
    it('orders the rows by param whatever order the caller changed them in', function (): void {
        $rows = SessionData::buildDataRows(7, ['auth_login' => 'a@b.test', 'AUTH_PHASE' => 'PHASE_DONE', 'csrf' => 'x']);

        expect(array_column($rows, 'param'))->toBe(['AUTH_PHASE', 'auth_login', 'csrf']);
    });

    it('gives two writers with opposite change orders the identical list', function (): void {
        $first = SessionData::buildDataRows(7, ['AUTH_PHASE' => 'PHASE_DONE', 'auth_login' => 'a@b.test']);
        $second = SessionData::buildDataRows(7, ['auth_login' => 'a@b.test', 'AUTH_PHASE' => 'PHASE_DONE']);

        expect($first)->toBe($second);
    });

    it('stamps every row with the session id and keeps the values', function (): void {
        $rows = SessionData::buildDataRows(42, ['b' => '2', 'a' => '1']);

        expect($rows)->toBe([
            ['sessionId' => 42, 'param' => 'a', 'value' => '1'],
            ['sessionId' => 42, 'param' => 'b', 'value' => '2'],
        ]);
    });

    it('returns nothing for nothing', function (): void {
        expect(SessionData::buildDataRows(1, []))->toBe([]);
    });
});
