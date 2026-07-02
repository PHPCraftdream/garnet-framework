<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;

describe('ExtPDO', function (): void {
    describe('Constants', function (): void {
        it('has DB_TYPE_MYSQL constant', function (): void {
            expect(ExtPDO::DB_TYPE_MYSQL)->toBe('mysql');
        });
    });
});
