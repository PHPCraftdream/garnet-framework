<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cache\Spec;

use PHPCraftdream\Garnet\Kernel\Io\Cache\FileCache;

describe('FileCache', function (): void {
    describe('constructor', function (): void {
        it('stores file name and TTL', function (): void {
            $cacheBuilder = fn (): string => 'cached data';

            $fileCache = new FileCache('test.txt', 3600, $cacheBuilder);

            expect($fileCache->getFileName())->toBe('test.txt');
            expect($fileCache->getTtlSeconds())->toBe(3600);
        });

        it('stores cache builder callable', function (): void {
            $cacheBuilder = fn (): string => 'cached data';

            $fileCache = new FileCache('test.txt', 3600, $cacheBuilder);

            $result = $fileCache->build();

            expect($result)->toBe('cached data');
        });

        it('allows different TTL values', function (): void {
            $cacheBuilder = fn (): string => 'data';

            $fileCache1 = new FileCache('test1.txt', 60, $cacheBuilder);
            $fileCache2 = new FileCache('test2.txt', 86400, $cacheBuilder);

            expect($fileCache1->getTtlSeconds())->toBe(60);
            expect($fileCache2->getTtlSeconds())->toBe(86400);
        });

        it('supports complex cache builder logic', function (): void {
            $cacheBuilder = function (): string {
                $data = [];

                for ($i = 0; $i < 3; $i++) {
                    $data[] = "item{$i}";
                }

                return json_encode($data);
            };

            $fileCache = new FileCache('complex.json', 300, $cacheBuilder);

            $result = $fileCache->build();

            expect($result)->toContain('item0');
            expect($result)->toContain('item1');
            expect($result)->toContain('item2');
        });

        it('handles file paths with slashes', function (): void {
            $cacheBuilder = fn (): string => 'path data';

            $fileCache = new FileCache('subdir/test/file.txt', 3600, $cacheBuilder);

            expect($fileCache->getFileName())->toBe('subdir/test/file.txt');
        });

        it('supports zero TTL', function (): void {
            $cacheBuilder = fn (): string => 'no cache';

            $fileCache = new FileCache('no_cache.txt', 0, $cacheBuilder);

            expect($fileCache->getTtlSeconds())->toBe(0);
        });
    });

    describe('getFileName()', function (): void {
        it('returns stored file name', function (): void {
            $cacheBuilder = fn (): string => '';

            $fileCache = new FileCache('my_file.txt', 3600, $cacheBuilder);

            expect($fileCache->getFileName())->toBe('my_file.txt');
        });
    });

    describe('getTtlSeconds()', function (): void {
        it('returns stored TTL', function (): void {
            $cacheBuilder = fn (): string => '';

            $fileCache = new FileCache('test.txt', 7200, $cacheBuilder);

            expect($fileCache->getTtlSeconds())->toBe(7200);
        });
    });

    describe('build()', function (): void {
        it('executes cache builder and returns result', function (): void {
            $cacheBuilder = fn (): string => 'built content';

            $fileCache = new FileCache('test.txt', 3600, $cacheBuilder);

            $result = $fileCache->build();

            expect($result)->toBe('built content');
        });

        it('calls builder each time build() is invoked', function (): void {
            $counter = 0;

            $cacheBuilder = function () use (&$counter): string {
                $counter++;

                return "call {$counter}";
            };

            $fileCache = new FileCache('test.txt', 3600, $cacheBuilder);

            $result1 = $fileCache->build();
            $result2 = $fileCache->build();

            expect($result1)->toBe('call 1');
            expect($result2)->toBe('call 2');
            expect($counter)->toBe(2);
        });

        it('supports builders with closures capturing variables', function (): void {
            $prefix = 'PREFIX_';

            $cacheBuilder = fn (): string => $prefix . 'content';

            $fileCache = new FileCache('test.txt', 3600, $cacheBuilder);

            $result = $fileCache->build();

            expect($result)->toBe('PREFIX_content');
        });
    });
});
