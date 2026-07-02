<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use mysqli;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbMySQLiLink;

describe('DbMySQLiLink', function (): void {
    describe('ID generation', function (): void {
        it('assigns unique IDs', function (): void {
            $link1 = new DbMySQLiLink(new mysqli());
            $link2 = new DbMySQLiLink(new mysqli());

            expect($link1->getId())->not->toBe($link2->getId());
        });

        it('IDs are sequential', function (): void {
            $link1 = new DbMySQLiLink(new mysqli());
            $link2 = new DbMySQLiLink(new mysqli());

            expect($link2->getId())->toBe($link1->getId() + 1);
        });
    });

    describe('State', function (): void {
        it('is not busy initially', function (): void {
            $link = new DbMySQLiLink(new mysqli());

            expect($link->isBusy())->toBe(false);
        });
    });
});
