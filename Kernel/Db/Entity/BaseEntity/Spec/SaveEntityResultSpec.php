<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveEntityResult;
use PHPCraftdream\Garnet\Kernel\Io\Forms\Updater;

describe('SaveEntityResult', function (): void {
    describe('constructor', function (): void {
        it('creates with update only', function (): void {
            $updater = new Updater(['data' => 'value']);
            $result = new SaveEntityResult($updater);

            expect($result->update)->toBe($updater);
            expect($result->addData)->toBe(null);
        });

        it('creates with update and addData', function (): void {
            $update = new Updater(['key' => 'val']);
            $addData = new Updater(['other' => 'data']);
            $result = new SaveEntityResult($update, $addData);

            expect($result->update)->toBe($update);
            expect($result->addData)->toBe($addData);
        });
    });

    describe('readonly properties', function (): void {
        it('update is readonly', function (): void {
            $updater = new Updater(['test']);
            $result = new SaveEntityResult($updater);
            expect($result->update)->toBe($updater);
        });

        it('addData is readonly and nullable', function (): void {
            $updater = new Updater(['test']);
            $result = new SaveEntityResult($updater);
            expect($result->addData)->toBe(null);
        });
    });
});
