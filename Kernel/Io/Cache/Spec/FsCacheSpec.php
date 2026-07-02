<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cache\Spec {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CacheException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICache;
    use PHPCraftdream\Garnet\Kernel\Io\Cache\FileCache;
    use PHPCraftdream\Garnet\Kernel\Io\Cache\FsCache;
    use ReflectionClass;

    describe('FsCache', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_fscache_test_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $reflection = new ReflectionClass(FsCache::class);
            $cacheInfoProp = $reflection->getProperty('cacheInfo');
            $cacheInfoProp->setAccessible(true);
            $cacheInfoProp->setValue(null, []);

            $fileInfoProp = $reflection->getProperty('fileInfo');
            $fileInfoProp->setAccessible(true);
            $fileInfoProp->setValue(null, []);
        });

        afterEach(function (): void {
            if (is_dir($this->tempDir)) {
                $files = glob($this->tempDir . '/*');

                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                rmdir($this->tempDir);
            }

            $reflection = new ReflectionClass(FsCache::class);
            $cacheInfoProp = $reflection->getProperty('cacheInfo');
            $cacheInfoProp->setAccessible(true);
            $cacheInfoProp->setValue(null, []);

            $fileInfoProp = $reflection->getProperty('fileInfo');
            $fileInfoProp->setAccessible(true);
            $fileInfoProp->setValue(null, []);
        });

        describe('defineCache()', function (): void {
            it('defines a cache with valid directory', function (): void {
                $cache = FsCache::defineCache($this->tempDir, 'test_cache');

                expect($cache)->toBeAnInstanceOf(ICache::class);
            });

            it('defines a cache with default ENV_APP name', function (): void {
                $cache = FsCache::defineCache($this->tempDir);

                expect($cache)->toBeAnInstanceOf(ICache::class);
            });

            it('throws exception for duplicate cache name', function (): void {
                FsCache::defineCache($this->tempDir, 'duplicate');

                expect(function (): void {
                    FsCache::defineCache($this->tempDir, 'duplicate');
                })->toThrow(new CacheException('Cache already defined: duplicate'));
            });

            it('throws exception for non-existent directory', function (): void {
                $nonExistentDir = $this->tempDir . '/nonexistent';

                expect(function () use ($nonExistentDir): void {
                    FsCache::defineCache($nonExistentDir);
                })->toThrow(new CacheException('Dir not found:' . $nonExistentDir));
            });
        });

        describe('getCache()', function (): void {
            it('returns defined cache', function (): void {
                $definedCache = FsCache::defineCache($this->tempDir, 'get_test');
                $retrievedCache = FsCache::getCache('get_test');

                expect($retrievedCache)->toBe($definedCache);
            });

            it('returns cache with default ENV_APP name', function (): void {
                $definedCache = FsCache::defineCache($this->tempDir);
                $retrievedCache = FsCache::getCache();

                expect($retrievedCache)->toBe($definedCache);
            });

            it('throws exception for undefined cache', function (): void {
                expect(function (): void {
                    FsCache::getCache('undefined');
                })->toThrow(new CacheException('Cache not found: undefined'));
            });
        });

        describe('defineFile()', function (): void {
            it('defines a file with builder and TTL', function (): void {
                $builderCalled = false;
                $builder = function () use (&$builderCalled): string {
                    $builderCalled = true;

                    return 'test content';
                };

                FsCache::defineFile('test.js', 3600, $builder);

                $reflection = new ReflectionClass(FsCache::class);
                $fileInfoProp = $reflection->getProperty('fileInfo');
                $fileInfoProp->setAccessible(true);
                $fileInfo = $fileInfoProp->getValue(null);

                expect($fileInfo['test.js'])->toBeAnInstanceOf(FileCache::class);
            });

            it('trims slashes from file name', function (): void {
                $builder = fn (): string => 'content';

                FsCache::defineFile('/test/test.js', 3600, $builder);

                $reflection = new ReflectionClass(FsCache::class);
                $fileInfoProp = $reflection->getProperty('fileInfo');
                $fileInfoProp->setAccessible(true);
                $fileInfo = $fileInfoProp->getValue(null);

                expect(isset($fileInfo['test/test.js']))->toBe(true);
            });

            it('throws exception for duplicate file name', function (): void {
                $builder = fn (): string => 'content';

                FsCache::defineFile('dup.js', 3600, $builder);

                expect(function () use ($builder): void {
                    FsCache::defineFile('dup.js', 7200, $builder);
                })->toThrow(new CacheException('File already defined: dup.js'));
            });
        });

        describe('getActualFile()', function (): void {
            beforeEach(function (): void {
                $this->cache = FsCache::defineCache($this->tempDir);
                $this->testFileName = 'actual_test_' . uniqid() . '.txt';
                $this->testContent = 'test content ' . uniqid();
            });

            it('creates file when missing and returns content', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 3600, $builder);
                $result = $this->cache->getActualFile($this->testFileName);

                expect($result)->toBe($this->testContent);
                expect(is_file($this->tempDir . '/' . $this->testFileName))->toBe(true);
            });

            it('returns cached content within TTL', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 3600, $builder);
                $this->cache->getActualFile($this->testFileName);

                $result = $this->cache->getActualFile($this->testFileName);

                expect($result)->toBe($this->testContent);
            });

            it('rebuilds expired file', function (): void {
                $content = $this->testContent;
                $modifiedContent = $this->testContent . ' expired';

                FsCache::defineFile($this->testFileName, 1, fn (): string => $content);
                $this->cache->getActualFile($this->testFileName);
                sleep(2);

                $reflection = new ReflectionClass(FsCache::class);
                $fileInfoProp = $reflection->getProperty('fileInfo');
                $fileInfoProp->setAccessible(true);
                $fileInfo = $fileInfoProp->getValue(null);
                $fileCache = $fileInfo[$this->testFileName];

                $fileCacheReflection = new ReflectionClass(FileCache::class);
                $builderProp = $fileCacheReflection->getProperty('cacheBuilder');
                $builderProp->setAccessible(true);
                $builderProp->setValue($fileCache, fn (): string => $modifiedContent);

                $result = $this->cache->getActualFile($this->testFileName);

                expect($result)->toBe($modifiedContent);
            });

            it('throws exception for undefined file', function (): void {
                expect(function (): void {
                    $this->cache->getActualFile('undefined.txt');
                })->toThrow(new CacheException('File not defined: undefined.txt'));
            });
        });

        describe('getExistsFile()', function (): void {
            beforeEach(function (): void {
                $this->cache = FsCache::defineCache($this->tempDir);
                $this->testFileName = 'exists_test_' . uniqid() . '.txt';
                $this->testContent = 'exists content ' . uniqid();
            });

            it('creates file when missing and returns content', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 3600, $builder);
                $result = $this->cache->getExistsFile($this->testFileName);

                expect($result)->toBe($this->testContent);
            });

            it('returns existing file content', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 3600, $builder);
                $this->cache->getExistsFile($this->testFileName);

                $result = $this->cache->getExistsFile($this->testFileName);
                expect($result)->toBe($this->testContent);
            });

            it('returns existing file even after TTL expires', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 1, $builder);
                $this->cache->getExistsFile($this->testFileName);
                sleep(2);

                $result = $this->cache->getExistsFile($this->testFileName);
                expect($result)->toBe($this->testContent);
            });

            it('throws exception for undefined file', function (): void {
                expect(function (): void {
                    $this->cache->getExistsFile('undefined.txt');
                })->toThrow(new CacheException('File not defined: undefined.txt'));
            });
        });

        describe('refreshFile()', function (): void {
            beforeEach(function (): void {
                $this->cache = FsCache::defineCache($this->tempDir);
                $this->testFileName = 'refresh_test_' . uniqid() . '.txt';
                $this->testContent = 'refresh content ' . uniqid();
            });

            it('creates file when missing', function (): void {
                $content = $this->testContent;
                $builder = fn (): string => $content;

                FsCache::defineFile($this->testFileName, 3600, $builder);
                $this->cache->refreshFile($this->testFileName);

                expect(is_file($this->tempDir . '/' . $this->testFileName))->toBe(true);
            });

            it('rebuilds expired file', function (): void {
                $content = $this->testContent;
                $modifiedContent = $this->testContent . ' refreshed';

                FsCache::defineFile($this->testFileName, 1, fn (): string => $content);
                $this->cache->refreshFile($this->testFileName);
                sleep(2);

                $reflection = new ReflectionClass(FsCache::class);
                $fileInfoProp = $reflection->getProperty('fileInfo');
                $fileInfoProp->setAccessible(true);
                $fileInfo = $fileInfoProp->getValue(null);
                $fileCache = $fileInfo[$this->testFileName];

                $fileCacheReflection = new ReflectionClass(FileCache::class);
                $builderProp = $fileCacheReflection->getProperty('cacheBuilder');
                $builderProp->setAccessible(true);
                $builderProp->setValue($fileCache, fn (): string => $modifiedContent);

                $this->cache->refreshFile($this->testFileName);

                $content = file_get_contents($this->tempDir . '/' . $this->testFileName);
                expect($content)->toBe($modifiedContent);
            });

            it('keeps file within TTL', function (): void {
                $content = $this->testContent;
                $modifiedContent = $this->testContent . ' modified';

                FsCache::defineFile($this->testFileName, 3600, fn (): string => $content);
                $this->cache->refreshFile($this->testFileName);

                $reflection = new ReflectionClass(FsCache::class);
                $fileInfoProp = $reflection->getProperty('fileInfo');
                $fileInfoProp->setAccessible(true);
                $fileInfo = $fileInfoProp->getValue(null);
                $fileCache = $fileInfo[$this->testFileName];

                $fileCacheReflection = new ReflectionClass(FileCache::class);
                $builderProp = $fileCacheReflection->getProperty('cacheBuilder');
                $builderProp->setAccessible(true);
                $builderProp->setValue($fileCache, fn (): string => $modifiedContent);

                $this->cache->refreshFile($this->testFileName);

                $content = file_get_contents($this->tempDir . '/' . $this->testFileName);
                expect($content)->toBe($this->testContent);
            });

            it('throws exception for undefined file', function (): void {
                expect(function (): void {
                    $this->cache->refreshFile('undefined.txt');
                })->toThrow(new CacheException('File not defined: undefined.txt'));
            });
        });
    });
}
