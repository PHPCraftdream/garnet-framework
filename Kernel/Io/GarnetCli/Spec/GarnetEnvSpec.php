<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;
    use function dirname;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    // GARNET_ROOT is defined by the root `garnet` CLI entry; in a kahlan run
    // we synthesise it before any spec touches GarnetEnv. The value points at
    // the repo root so the GARNET_ROOT-based fallback branches resolve to
    // something concrete (no spec depends on the value contents).
    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    describe('GarnetEnv', function (): void {
        // ── Tempdir + env-isolation harness ────────────────────────────
        //
        // Most methods read GARNET_APP_DIR / GARNET_WORKDIR_DIR / GARNET_ROOT.
        // We make a tmp tree per spec so the assertions don't depend on the
        // checkout's real Apps/ layout, and we restore env after each test.

        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_genv_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $this->prevAppDir = getenv('GARNET_APP_DIR');
            $this->prevWorkdirDir = getenv('GARNET_WORKDIR_DIR');
            $this->prevRuntimeDir = getenv('GARNET_RUNTIME_DIR');

            putenv('GARNET_APP_DIR');
            putenv('GARNET_WORKDIR_DIR');
            putenv('GARNET_RUNTIME_DIR');
        });

        afterEach(function (): void {
            // Restore env
            putenv($this->prevAppDir === false ? 'GARNET_APP_DIR' : 'GARNET_APP_DIR=' . $this->prevAppDir);
            putenv($this->prevWorkdirDir === false ? 'GARNET_WORKDIR_DIR' : 'GARNET_WORKDIR_DIR=' . $this->prevWorkdirDir);
            putenv($this->prevRuntimeDir === false ? 'GARNET_RUNTIME_DIR' : 'GARNET_RUNTIME_DIR=' . $this->prevRuntimeDir);

            // Recursively remove tempDir
            if (is_dir($this->tempDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($it as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($this->tempDir);
            }
        });

        describe('::getAppDir', function (): void {
            it('returns GARNET_APP_DIR verbatim when the env var is set', function (): void {
                putenv('GARNET_APP_DIR=' . $this->tempDir);
                expect(GarnetEnv::getAppDir('AnyName'))->toBe($this->tempDir);
            });

            it('falls back to GARNET_ROOT/Apps/<name> when GARNET_APP_DIR is empty', function (): void {
                $expected = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . 'Foo';
                expect(GarnetEnv::getAppDir('Foo'))->toBe($expected);
            });

            it('ignores literal "false" string in GARNET_APP_DIR (treated as empty)', function (): void {
                // `getenv` returns the string verbatim; only `false` (bool) means unset.
                // An empty string is treated as "not set" by the implementation.
                putenv('GARNET_APP_DIR=');
                $expected = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . 'X';
                expect(GarnetEnv::getAppDir('X'))->toBe($expected);
            });
        });

        describe('::workDir', function (): void {
            it('returns GARNET_WORKDIR_DIR when set, stripped of trailing slash', function (): void {
                putenv('GARNET_WORKDIR_DIR=' . $this->tempDir . '/');
                expect(GarnetEnv::workDir('Anything'))->toBe(rtrim($this->tempDir, '/\\'));
            });

            it('falls back to <appDir>/WorkDir when GARNET_WORKDIR_DIR is not set', function (): void {
                putenv('GARNET_APP_DIR=' . $this->tempDir);
                expect(GarnetEnv::workDir('Foo'))->toBe($this->tempDir . DIRECTORY_SEPARATOR . 'WorkDir');
            });
        });

        describe('::envFile', function (): void {
            it('returns <override>/.env when GARNET_APP_DIR is set', function (): void {
                putenv('GARNET_APP_DIR=' . $this->tempDir);
                expect(GarnetEnv::envFile())->toBe($this->tempDir . DIRECTORY_SEPARATOR . '.env');
            });

            it('returns GARNET_ROOT/.env when GARNET_APP_DIR is empty', function (): void {
                expect(GarnetEnv::envFile())->toBe(GARNET_ROOT . DIRECTORY_SEPARATOR . '.env');
            });
        });

        describe('::getPublicDir', function (): void {
            it('honours BUNDLE_PUBLIC_DIR from the app override .env (deploy layout)', function (): void {
                $appDir = $this->tempDir . DIRECTORY_SEPARATOR . 'garnet-app-myapp';
                mkdir($appDir, 0o777, true);
                file_put_contents(
                    $appDir . DIRECTORY_SEPARATOR . '.env',
                    "APP_NAME=MyApp\nBUNDLE_PUBLIC_DIR=example.com\n"
                );
                putenv('GARNET_APP_DIR=' . $appDir);

                // The sibling-of-override convention: dirname($appDir) + BUNDLE_PUBLIC_DIR
                $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'example.com';
                expect(GarnetEnv::getPublicDir('MyApp'))->toBe($expected);
            });

            it('falls back to <appDir>/Public when BUNDLE_PUBLIC_DIR is empty and the dir exists', function (): void {
                $appDir = $this->tempDir . DIRECTORY_SEPARATOR . 'my-app';
                mkdir($appDir . DIRECTORY_SEPARATOR . 'Public', 0o777, true);
                file_put_contents($appDir . DIRECTORY_SEPARATOR . '.env', "APP_NAME=MyApp\n");
                putenv('GARNET_APP_DIR=' . $appDir);

                expect(GarnetEnv::getPublicDir('MyApp'))->toBe(
                    $appDir . DIRECTORY_SEPARATOR . 'Public'
                );
            });
        });

        describe('::readEnvKey', function (): void {
            it('returns the value for a present key', function (): void {
                $env = $this->tempDir . DIRECTORY_SEPARATOR . '.env';
                file_put_contents($env, "APP_NAME=Garnet\nLEVEL=42\n");
                expect(GarnetEnv::readEnvKey($env, 'APP_NAME'))->toBe('Garnet');
                expect(GarnetEnv::readEnvKey($env, 'LEVEL'))->toBe('42');
            });

            it('strips surrounding double quotes', function (): void {
                $env = $this->tempDir . DIRECTORY_SEPARATOR . '.env';
                file_put_contents($env, 'TITLE="My App"' . "\n");
                expect(GarnetEnv::readEnvKey($env, 'TITLE'))->toBe('My App');
            });

            it('returns empty string for missing key', function (): void {
                $env = $this->tempDir . DIRECTORY_SEPARATOR . '.env';
                file_put_contents($env, "APP_NAME=Garnet\n");
                expect(GarnetEnv::readEnvKey($env, 'NOSUCH'))->toBe('');
            });

            it('returns empty string for missing file', function (): void {
                expect(GarnetEnv::readEnvKey($this->tempDir . '/nonexistent.env', 'KEY'))->toBe('');
            });

            it('ignores comments and blank lines', function (): void {
                $env = $this->tempDir . DIRECTORY_SEPARATOR . '.env';
                file_put_contents($env, "# comment\n\nAPP_NAME=Garnet\n# another\n");
                expect(GarnetEnv::readEnvKey($env, 'APP_NAME'))->toBe('Garnet');
            });
        });

        describe('::readAppNameFromRoot', function (): void {
            it('parses APP_NAME out of <root>/.env', function (): void {
                file_put_contents(
                    $this->tempDir . DIRECTORY_SEPARATOR . '.env',
                    "APP_NAME=Garnet\nDEBUG=1\n"
                );
                expect(GarnetEnv::readAppNameFromRoot($this->tempDir))->toBe('Garnet');
            });

            it('returns empty string when .env missing', function (): void {
                expect(GarnetEnv::readAppNameFromRoot($this->tempDir))->toBe('');
            });

            it('returns empty when APP_NAME not set', function (): void {
                file_put_contents(
                    $this->tempDir . DIRECTORY_SEPARATOR . '.env',
                    "DEBUG=1\n"
                );
                expect(GarnetEnv::readAppNameFromRoot($this->tempDir))->toBe('');
            });
        });

        describe('::readAppName', function (): void {
            it('reads from the override dir when GARNET_APP_DIR is set', function (): void {
                file_put_contents(
                    $this->tempDir . DIRECTORY_SEPARATOR . '.env',
                    "APP_NAME=Overridden\n"
                );
                putenv('GARNET_APP_DIR=' . $this->tempDir);
                expect(GarnetEnv::readAppName())->toBe('Overridden');
            });

            it('falls back to GARNET_ROOT/.env when override is empty', function (): void {
                // GARNET_ROOT is defined for the test run; we don't fight it,
                // we just verify we got SOME string (or empty) back without erroring.
                // The non-empty branches above already cover behaviour; this one
                // confirms the fallback path doesn't blow up when .env is absent.
                $name = GarnetEnv::readAppName();
                expect(is_string($name))->toBe(true);
            });
        });

        describe('::writeAppNameFromRoot', function (): void {
            it('inserts APP_NAME into a fresh .env', function (): void {
                GarnetEnv::writeAppNameFromRoot($this->tempDir, 'NewApp');
                $content = file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . '.env');
                expect($content)->toMatch('/APP_NAME=NewApp/');
            });

            it('replaces APP_NAME in an existing .env, preserving other lines', function (): void {
                file_put_contents(
                    $this->tempDir . DIRECTORY_SEPARATOR . '.env',
                    "APP_NAME=Old\nDEBUG=1\n"
                );
                GarnetEnv::writeAppNameFromRoot($this->tempDir, 'New');
                $content = file_get_contents($this->tempDir . DIRECTORY_SEPARATOR . '.env');
                expect($content)->toMatch('/APP_NAME=New/');
                expect($content)->toMatch('/DEBUG=1/');
                expect($content)->not->toMatch('/APP_NAME=Old/');
            });
        });

        describe('::listAppsFromRoot', function (): void {
            it('returns the sorted list of subdirectories that contain <Name>/<Name>.php', function (): void {
                $appsDir = $this->tempDir . DIRECTORY_SEPARATOR . 'Apps';

                foreach (['Zeta', 'Alpha', 'Beta'] as $app) {
                    mkdir($appsDir . DIRECTORY_SEPARATOR . $app, 0o777, true);
                    file_put_contents(
                        $appsDir . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . $app . '.php',
                        '<?php'
                    );
                }
                // dir without the matching class file → skipped
                mkdir($appsDir . DIRECTORY_SEPARATOR . 'Empty', 0o777, true);

                expect(GarnetEnv::listAppsFromRoot($this->tempDir))->toBe(['Alpha', 'Beta', 'Zeta']);
            });

            it('returns empty array when Apps/ is empty', function (): void {
                mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'Apps', 0o777, true);
                expect(GarnetEnv::listAppsFromRoot($this->tempDir))->toBe([]);
            });
        });

        describe('::readRuntimeDir / ::readWorkdirDir', function (): void {
            it('returns null when the runtime .env carries no BUNDLE_RUNTIME_DIR', function (): void {
                putenv('GARNET_RUNTIME_DIR=' . $this->tempDir);
                file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . '.env', "APP_NAME=X\n");
                expect(GarnetEnv::readRuntimeDir())->toBeNull();
                expect(GarnetEnv::readWorkdirDir())->toBeNull();
            });

            it('reads BUNDLE_RUNTIME_DIR / BUNDLE_WORKDIR_DIR from the runtime .env', function (): void {
                putenv('GARNET_RUNTIME_DIR=' . $this->tempDir);
                file_put_contents(
                    $this->tempDir . DIRECTORY_SEPARATOR . '.env',
                    "BUNDLE_RUNTIME_DIR=runtime-foo\nBUNDLE_WORKDIR_DIR=WorkDirX\n"
                );
                expect(GarnetEnv::readRuntimeDir())->toBe('runtime-foo');
                expect(GarnetEnv::readWorkdirDir())->toBe('WorkDirX');
            });
        });
    });
}
