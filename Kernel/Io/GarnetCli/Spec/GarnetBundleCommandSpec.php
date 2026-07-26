<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function array_map;
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function file_put_contents;
    use function glob;
    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetBundleCommand;
    use ReflectionMethod;

    use function rmdir;
    use function sys_get_temp_dir;
    use function uniqid;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    describe('GarnetBundleCommand', function (): void {
        describe('::renderRuntimeGarnet', function (): void {
            beforeEach(function (): void {
                $this->tempDir = sys_get_temp_dir() . DS . 'gtest_bundle_' . uniqid();
                mkdir($this->tempDir, 0o777, true);

                // Minimal source garnet that contains the define + autoload
                // markers the rewriter looks for.
                $this->garnetSrc = $this->tempDir . DS . 'garnet';
                file_put_contents($this->garnetSrc,
                    "#!/usr/bin/env php\n"
                    . "<?php declare(strict_types=1);\n"
                    . "define('GARNET_ROOT', __DIR__);\n"
                    . "require_once GARNET_ROOT . DS . 'Framework' . DS . 'vendor' . DS . 'autoload.php';\n"
                );
            });

            afterEach(function (): void {
                if (is_dir($this->tempDir)) {
                    array_map('unlink', glob($this->tempDir . DS . '*') ?: []);
                    rmdir($this->tempDir);
                }
            });

            it('returns null when the source file is missing', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->tempDir . DS . 'nonexistent',
                    'garnet-app-myapp',
                    'MyApp',
                    'garnet-framework'
                );
                expect($out)->toBeNull();
            });

            it('rewrites GARNET_ROOT to dirname(__DIR__) for the runtime layout', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework'
                );

                expect($out)->toContain("define('GARNET_ROOT', dirname(__DIR__));");
                expect($out)->not->toContain("define('GARNET_ROOT', __DIR__);");
            });

            it('plants GARNET_APP_DIR / GARNET_APP_NAME / WORKDIR / RUNTIME putenvs', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework'
                );

                expect($out)->toContain("putenv('GARNET_APP_DIR=' . GARNET_ROOT . DS . 'garnet-app-myapp');");
                expect($out)->toContain("putenv('GARNET_APP_NAME=MyApp');");
                expect($out)->toContain("putenv('GARNET_WORKDIR_DIR=' . __DIR__ . DS . 'WorkDir');");
                expect($out)->toContain("putenv('GARNET_RUNTIME_DIR=' . __DIR__);");
            });

            it('redirects the framework autoload path to the versioned directory', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21'
                );

                expect($out)->toContain(
                    "GARNET_ROOT . DS . 'garnet-framework-2026-05-21' . DS . 'vendor' . DS . 'autoload.php'"
                );
                expect($out)->not->toContain(
                    "GARNET_ROOT . DS . 'Framework' . DS . 'vendor' . DS . 'autoload.php'"
                );
            });
        });

        describe('::renderRuntimeGarnet (app-mode / standalone app)', function (): void {
            beforeEach(function (): void {
                $this->tempDir = sys_get_temp_dir() . DS . 'gtest_bundle_appmode_' . uniqid();
                mkdir($this->tempDir, 0o777, true);

                // Verbatim shape of Templates/Application/garnet — every
                // app:create'd app starts from this exact content.
                $this->garnetSrc = $this->tempDir . DS . 'garnet';
                file_put_contents($this->garnetSrc,
                    "#!/usr/bin/env php\n"
                    . "<?php declare(strict_types=1);\n"
                    . "require_once __DIR__ . '/vendor/autoload.php';\n"
                    . "putenv('GARNET_APP_DIR=' . __DIR__);\n"
                    . '\PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner::main(__DIR__, $argv);' . "\n"
                );
            });

            afterEach(function (): void {
                if (is_dir($this->tempDir)) {
                    array_map('unlink', glob($this->tempDir . DS . '*') ?: []);
                    rmdir($this->tempDir);
                }
            });

            it('redirects the autoload require to the framework sibling\'s vendor/autoload.php', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21', true
                );

                expect($out)->toContain(
                    "require_once dirname(__DIR__) . '/garnet-framework-2026-05-21/vendor/autoload.php';"
                );
                expect($out)->not->toContain("require_once __DIR__ . '/vendor/autoload.php';");
            });

            it('plants GARNET_APP_DIR / GARNET_FRAMEWORK_DIR pointing at the sibling dirs', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21', true
                );

                expect($out)->toContain(
                    "putenv('GARNET_APP_DIR=' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'garnet-app-myapp');"
                );
                expect($out)->toContain(
                    "putenv('GARNET_FRAMEWORK_DIR=' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'garnet-framework-2026-05-21');"
                );
            });

            it('plants GARNET_APP_NAME / WORKDIR / RUNTIME putenvs same as legacy mode', function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21', true
                );

                expect($out)->toContain("putenv('GARNET_APP_NAME=MyApp');");
                expect($out)->toContain("putenv('GARNET_WORKDIR_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . 'WorkDir');");
                expect($out)->toContain("putenv('GARNET_RUNTIME_DIR=' . __DIR__);");
            });

            it("rewrites GarnetRunner::main()'s first argument to the sibling app dir, not __DIR__", function (): void {
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21', true
                );

                expect($out)->toContain(
                    "GarnetRunner::main(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'garnet-app-myapp', \$argv);"
                );
                expect($out)->not->toContain('GarnetRunner::main(__DIR__, $argv);');
            });

            it('defaults isAppMode to false — passing app-mode-shaped content through the legacy path is a silent no-op, not a crash', function (): void {
                // Regression guard for the exact bug this whole app-mode
                // branch was added to fix: before it existed, every
                // deploy:diff run silently produced an unchanged (and
                // therefore broken) runtime dispatcher for a standalone
                // app, because the legacy str_replace patterns never
                // matched this file's content — and nothing warned about
                // it, `syncRemoteRuntimeGarnet()`'s boot check just fell
                // back to keeping the previous dispatcher every time.
                $out = GarnetBundleCommand::renderRuntimeGarnet(
                    $this->garnetSrc, 'garnet-app-myapp', 'MyApp', 'garnet-framework-2026-05-21'
                );

                expect($out)->toContain("require_once __DIR__ . '/vendor/autoload.php';");
                expect($out)->toContain('GarnetRunner::main(__DIR__, $argv);');
            });
        });

        describe('::copyDir dist-recursion guard (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(GarnetBundleCommand::class, 'copyDir');
                $this->tempDir = sys_get_temp_dir() . DS . 'gtest_bundle_recursion_' . uniqid();
                mkdir($this->tempDir, 0o777, true);
                file_put_contents($this->tempDir . DS . 'a.txt', 'hello');
                file_put_contents($this->tempDir . DS . 'b.txt', 'world');
            });

            afterEach(function (): void {
                if (is_dir($this->tempDir)) {
                    $rmrf = function (string $dir) use (&$rmrf): void {
                        foreach (glob($dir . DS . '*') ?: [] as $item) {
                            is_dir($item) ? $rmrf($item) : unlink($item);
                        }
                        rmdir($dir);
                    };
                    $rmrf($this->tempDir);
                }
            });

            it('terminates and does not nest the destination inside itself when dst is nested under src', function (): void {
                // Regression guard for a real incident: app-mode's dist/
                // output lands inside vendor/phpcraftdream/garnet-framework/
                // (GARNET_ROOT resolves there), which IS the framework
                // copy's own source — without this guard the recursive
                // iterator walks into its own not-yet-finished output and
                // copies it into itself, ballooning without bound (hit
                // 2.5GB for real before being killed). This test would hang
                // (or blow up) if the guard regressed.
                $dst = $this->tempDir . DS . 'dist';

                $this->fn->invoke(null, $this->tempDir, $dst);

                expect(is_file($dst . DS . 'a.txt'))->toBe(true);
                expect(is_file($dst . DS . 'b.txt'))->toBe(true);
                // The critical assertion: dst must NOT contain a copy of
                // itself (dist/dist/…) — that self-nesting is exactly what
                // the unbounded growth looked like.
                expect(is_dir($dst . DS . 'dist'))->toBe(false);
            });
        });

        describe('::humanBytes (via reflection — pure helper)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(GarnetBundleCommand::class, 'humanBytes');
            });

            it('formats sub-KB as bytes', function (): void {
                expect($this->fn->invoke(null, 0))->toBe('0.00 B');
                expect($this->fn->invoke(null, 512))->toBe('512.00 B');
                expect($this->fn->invoke(null, 1023))->toBe('1023.00 B');
            });

            it('promotes to KB at the 1024 boundary', function (): void {
                expect($this->fn->invoke(null, 1024))->toBe('1.00 KB');
                expect($this->fn->invoke(null, 2048))->toBe('2.00 KB');
            });

            it('promotes to MB / GB at the right boundaries', function (): void {
                expect($this->fn->invoke(null, 1024 * 1024))->toBe('1.00 MB');
                expect($this->fn->invoke(null, 1024 * 1024 * 1024))->toBe('1.00 GB');
            });

            it('caps at GB — terabyte inputs still report GB (the unit table only goes that far)', function (): void {
                // 5 TB in bytes → still rendered in GB (largest unit on the scale).
                $tb = 5 * 1024 ** 4;
                $out = $this->fn->invoke(null, $tb);
                expect($out)->toContain('GB');
            });
        });

        describe('::renderSharedIndex (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(GarnetBundleCommand::class, 'renderSharedIndex');
            });

            it('produces a runnable PHP snippet that reads .env from __DIR__', function (): void {
                $out = $this->fn->invoke(null);

                expect($out)->toContain('<?php declare(strict_types=1);');
                expect($out)->toContain('parse_ini_file($_gr . \'/.env\')');
            });

            it('exits 503 when the runtime .env is missing or unreadable', function (): void {
                $out = $this->fn->invoke(null);

                expect($out)->toContain('http_response_code(503)');
                expect($out)->toContain('runtime .env missing');
            });

            it('resolves BUNDLE_FRAMEWORK_DIR / BUNDLE_APP_DIR / BUNDLE_WORKDIR_DIR / BUNDLE_PUBLIC_DIR', function (): void {
                $out = $this->fn->invoke(null);

                foreach (['BUNDLE_FRAMEWORK_DIR', 'BUNDLE_APP_DIR', 'BUNDLE_WORKDIR_DIR', 'BUNDLE_PUBLIC_DIR'] as $key) {
                    expect($out)->toContain($key);
                }
            });

            it('plants GARNET_APP_DIR (and optionally WORKDIR / PUBLIC) and requires run_web.php', function (): void {
                $out = $this->fn->invoke(null);

                expect($out)->toContain('putenv("GARNET_APP_DIR={$_app}");');
                expect($out)->toContain('/run_web.php');
            });
        });
    });
}
