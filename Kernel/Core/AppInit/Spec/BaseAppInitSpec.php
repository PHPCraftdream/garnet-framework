<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\AppInit\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionClass;

    class TestableAppInit extends BaseAppInit {
        public static string $testAppDir;

        public static string $testFrontDir;

        public function getAppDir(): string {
            return self::$testAppDir;
        }

        public function getFrontDir(): string {
            return self::$testFrontDir;
        }

        protected function defineBundles(): void {
        }

        protected function defineMigrationClass(): void {
        }

        protected function defineTwigParams(): void {
        }
    }

    class MockBundleForAppInit extends BaseBundleInit {
        public bool $skipInit = false;

        public static function getBundleDir(): string {
            return __DIR__;
        }

        public function initLang(): void {
        }

        public function getLangData(): array {
            return [];
        }

        protected function init(): void {
            if (!$this->skipInit) {
                $twigDir = $this->twigTemplatesDir;

                if (!is_dir($twigDir)) {
                    mkdir($twigDir, 0o777, true);
                }
                parent::init();
            }
        }

        protected function afterConstruct(): void {
            if (!$this->skipInit) {
                parent::afterConstruct();
            }
        }
    }

    describe('BaseAppInit', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_app_test_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $this->publicDir = $this->tempDir . DIRECTORY_SEPARATOR . 'public';
            $this->appsDir = $this->tempDir . DIRECTORY_SEPARATOR . 'Apps';
            $this->appsFrontDir = $this->tempDir . DIRECTORY_SEPARATOR . 'FrontBuilder';

            mkdir($this->publicDir, 0o777, true);
            mkdir($this->appsDir, 0o777, true);
            mkdir($this->appsFrontDir, 0o777, true);

            TestableAppInit::$testAppDir = $this->appsDir . DIRECTORY_SEPARATOR . 'TestApp';
            TestableAppInit::$testFrontDir = $this->appsFrontDir . DIRECTORY_SEPARATOR;

            BaseAppInit::setPublicDirInit($this->publicDir);

            $reflection = new ReflectionClass(BaseAppInit::class);
            $instanceProp = $reflection->getProperty('instance');
            $instanceProp->setValue(null, null);
        });

        afterEach(function (): void {
            if (is_dir($this->tempDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) {
                        rmdir($fileinfo->getPathname());
                    } else {
                        unlink($fileinfo->getPathname());
                    }
                }
                rmdir($this->tempDir);
            }

            $reflection = new ReflectionClass(BaseAppInit::class);
            $instanceProp = $reflection->getProperty('instance');
            $instanceProp->setValue(null, null);
        });

        describe('constructor', function (): void {
            it('initializes all readonly properties', function (): void {
                $app = new TestableAppInit(false);

                expect(isset($app->namespace))->toBe(true);
                expect(isset($app->appDir))->toBe(true);
                expect(isset($app->appDirName))->toBe(true);
                expect(isset($app->assetsDirName))->toBe(true);
                expect(isset($app->publicDir))->toBe(true);
                expect(isset($app->assetsDir))->toBe(true);
                expect(isset($app->publicUploadDir))->toBe(true);
                expect(isset($app->assetsDirFw))->toBe(true);
                expect(isset($app->assetsDirFwJs))->toBe(true);
                expect(isset($app->assetsDirFwCss))->toBe(true);
                expect(isset($app->assetsWebPath))->toBe(true);
                expect(isset($app->publicUploadWebPath))->toBe(true);
                expect(isset($app->assetsGenDir))->toBe(true);
                expect(isset($app->assetsGenCssDir))->toBe(true);
                expect(isset($app->assetsGenJsDir))->toBe(true);
                expect(isset($app->workDir))->toBe(true);
                expect(isset($app->configProdDir))->toBe(true);
                expect(isset($app->configDevDir))->toBe(true);
                expect(isset($app->fileCacheDir))->toBe(true);
                expect(isset($app->logErrorDir))->toBe(true);
                expect(isset($app->logSystemDir))->toBe(true);
                expect(isset($app->uploadDir))->toBe(true);
                expect(isset($app->twigCacheDir))->toBe(true);
                expect(isset($app->nodeModules))->toBe(true);
                expect(isset($app->isDev))->toBe(true);
            });

            it('stores isDev value', function (): void {
                $app = new TestableAppInit(true);
                expect($app->isDev)->toBe(true);

                $app2 = new TestableAppInit(false);
                expect($app2->isDev)->toBe(false);
            });

            it('sets static instance', function (): void {
                $app = new TestableAppInit(false);

                expect(BaseAppInit::getInstance())->toBe($app);
            });

            it('sets namespace correctly', function (): void {
                $app = new TestableAppInit(false);

                expect($app->namespace)->toContain('Spec');
            });

            it('sets appDirName correctly', function (): void {
                $app = new TestableAppInit(false);

                expect($app->appDirName)->toBe('TestApp');
            });

            it('sets assetsDirName correctly', function (): void {
                $app = new TestableAppInit(false);

                expect($app->assetsDirName)->toBe('TestApp');
            });

            it('sets web paths correctly', function (): void {
                $app = new TestableAppInit(false);

                expect($app->assetsWebPath)->toBe('/assets/TestApp/');
                expect($app->publicUploadWebPath)->toBe('/upload/TestApp/');
            });

            it('sets work directory paths correctly', function (): void {
                $app = new TestableAppInit(false);

                expect($app->workDir)->toContain('WorkDir');
                expect($app->configProdDir)->toContain('Config');
                expect($app->configDevDir)->toContain('ConfigDev');
                expect($app->fileCacheDir)->toContain('FileCache');
                expect($app->logErrorDir)->toContain('LogJournal');
                expect($app->logErrorDir)->toContain('Errors');
                expect($app->logSystemDir)->toContain('System');
                expect($app->uploadDir)->toContain('Upload');
                expect($app->twigCacheDir)->toContain('TwigCache');
            });

            it('uses GARNET_WORKDIR_DIR env override when set', function (): void {
                putenv('GARNET_WORKDIR_DIR=/tmp/wd-test');

                try {
                    $app = new TestableAppInit(false);

                    $ds = DIRECTORY_SEPARATOR;
                    expect($app->workDir)->toBe('/tmp/wd-test' . $ds);
                    expect($app->logErrorDir)->toBe('/tmp/wd-test' . $ds . 'LogJournal' . $ds . 'Errors' . $ds);
                } finally {
                    putenv('GARNET_WORKDIR_DIR');
                }
            });
        });

        describe('getPublicDir()', function (): void {
            it('returns public dir when set', function (): void {
                $app = new TestableAppInit(false);

                expect($app->getPublicDir())->toBe($this->publicDir);
            });

            // PENDING: getPublicDir() only throws when run from a web SAPI. Under
            // CLI (kahlan) it falls back to sys_get_temp_dir() to keep CLI ops
            // like migrations/db:wipe working without a public/ dir — see
            // BaseAppInit::getPublicDir() comment. Needs a SAPI mock to assert.
            xit('throws exception when publicDirInit is not set', function (): void {
                $reflection = new ReflectionClass(BaseAppInit::class);
                $publicDirInitProp = $reflection->getProperty('publicDirInit');
                $publicDirInitProp->setValue(null, null);

                expect(function (): void {
                    $app = new TestableAppInit(false);
                    $app->getPublicDir();
                })->toThrow(new CommonException('$publicDirInit === null and GARNET_PUBLIC_DIR not set'));
            });

            it('throws exception when publicDirInit is not a directory', function (): void {
                BaseAppInit::setPublicDirInit($this->tempDir . '/nonexistent');

                expect(function (): void {
                    new TestableAppInit(false);
                })->toThrow();
            });
        });

        describe('setPublicDirInit()', function (): void {
            it('sets static publicDirInit', function (): void {
                $newDir = $this->tempDir . '/public2';
                mkdir($newDir, 0o777, true);

                BaseAppInit::setPublicDirInit($newDir);

                $app = new TestableAppInit(false);

                expect($app->getPublicDir())->toBe($newDir);
            });
        });

        describe('getInstance()', function (): void {
            it('returns null when no instance created', function (): void {
                $reflection = new ReflectionClass(BaseAppInit::class);
                $instanceProp = $reflection->getProperty('instance');
                $instanceProp->setValue(null, null);

                expect(BaseAppInit::getInstance())->toBe(null);
            });

            it('returns the instance when created', function (): void {
                $app = new TestableAppInit(false);

                expect(BaseAppInit::getInstance())->toBe($app);
            });
        });

        describe('getLang()', function (): void {
            it('returns default language', function (): void {
                $app = new TestableAppInit(false);

                expect($app->getLang())->toBe('RU');
            });
        });

        describe('addBundle()', function (): void {
            it('adds bundle to bundles array', function (): void {
                $app = new TestableAppInit(false);
                $bundle = new MockBundleForAppInit($app->workDir, $app);
                $bundle->skipInit = true;

                $app->addBundle($bundle);

                $reflection = new ReflectionClass($app);
                $bundlesProp = $reflection->getProperty('bundles');
                $bundles = $bundlesProp->getValue($app);

                expect(array_key_exists($bundle->bundleName, $bundles))->toBe(true);
                expect($bundles[$bundle->bundleName])->toBe($bundle);
            });
        });

        describe('toArray()', function (): void {
            it('converts app to array', function (): void {
                $app = new TestableAppInit(false);

                $result = $app->toArray();

                expect($result)->toBeAn('array');
                expect(isset($result['namespace']))->toBe(true);
                expect(isset($result['appDir']))->toBe(true);
                expect(isset($result['isDev']))->toBe(true);
            });

            it('includes bundles in array', function (): void {
                $app = new TestableAppInit(false);
                $bundle = new MockBundleForAppInit($app->workDir, $app);
                $bundle->skipInit = true;
                $app->addBundle($bundle);

                $result = $app->toArray();

                expect(isset($result['bundles']))->toBe(true);
                expect(is_array($result['bundles']))->toBe(true);
                expect(count($result['bundles']))->toBe(1);
            });

            it('sanitizes array keys', function (): void {
                $app = new TestableAppInit(false);

                $result = $app->toArray();

                foreach (array_keys($result) as $key) {
                    expect(preg_match('#[^A-Za-z0-9]#', $key))->toBe(0);
                }
            });
        });

        describe('touchDirs()', function (): void {
            it('creates all required directories', function (): void {
                $app = new TestableAppInit(false);

                $app->touchDirs();

                expect(is_dir($app->publicDir))->toBe(true);
                expect(is_dir($app->workDir))->toBe(true);
                expect(is_dir($app->assetsDir))->toBe(true);
                expect(is_dir($app->publicUploadDir))->toBe(true);
                expect(is_dir($app->assetsGenDir))->toBe(true);
                expect(is_dir($app->configProdDir))->toBe(true);
                expect(is_dir($app->configDevDir))->toBe(true);
                expect(is_dir($app->fileCacheDir))->toBe(true);
                expect(is_dir($app->logErrorDir))->toBe(true);
                expect(is_dir($app->logSystemDir))->toBe(true);
                expect(is_dir($app->uploadDir))->toBe(true);
                expect(is_dir($app->twigCacheDir))->toBe(true);
                expect(is_dir($app->nodeModules))->toBe(true);
                expect(is_dir($app->assetsGenJsDir))->toBe(true);
                expect(is_dir($app->assetsGenCssDir))->toBe(true);
                expect(is_dir($app->assetsDirFw))->toBe(true);
                expect(is_dir($app->assetsDirFwJs))->toBe(true);
                expect(is_dir($app->assetsDirFwCss))->toBe(true);
            });

            it('does not fail when directories already exist', function (): void {
                $app = new TestableAppInit(false);
                $app->touchDirs();

                expect(function () use ($app): void {
                    $app->touchDirs();
                })->not->toThrow();
            });
        });
    });
}
