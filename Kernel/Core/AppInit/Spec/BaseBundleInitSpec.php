<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\AppInit\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionClass;

    class TestableAppInitForBundle extends BaseAppInit {
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

    class MockBundleForBaseBundleInit extends BaseBundleInit {
        public bool $skipInit = false;

        public static function getBundleDir(): string {
            return __DIR__;
        }

        public function initLang(): void {
        }

        public function getLangData(): array {
            return ['key1' => 'value1', 'key2' => 'value2'];
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

    describe('BaseBundleInit', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_bundle_test_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $this->publicDir = $this->tempDir . DIRECTORY_SEPARATOR . 'public';
            $this->appsDir = $this->tempDir . DIRECTORY_SEPARATOR . 'Apps';
            $this->appsFrontDir = $this->tempDir . DIRECTORY_SEPARATOR . 'FrontBuilder';

            mkdir($this->publicDir, 0o777, true);
            mkdir($this->appsDir, 0o777, true);
            mkdir($this->appsFrontDir, 0o777, true);

            TestableAppInitForBundle::$testAppDir = $this->appsDir . DIRECTORY_SEPARATOR . 'TestApp';
            TestableAppInitForBundle::$testFrontDir = $this->appsFrontDir . DIRECTORY_SEPARATOR;

            BaseAppInit::setPublicDirInit($this->publicDir);

            $reflection = new ReflectionClass(BaseBundleInit::class);
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

            $reflection = new ReflectionClass(BaseBundleInit::class);
            $instanceProp = $reflection->getProperty('instance');
            $instanceProp->setValue(null, null);
        });

        describe('constructor', function (): void {
            it('initializes all readonly properties', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect(isset($bundle->namespace))->toBe(true);
                expect(isset($bundle->bundleDir))->toBe(true);
                expect(isset($bundle->bundleAssetsDir))->toBe(true);
                expect(isset($bundle->frontendDir))->toBe(true);
                expect(isset($bundle->frontendTrDir))->toBe(true);
                expect(isset($bundle->bundleAssetsSrc))->toBe(true);
                expect(isset($bundle->twigEnv))->toBe(true);
                expect(isset($bundle->twigTemplatesDir))->toBe(true);
                expect(isset($bundle->twigTemplatesSubspaceDir))->toBe(true);
                expect(isset($bundle->twigCacheDir))->toBe(true);
                expect(isset($bundle->bundleName))->toBe(true);
                expect(isset($bundle->assetsWebPath))->toBe(true);
                expect(isset($bundle->isFrameworkBundle))->toBe(true);
            });

            it('sets bundleName correctly', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect($bundle->bundleName)->toBe('MockBundleForBaseBundleInit');
            });

            it('sets namespace correctly', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect($bundle->namespace)->toContain('Spec');
            });

            it('sets twigEnv to bundleName', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect($bundle->twigEnv)->toBe($bundle->bundleName);
            });

            it('identifies framework bundle correctly', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect($bundle->isFrameworkBundle)->toBe(false);
            });

            it('sets static instance', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect(BaseBundleInit::getInstance())->toBe($bundle);
            });
        });

        describe('getInstance()', function (): void {
            it('throws exception when instance not found', function (): void {
                $reflection = new ReflectionClass(BaseBundleInit::class);
                $instanceProp = $reflection->getProperty('instance');
                $instanceProp->setValue(null, null);

                expect(function (): void {
                    BaseBundleInit::getInstance();
                })->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\BundleException('Bundle instance not found'));
            });

            it('returns the instance when created', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                expect(BaseBundleInit::getInstance())->toBe($bundle);
            });
        });

        describe('getTwig()', function (): void {
            it('returns Twig instance', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                $twig = $bundle->getTwig();

                expect($twig)->toBeAnInstanceOf(\PHPCraftdream\Garnet\Kernel\Io\Twig\Twig::class);
            });
        });

        describe('getLangData()', function (): void {
            it('returns language data array', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                $langData = $bundle->getLangData();

                expect($langData)->toBeAn('array');
                expect(array_key_exists('key1', $langData))->toBe(true);
                expect(array_key_exists('key2', $langData))->toBe(true);
            });
        });

        describe('toArray()', function (): void {
            it('converts bundle to array', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                $result = $bundle->toArray();

                expect($result)->toBeAn('array');
                expect(isset($result['namespace']))->toBe(true);
                expect(isset($result['bundleDir']))->toBe(true);
                expect(isset($result['bundleName']))->toBe(true);
            });

            it('sanitizes array keys', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                $result = $bundle->toArray();

                foreach (array_keys($result) as $key) {
                    expect(preg_match('#[^A-Za-z0-9]#', $key))->toBe(0);
                }
            });
        });

        describe('touchDirs()', function (): void {
            it('creates all required directories', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);

                $bundle->touchDirs();

                expect(is_dir($bundle->bundleDir))->toBe(true);
                expect(is_dir($bundle->frontendDir))->toBe(true);
                expect(is_dir($bundle->twigTemplatesDir))->toBe(true);
                expect(is_dir($bundle->twigTemplatesSubspaceDir))->toBe(true);
                expect(is_dir($bundle->twigCacheDir))->toBe(true);
                expect(is_dir($bundle->bundleAssetsDir))->toBe(true);
                expect(is_dir($bundle->bundleAssetsSrc))->toBe(true);
                expect(is_dir($bundle->frontendTrDir))->toBe(true);
            });

            it('does not fail when directories already exist', function (): void {
                $app = new TestableAppInitForBundle(false);
                $bundle = new MockBundleForBaseBundleInit($app->workDir, $app);
                $bundle->touchDirs();

                expect(function () use ($bundle): void {
                    $bundle->touchDirs();
                })->not->toThrow();
            });
        });
    });
}
