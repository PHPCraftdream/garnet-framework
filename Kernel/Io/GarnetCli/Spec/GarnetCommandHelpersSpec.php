<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use const DIRECTORY_SEPARATOR;

    use function file_exists;
    use function file_put_contents;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\CliTokens;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\Remote\GarnetTestRemoteCommand;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Project\GarnetUninstallCommand;
    use ReflectionMethod;

    use function sys_get_temp_dir;
    use function uniqid;
    use function unlink;

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

        // randToken() was unified into CliTokens::randToken() (see
        // Kernel/Io/GarnetCli/Spec/CliTokensSpec.php for coverage) — the
        // per-command private copies this describe block used to test via
        // reflection no longer exist.

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
                    'base_url' => '', 'token' => '', 'passthrough' => [],
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

            it('parses --token=SECRET and keeps it out of passthrough', function (): void {
                $f = ($this->invoke)(GarnetTestRemoteCommand::class, 'parseFlags',
                    [['--token=abcdefghijklmnop', 'a.spec.ts']]);
                expect($f['token'])->toBe('abcdefghijklmnop');
                expect($f['passthrough'])->toBe(['a.spec.ts']);
            });
        });

        describe('GarnetTestRemoteCommand::chooseToken', function (): void {
            $choose = fn (bool $noProvision, string $explicit, string $remembered) => ($this->invoke)(
                GarnetTestRemoteCommand::class,
                'chooseToken',
                [$noProvision, $explicit, $remembered],
            );
            $this->choose = $choose;

            it('mints a fresh token for a provisioning run', function (): void {
                $a = ($this->choose)(false, '', '');
                $b = ($this->choose)(false, '', '');
                expect($a['error'])->toBe('');
                expect($a['token'])->toMatch('/^[a-f0-9]{32}$/');
                // A secret reused across runs would outlive its scope.
                expect($a['token'])->not->toBe($b['token']);
            });

            it('ignores a remembered token when it is about to provision', function (): void {
                // The fresh scope gets a fresh gate; reusing the old secret
                // would leave the previous one valid on the box.
                $r = ($this->choose)(false, '', 'rememberedtoken12345');
                expect($r['token'])->not->toBe('rememberedtoken12345');
            });

            it('reuses the remembered token under --no-provision', function (): void {
                $r = ($this->choose)(true, '', 'rememberedtoken12345');
                expect($r)->toBe(['token' => 'rememberedtoken12345', 'error' => '']);
            });

            it('refuses --no-provision with nothing to reuse', function (): void {
                // The bug this whole path exists for: a fresh token here was
                // never planted, so the run died in globalSetup instead.
                $r = ($this->choose)(true, '', '');
                expect($r['token'])->toBe('');
                expect($r['error'])->toMatch('/no token to reuse/');
            });

            it('refuses a malformed remembered token', function (): void {
                $r = ($this->choose)(true, '', 'short');
                expect($r['token'])->toBe('');
                expect($r['error'])->toMatch('/malformed/');
            });

            it('prefers an explicit --token over both other sources', function (): void {
                expect(($this->choose)(true, 'explicittoken1234567', 'rememberedtoken12345'))
                    ->toBe(['token' => 'explicittoken1234567', 'error' => '']);
                expect(($this->choose)(false, 'explicittoken1234567', '')['token'])
                    ->toBe('explicittoken1234567');
            });

            it('rejects an explicit token outside the provision charset', function (): void {
                foreach (['short', 'has spaces in it xx', 'ok-but-then/slash1234'] as $bad) {
                    $r = ($this->choose)(false, $bad, '');
                    expect($r['token'])->toBe('');
                    expect($r['error'])->toMatch('/16-128 chars/');
                }
            });
        });
    });
}
