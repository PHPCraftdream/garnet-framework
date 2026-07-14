<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function file_exists;
    use function file_put_contents;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetTestRemoteCommand;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetUninstallCommand;

    use function preg_match;

    use ReflectionMethod;

    use function strlen;
    use function sys_get_temp_dir;
    use function uniqid;
    use function unlink;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    /**
     * Targeted coverage of the testable helper methods inside the larger
     * CLI command classes. The top-level run() entry points end with
     * exit() and shell out, so they remain integration-shaped; the
     * helpers below are pure logic.
     */
    describe('Garnet command helpers (cross-class)', function (): void {
        $invoke = function (string $class, string $method, array $args) {
            $m = new ReflectionMethod($class, $method);

            return $m->invokeArgs(null, $args);
        };
        $this->invoke = $invoke;

        // ── GarnetUninstallCommand ─────────────────────────────────────

        describe('GarnetUninstallCommand::parseEnv', function (): void {
            beforeEach(function (): void {
                $this->envFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                    . 'gtest_parseenv_' . uniqid() . '.env';
            });

            afterEach(function (): void {
                if (file_exists($this->envFile)) {
                    unlink($this->envFile);
                }
            });

            it('parses standard KEY=VALUE lines', function (): void {
                file_put_contents($this->envFile, "APP_NAME=Garnet\nDEBUG=1\n");
                $env = ($this->invoke)(GarnetUninstallCommand::class, 'parseEnv', [$this->envFile]);

                expect($env)->toBe(['APP_NAME' => 'Garnet', 'DEBUG' => '1']);
            });

            it('skips comment lines (#) and blank lines', function (): void {
                file_put_contents($this->envFile, "# comment\n\nAPP=X\n# more\n");
                $env = ($this->invoke)(GarnetUninstallCommand::class, 'parseEnv', [$this->envFile]);

                expect($env)->toBe(['APP' => 'X']);
            });

            it('strips surrounding double and single quotes', function (): void {
                file_put_contents($this->envFile, "TITLE=\"My App\"\nTAGLINE='Hello'\n");
                $env = ($this->invoke)(GarnetUninstallCommand::class, 'parseEnv', [$this->envFile]);

                expect($env)->toBe(['TITLE' => 'My App', 'TAGLINE' => 'Hello']);
            });

            it('ignores lines that do not match KEY=VALUE shape', function (): void {
                file_put_contents($this->envFile, "garbage\n=no-key\nKEY=ok\n");
                $env = ($this->invoke)(GarnetUninstallCommand::class, 'parseEnv', [$this->envFile]);

                expect($env)->toBe(['KEY' => 'ok']);
            });

            it('requires KEY to start with uppercase letter or underscore', function (): void {
                file_put_contents($this->envFile, "lower=skip\n_OK=ok\nUPPER=yes\n");
                $env = ($this->invoke)(GarnetUninstallCommand::class, 'parseEnv', [$this->envFile]);

                expect($env)->toBe(['_OK' => 'ok', 'UPPER' => 'yes']);
            });
        });

        describe('GarnetUninstallCommand::humanBytes', function (): void {
            it('formats bytes / KB / MB / GB with two decimals', function (): void {
                expect(($this->invoke)(GarnetUninstallCommand::class, 'humanBytes', [0]))->toBe('0.00 B');
                expect(($this->invoke)(GarnetUninstallCommand::class, 'humanBytes', [1024]))->toBe('1.00 KB');
                expect(($this->invoke)(GarnetUninstallCommand::class, 'humanBytes', [1024 * 1024]))->toBe('1.00 MB');
                expect(($this->invoke)(GarnetUninstallCommand::class, 'humanBytes', [1024 ** 3]))->toBe('1.00 GB');
            });

            it('caps at GB for TB-scale inputs', function (): void {
                $tb = 5 * 1024 ** 4;
                expect(($this->invoke)(GarnetUninstallCommand::class, 'humanBytes', [$tb]))->toContain('GB');
            });
        });

        describe('GarnetUninstallCommand::randToken', function (): void {
            it('returns a string of the requested length', function (): void {
                expect(strlen(($this->invoke)(GarnetUninstallCommand::class, 'randToken', [4])))->toBe(4);
                expect(strlen(($this->invoke)(GarnetUninstallCommand::class, 'randToken', [8])))->toBe(8);
            });

            it('uses only ambiguity-free uppercase letters (no I, O — confusable with 1, 0)', function (): void {
                $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

                for ($try = 0; $try < 50; $try++) {
                    $t = ($this->invoke)(GarnetUninstallCommand::class, 'randToken', [10]);
                    expect((bool)preg_match('/^[' . $alphabet . ']+$/', $t))->toBe(true);
                }
            });

            it('produces different values on consecutive calls (entropy)', function (): void {
                $a = ($this->invoke)(GarnetUninstallCommand::class, 'randToken', [8]);
                $b = ($this->invoke)(GarnetUninstallCommand::class, 'randToken', [8]);
                expect($a)->not->toBe($b);
            });
        });

        // ── GarnetServeCommand ────────────────────────────────────────
        // The nginx upstream-config generator (makeUpstreamsConf) was
        // removed when `serve` moved to the Node dev server
        // (tooling/server/garnet-serve.mjs), which does worker routing
        // itself. The X-Test-Worker pinning that used to live in the nginx
        // map is now exercised end-to-end by the Playwright isolation
        // suite. Only the smoke contract (class + run method) remains, in
        // GarnetCommandsSmokeSpec.

        // ── GarnetTestRemoteCommand ───────────────────────────────────

        describe('GarnetTestRemoteCommand::parseFlags', function (): void {
            it('returns sane defaults for empty argv', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags', [[]]);
                expect($f)->toBe([
                    'help' => false, 'keep' => false, 'no_provision' => false,
                    'base_url' => '', 'passthrough' => [],
                ]);
            });

            it('parses --help and -h', function (): void {
                expect(($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags', [['--help']])['help'])->toBe(true);
                expect(($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags', [['-h']])['help'])->toBe(true);
            });

            it('parses --keep and --no-provision booleans', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags', [['--keep', '--no-provision']]);
                expect($f['keep'])->toBe(true);
                expect($f['no_provision'])->toBe(true);
            });

            it('parses --base-url=URL', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags',
                    [['--base-url=https://example.com']]);
                expect($f['base_url'])->toBe('https://example.com');
            });

            it('forwards every unknown arg into passthrough', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags',
                    [['--project=admin-tests', '--workers=4', 'specs/foo.spec.ts']]);
                expect($f['passthrough'])->toBe(['--project=admin-tests', '--workers=4', 'specs/foo.spec.ts']);
            });

            it('mixes known flags + passthrough correctly', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags',
                    [['--base-url=https://x.test', '--project=admin', '--keep', 'a.spec.ts']]);
                expect($f['base_url'])->toBe('https://x.test');
                expect($f['keep'])->toBe(true);
                expect($f['passthrough'])->toBe(['--project=admin', 'a.spec.ts']);
            });
        });
    });
}
