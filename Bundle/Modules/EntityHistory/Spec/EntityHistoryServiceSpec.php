<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Spec;

use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\EntityHistoryService;

describe('EntityHistoryService::diff()', function (): void {
    it('returns empty diff for identical rows', function (): void {
        $a = ['id' => 1, 'title' => 'Foo', 'count' => 3];
        $b = ['id' => 1, 'title' => 'Foo', 'count' => 3];
        expect(EntityHistoryService::diff($a, $b))->toBe([]);
    });

    it('captures changed fields with old/new', function (): void {
        $a = ['title' => 'Foo', 'count' => 3];
        $b = ['title' => 'Bar', 'count' => 3];
        $diff = EntityHistoryService::diff($a, $b);
        expect($diff)->toBe([
            'title' => ['old' => 'Foo', 'new' => 'Bar'],
        ]);
    });

    it('captures fields added in new row', function (): void {
        $a = ['title' => 'Foo'];
        $b = ['title' => 'Foo', 'description' => 'New'];
        $diff = EntityHistoryService::diff($a, $b);
        expect($diff)->toBe([
            'description' => ['old' => null, 'new' => 'New'],
        ]);
    });

    it('captures fields removed in new row', function (): void {
        $a = ['title' => 'Foo', 'tag' => 'x'];
        $b = ['title' => 'Foo'];
        $diff = EntityHistoryService::diff($a, $b);
        expect($diff)->toBe([
            'tag' => ['old' => 'x', 'new' => null],
        ]);
    });

    it('treats numeric strings and ints as equal (DB-friendly comparison)', function (): void {
        // DB rows often come back as strings even for integer columns;
        // the diff helper must not flag spurious changes on a no-op save.
        $a = ['count' => '3'];
        $b = ['count' => 3];
        expect(EntityHistoryService::diff($a, $b))->toBe([]);
    });

    it('skips fields listed in $ignoredFields', function (): void {
        $a = ['title' => 'Foo', 'updated_at' => 100];
        $b = ['title' => 'Bar', 'updated_at' => 200];
        $diff = EntityHistoryService::diff($a, $b, ['updated_at']);
        expect($diff)->toBe([
            'title' => ['old' => 'Foo', 'new' => 'Bar'],
        ]);
    });

    it('captures multiple field changes in a single diff', function (): void {
        $a = ['title' => 'A', 'body' => 'X', 'count' => 1];
        $b = ['title' => 'B', 'body' => 'X', 'count' => 2];
        $diff = EntityHistoryService::diff($a, $b);
        expect(array_key_exists('title', $diff))->toBe(true);
        expect(array_key_exists('count', $diff))->toBe(true);
        expect(array_key_exists('body', $diff))->toBe(false);
    });
});
