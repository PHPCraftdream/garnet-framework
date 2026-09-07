<?php declare(strict_types=1);

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use PHPCraftdream\Garnet\Kernel\Db\Query\QueryFactory;
use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;

/**
 * A placeholder with no bind value used to become NULL. That is the worst
 * possible default for a guard query: `col < NULL` is never true, so the
 * query returns nothing and the caller concludes there is nothing to guard
 * against. These specs pin the loud behaviour and document the chained-where
 * trap that produces unbound placeholders in the first place.
 */
describe('patchArgsIndexed with an unbound placeholder', function (): void {
    it('refuses a positional placeholder that has no bind value', function (): void {
        $call = function (): void {
            QueryTools::patchArgsIndexed('SELECT * FROM t WHERE a = ? AND b = ?', [1]);
        };

        expect($call)->toThrow(new DbException());
    });

    it('refuses a named placeholder that has no bind value', function (): void {
        $call = function (): void {
            QueryTools::patchArgsIndexed('SELECT * FROM t WHERE a = :a AND b = :b', ['a' => 1]);
        };

        expect($call)->toThrow(new DbException());
    });

    it('still accepts a value that was deliberately bound as null', function (): void {
        [$sql, $args] = QueryTools::patchArgsIndexed('SELECT * FROM t WHERE a = ?', [null]);

        expect($sql)->toBe('SELECT * FROM t WHERE a = ?');
        expect($args)->toBe([null]);
    });

    it('names the placeholder it could not bind', function (): void {
        $message = '';

        try {
            QueryTools::patchArgsIndexed('SELECT * FROM t WHERE a = ? AND b = ?', [1]);
        } catch (DbException $e) {
            $message = $e->getMessage();
        }

        expect($message)->toContain('#1');
    });

    // ################################################################################################################

    /**
     * The trap itself, reproduced through Aura's own factory: three chained
     * positional wheres produce three `?` but only ONE surviving bind value,
     * because each where() call binds its values starting at index 0 and the
     * later call overwrites the earlier one. Garnet's factory is what stops
     * this — see the specs below.
     */
    it('catches a chain of positional wheres collapsing its bind values', function (): void {
        $select = (new AuraQueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('time_slots')
            ->where('expert_id = ?', [7])
            ->where('start_at < ?', [200])
            ->where('end_at > ?', [100]);

        // Three placeholders survive in the SQL...
        expect(substr_count($select->getStatement(), '?'))->toBe(3);
        // ...but the binds collapsed onto a single index — the last one wins.
        expect($select->getBindValues())->toBe([100]);

        $call = function () use ($select): void {
            QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());
        };

        expect($call)->toThrow(new DbException());
    });

    it('accepts the named-placeholder form the chain should have used', function (): void {
        $select = (new AuraQueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('time_slots')
            ->where(
                'expert_id = :expert_id AND start_at < :end_at AND end_at > :start_at',
                ['expert_id' => 7, 'end_at' => 200, 'start_at' => 100]
            );

        [, $args] = QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());

        expect($args)->toBe([7, 200, 100]);
    });
});

// ####################################################################################################################

describe("Garnet's query factory keeping chained positional binds", function (): void {
    it('keeps every value of a chained select where, in order', function (): void {
        $select = (new QueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('time_slots')
            ->where('expert_id = ?', [7])
            ->where("status != 'cancelled'")
            ->where('start_at < ?', [200])
            ->where('end_at > ?', [100]);

        [$sql, $args] = QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());

        expect(substr_count($sql, '?'))->toBe(3);
        expect($args)->toBe([7, 200, 100]);
    });

    it('keeps array binds expanding into an IN list alongside scalar ones', function (): void {
        $select = (new QueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('bookings')
            ->where('user_id = ?', [42])
            ->where('bookable_id IN (?)', [[1, 2, 3]])
            ->where('status = ?', ['confirmed']);

        [, $args] = QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());

        expect($args)->toBe([42, 1, 2, 3, 'confirmed']);
    });

    it('leaves a condition that already uses named placeholders alone', function (): void {
        $select = (new QueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('t')
            ->where('a = :a', ['a' => 1])
            ->where('b = :b', ['b' => 2]);

        [, $args] = QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());

        expect($args)->toBe([1, 2]);
    });

    it('keeps every value of a chained update where — the shape that would widen a write', function (): void {
        $update = (new QueryFactory('mysql'))->newUpdate();
        $update->table('bookings')->cols(['status' => 'cancelled'])
            ->where('user_id = ?', [42])
            ->where('slot_id = ?', [7]);

        [, $args] = QueryTools::patchArgsIndexed($update->getStatement(), $update->getBindValues());

        expect($args)->toBe(['cancelled', 42, 7]);
    });

    it('keeps every value of a chained delete where', function (): void {
        $delete = (new QueryFactory('mysql'))->newDelete();
        $delete->from('bookings')
            ->where('user_id = ?', [42])
            ->where('slot_id = ?', [7]);

        [, $args] = QueryTools::patchArgsIndexed($delete->getStatement(), $delete->getBindValues());

        expect($args)->toBe([42, 7]);
    });

    it('still reports a placeholder that was given no value at all', function (): void {
        $select = (new QueryFactory('mysql'))->newSelect();
        $select->cols(['*'])->from('t')->where('a = ? AND b = ?', [1]);

        $call = function () use ($select): void {
            QueryTools::patchArgsIndexed($select->getStatement(), $select->getBindValues());
        };

        expect($call)->toThrow(new DbException());
    });
});
