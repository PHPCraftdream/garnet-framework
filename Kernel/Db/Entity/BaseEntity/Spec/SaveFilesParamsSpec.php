<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveFilesParams;

describe('SaveFilesParams', function (): void {
    describe('constructor', function (): void {
        it('creates with files and baseDir', function (): void {
            $params = new SaveFilesParams(['file1'], '/base/dir');
            expect($params->files)->toBe(['file1']);
            expect($params->baseDir)->toBe('/base/dir');
        });

        it('creates with files, baseDir and prevData', function (): void {
            $params = new SaveFilesParams(['file1'], '/base/dir', ['old' => 'data']);
            expect($params->files)->toBe(['file1']);
            expect($params->baseDir)->toBe('/base/dir');
            expect($params->prevData)->toBe(['old' => 'data']);
        });

        it('defaults prevData to empty array', function (): void {
            $params = new SaveFilesParams(['file1'], '/base/dir');
            expect($params->prevData)->toBe([]);
        });
    });

    describe('make()', function (): void {
        it('creates instance with provided parameters', function (): void {
            $params = SaveFilesParams::make(['file1', 'file2'], '/base', ['key' => 'value']);

            expect($params->files)->toBe(['file1', 'file2']);
            expect($params->baseDir)->toBe('/base');
            expect($params->prevData)->toBe(['key' => 'value']);
        });

        it('creates instance with defaults', function (): void {
            $params = SaveFilesParams::make([], '/dir');

            expect($params->files)->toBe([]);
            expect($params->baseDir)->toBe('/dir');
            expect($params->prevData)->toBe([]);
        });
    });
});
