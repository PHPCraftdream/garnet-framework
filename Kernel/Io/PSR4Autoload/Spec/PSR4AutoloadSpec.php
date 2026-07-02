<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\PSR4Autoload\PSR4Autoload;

describe('PSR4Autoload', function (): void {
    $tempDir = '';

    beforeAll(function () use (&$tempDir): void {
        $tempDir = sys_get_temp_dir();
    });

    describe('paths property', function (): void {
        it('is initialized as empty array', function (): void {
            $loader = new PSR4Autoload();
            expect($loader->paths)->toBe([]);
        });
    });

    describe('makeDirPath()', function () use (&$tempDir): void {
        it('returns path with trailing separator and validates directory exists', function () use ($tempDir): void {
            $loader = new PSR4Autoload();
            $result = $loader->makeDirPath($tempDir);
            expect($result)->toContain($tempDir);
            expect(substr($result, -1) === DIRECTORY_SEPARATOR)->toBe(true);

            expect(function () use ($loader): void {
                $loader->makeDirPath('/nonexistent_' . uniqid());
            })->toThrow();
        });
    });

    describe('setPaths()', function () use (&$tempDir): void {
        it('sets, normalizes paths, and validates they exist', function () use ($tempDir): void {
            $loader = new PSR4Autoload();
            $loader->setPaths([
                'MyNamespace' => $tempDir,
                'OtherNamespace' => $tempDir,
            ]);
            expect(count($loader->paths))->toBe(2);

            $loader = new PSR4Autoload();
            $loader->setPaths(['Test' => $tempDir]);
            expect($loader->paths['Test'])->toContain($tempDir);
            expect(substr($loader->paths['Test'], -1))->toBe(DIRECTORY_SEPARATOR);

            expect(function () use ($loader): void {
                $loader->setPaths(['Test' => '/nonexistent/path']);
            })->toThrow();
        });
    });

    describe('loadClassByNsAndPath()', function (): void {
        it('returns false for non-matching namespace and non-existent files', function (): void {
            $loader = new PSR4Autoload();

            $result = $loader->loadClassByNsAndPath('OtherNamespace\\Class', 'MyNamespace', '/tmp');
            expect($result)->toBe(false);

            $result = $loader->loadClassByNsAndPath('MyNamespace\\NonExistentClass', 'MyNamespace', '/tmp');
            expect($result)->toBe(false);

            $result = $loader->loadClassByNsAndPath('A\\Class', 'A', '/tmp');
            expect($result)->toBe(false);
        });
    });
});
