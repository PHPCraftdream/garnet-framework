<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetDeployDiffCommand;
    use ReflectionMethod;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    describe('GarnetDeployDiffCommand', function (): void {
        // Convenience reflector for the private static helpers we test.
        $invoke = function (string $method, array $args) {
            $m = new ReflectionMethod(GarnetDeployDiffCommand::class, $method);

            return $m->invokeArgs(null, $args);
        };
        $this->invoke = $invoke;

        describe('::categorizeSinglePath', function (): void {
            it('routes Framework/* to the framework bucket and strips the prefix', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['Framework/Kernel/Io/Router/Router.php', 'MyApp']);
                expect($r)->toBe(['bucket' => 'framework', 'rel_remote' => 'Kernel/Io/Router/Router.php']);
            });

            it('routes Apps/<App>/WorkDir/* to runtime with a WorkDir/ prefix', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['Apps/MyApp/WorkDir/Config/db.ini', 'MyApp']);
                expect($r)->toBe(['bucket' => 'runtime', 'rel_remote' => 'WorkDir/Config/db.ini']);
            });

            it('routes Apps/<App>/Public/* to the public bucket', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['Apps/MyApp/Public/index.php', 'MyApp']);
                expect($r)->toBe(['bucket' => 'public', 'rel_remote' => 'index.php']);
            });

            it('routes Apps/<App>/Tests/* to the skip bucket', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['Apps/MyApp/Tests/foo.spec.ts', 'MyApp']);
                expect($r['bucket'])->toBe('skip');
            });

            it('routes other Apps/<App>/* paths to the app bucket', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['Apps/MyApp/Foreground/Controllers/Foo.php', 'MyApp']);
                expect($r)->toBe(['bucket' => 'app', 'rel_remote' => 'Foreground/Controllers/Foo.php']);
            });

            it('supports the shorthand WorkDir/* (no Apps/<App>/ prefix)', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['WorkDir/maintenance.flag', 'MyApp']);
                expect($r)->toBe(['bucket' => 'runtime', 'rel_remote' => 'WorkDir/maintenance.flag']);
            });

            it('returns null for paths that don\'t match any rule', function (): void {
                $r = ($this->invoke)('categorizeSinglePath', ['docs/architecture.md', 'MyApp']);
                expect($r)->toBeNull();
            });
        });

        describe('::needsRebrand', function (): void {
            it('rebrands every *Gen.php', function (): void {
                expect(($this->invoke)('needsRebrand', ['Apps/MyApp/Foreground/ForegroundJsGen.php']))->toBe(true);
                expect(($this->invoke)('needsRebrand', ['Framework/Bundle/FrameworkCssGen.php']))->toBe(true);
            });

            it('rebrands frontend asset types', function (): void {
                foreach (['js', 'css', 'map', 'html', 'svg'] as $ext) {
                    expect(($this->invoke)('needsRebrand', ["assets/x.{$ext}"]))->toBe(true);
                }
            });

            it('handles uppercase extensions (rebrand only matches lowercase)', function (): void {
                // The implementation lowercases the extension before checking,
                // so uppercase suffixes are still recognised.
                expect(($this->invoke)('needsRebrand', ['logo.SVG']))->toBe(true);
            });

            it('does not rebrand backend PHP files (not Gen)', function (): void {
                expect(($this->invoke)('needsRebrand', ['Apps/MyApp/Foreground/Controllers/Foo.php']))->toBe(false);
            });

            it('does not rebrand binary assets (png, jpg, ico)', function (): void {
                expect(($this->invoke)('needsRebrand', ['favicon.ico']))->toBe(false);
                expect(($this->invoke)('needsRebrand', ['photo.jpg']))->toBe(false);
                expect(($this->invoke)('needsRebrand', ['icon.png']))->toBe(false);
            });
        });

        describe('::hasNoSelectors', function (): void {
            it('returns true when every selector field is empty', function (): void {
                $opts = [
                    'since' => '', 'from' => '', 'after' => '',
                    'range' => '', 'branch' => '', 'commits' => [],
                ];
                expect(($this->invoke)('hasNoSelectors', [$opts]))->toBe(true);
            });

            it('returns false when --since is set', function (): void {
                $opts = ['since' => '2 days ago', 'from' => '', 'after' => '',
                    'range' => '', 'branch' => '', 'commits' => []];
                expect(($this->invoke)('hasNoSelectors', [$opts]))->toBe(false);
            });

            it('returns false when a --commit is set', function (): void {
                $opts = ['since' => '', 'from' => '', 'after' => '',
                    'range' => '', 'branch' => '', 'commits' => ['abc1234']];
                expect(($this->invoke)('hasNoSelectors', [$opts]))->toBe(false);
            });

            it('returns false when --branch is set', function (): void {
                $opts = ['since' => '', 'from' => '', 'after' => '',
                    'range' => '', 'branch' => 'feature/x', 'commits' => []];
                expect(($this->invoke)('hasNoSelectors', [$opts]))->toBe(false);
            });
        });

        describe('::parseArgs', function (): void {
            it('returns a fully-populated options array with sane defaults for empty argv', function (): void {
                $opts = ($this->invoke)('parseArgs', [[]]);

                expect($opts['apply'])->toBe(false);
                expect($opts['yes'])->toBe(false);
                expect($opts['since'])->toBe('');
                expect($opts['commits'])->toBe([]);
                expect($opts['files'])->toBe([]);
                expect($opts['exclude'])->toBe([]);
                expect($opts['full_public'])->toBe(false);
                expect($opts['boot_check'])->toBe(true);
            });

            it('parses --apply as a boolean', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--apply']]);
                expect($opts['apply'])->toBe(true);
            });

            it('accepts -y as an alias for --yes', function (): void {
                $opts = ($this->invoke)('parseArgs', [['-y']]);
                expect($opts['yes'])->toBe(true);
            });

            it('parses --since=DATE', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--since=2 days ago']]);
                expect($opts['since'])->toBe('2 days ago');
            });

            it('parses --commit= and accumulates multiple commits', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--commit=abc1234', '--commit=def5678']]);
                expect($opts['commits'])->toBe(['abc1234', 'def5678']);
            });

            it('parses --file= and accumulates multiple files', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--file=Apps/MyApp/Foreground/Foo.php', '--file=bar.php']]);
                expect($opts['files'])->toBe(['Apps/MyApp/Foreground/Foo.php', 'bar.php']);
            });

            it('parses --exclude= as a repeatable list', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--exclude=Apps/MyApp/Migrations/*', '--exclude=Apps/MyApp/docs/*']]);
                expect($opts['exclude'])->toBe(['Apps/MyApp/Migrations/*', 'Apps/MyApp/docs/*']);
            });

            it('parses --full-public', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--full-public']]);
                expect($opts['full_public'])->toBe(true);
            });

            it('respects --no-delete', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--no-delete']]);
                expect($opts['no_delete'])->toBe(true);
            });

            it('respects --reset-opcache', function (): void {
                $opts = ($this->invoke)('parseArgs', [['--reset-opcache']]);
                expect($opts['reset_opcache'])->toBe(true);
            });

            it('respects --no-frontend (frontend: false) and --frontend (frontend: true)', function (): void {
                $noFront = ($this->invoke)('parseArgs', [['--no-frontend']]);
                expect($noFront['frontend'])->toBe(false);

                $force = ($this->invoke)('parseArgs', [['--frontend']]);
                expect($force['frontend'])->toBe(true);
            });
        });

        describe('::computeUndeployedGap', function (): void {
            it('returns an empty array when there is no marker', function (): void {
                $r = ($this->invoke)('computeUndeployedGap', [['abc1234'], null]);
                expect($r)->toBe([]);
            });

            it('returns an empty array when shas list is empty', function (): void {
                $r = ($this->invoke)('computeUndeployedGap', [[], 'def5678']);
                expect($r)->toBe([]);
            });
        });
    });
}
