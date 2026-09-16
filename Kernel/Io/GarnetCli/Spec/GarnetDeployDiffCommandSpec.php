<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetDeployDiffCommand;
    use ReflectionMethod;
    use RuntimeException;

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

        describe('::parseFindSizeOutput', function (): void {
            it('parses "size path" lines into rel path => size', function (): void {
                $r = ($this->invoke)('parseFindSizeOutput', ["101647 gen/js/framework.abc123.gen.js\n2048 gen/css/framework.def456.gen.css\n"]);
                expect($r)->toBe([
                    'gen/js/framework.abc123.gen.js' => 101647,
                    'gen/css/framework.def456.gen.css' => 2048,
                ]);
            });

            it('returns an empty array for empty/whitespace-only output (missing remote dir)', function (): void {
                expect(($this->invoke)('parseFindSizeOutput', ['']))->toBe([]);
                expect(($this->invoke)('parseFindSizeOutput', ["  \n\n"]))->toBe([]);
            });

            it('skips lines that do not match the "<digits> <path>" shape', function (): void {
                $r = ($this->invoke)('parseFindSizeOutput', ["not a valid line\n101 real/file.js\n"]);
                expect($r)->toBe(['real/file.js' => 101]);
            });
        });

        describe('::publicRowsMissingRemote (deploy asset scope, #389)', function (): void {
            it('marks a file absent from the remote listing as an added row', function (): void {
                $local = ['gen/js/new.abc.gen.js' => '500:1700000000'];
                $r = ($this->invoke)('publicRowsMissingRemote', [$local, [], '/local/Public/assets']);
                expect($r)->toHaveLength(1);
                expect($r[0]['status'])->toBe('A');
                expect($r[0]['rel_remote'])->toBe('assets/gen/js/new.abc.gen.js');
                expect($r[0]['local_abs'])->toBe('/local/Public/assets' . DS . 'gen' . DS . 'js' . DS . 'new.abc.gen.js');
            });

            it('skips a file present remotely with the same size — the "already shipped" case', function (): void {
                $local = ['gen/js/same.abc.gen.js' => '500:1700000000'];
                $remote = ['gen/js/same.abc.gen.js' => 500];
                $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets']);
                expect($r)->toBe([]);
            });

            it('marks a file present remotely with a different size as modified — the exact bug this fixes: '
                . 'local build already up to date, but the size on the host disagrees', function (): void {
                    $local = ['gen/css/framework.gen.css' => '9999:1700000000'];
                    $remote = ['gen/css/framework.gen.css' => 111]; // stale/partial upload on the host
                    $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets']);
                    expect($r)->toHaveLength(1);
                    expect($r[0]['status'])->toBe('M');
                });

            it('never emits a delete row for a file present remotely but absent locally — '
                . 'cleanup of superseded bundles is a separate retention-policy decision', function (): void {
                    $local = [];
                    $remote = ['gen/js/old-2026-07.gen.js' => 12345];
                    $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets']);
                    expect($r)->toBe([]);
                });

            it('matches a rebranded app folder against the host — local assets/<AppName>/ vs '
                . 'remote assets/<public_name>/, which otherwise re-uploads every app asset every deploy', function (): void {
                    $local = ['IRabi/gen/js/same.abc.gen.js' => '500:1700000000'];
                    $remote = ['slotbook/gen/js/same.abc.gen.js' => 500];
                    $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets', 'IRabi', 'slotbook']);
                    expect($r)->toBe([]);
                });

            it('still reports a genuinely missing file under a rebranded app folder', function (): void {
                $local = ['IRabi/gen/js/fresh.abc.gen.js' => '500:1700000000'];
                $remote = ['slotbook/gen/js/other.def.gen.js' => 500];
                $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets', 'IRabi', 'slotbook']);
                expect($r)->toHaveLength(1);
                expect($r[0]['status'])->toBe('A');
                // rel_remote stays in local form — the destination rebrand is a later step.
                expect($r[0]['rel_remote'])->toBe('assets/IRabi/gen/js/fresh.abc.gen.js');
            });

            it('leaves framework assets alone under a rebrand — only the app folder is renamed', function (): void {
                $local = ['framework/gen/js/fw.abc.gen.js' => '500:1700000000'];
                $remote = ['framework/gen/js/fw.abc.gen.js' => 500];
                $r = ($this->invoke)('publicRowsMissingRemote', [$local, $remote, '/local/Public/assets', 'IRabi', 'slotbook']);
                expect($r)->toBe([]);
            });
        });

        describe('::parseFindSizeOutput with stat output (targeted remote probe)', function (): void {
            it('parses `stat -c "%s %n"` lines the same way as find output — the two share a format '
                . 'so the targeted probe could replace the full remote walk without a second parser', function (): void {
                    $r = ($this->invoke)('parseFindSizeOutput', ["10753 ./slotbook/gen/js/a.gen.js\n500 ./framework/gen/js/b.gen.js\n"]);
                    expect($r)->toBe([
                        './slotbook/gen/js/a.gen.js' => 10753,
                        './framework/gen/js/b.gen.js' => 500,
                    ]);
                });

            it('yields nothing for a file the host does not have — stat prints only to stderr, '
                . 'which is exactly the "absent on remote" signal', function (): void {
                    expect(($this->invoke)('parseFindSizeOutput', ['']))->toBe([]);
                });
        });

        describe('::rebrandAssetRel', function (): void {
            it('renames only the leading app segment', function (): void {
                expect(($this->invoke)('rebrandAssetRel', ['IRabi/gen/js/a.js', 'IRabi', 'slotbook']))
                    ->toBe('slotbook/gen/js/a.js');
            });

            it('matches the app segment case-insensitively — bundle lowercases it, deploy.ini may not', function (): void {
                expect(($this->invoke)('rebrandAssetRel', ['irabi/gen/js/a.js', 'IRabi', 'slotbook']))
                    ->toBe('slotbook/gen/js/a.js');
            });

            it('leaves other roots untouched', function (): void {
                expect(($this->invoke)('rebrandAssetRel', ['framework/gen/js/a.js', 'IRabi', 'slotbook']))
                    ->toBe('framework/gen/js/a.js');
            });

            it('is a no-op when no rebrand is configured', function (): void {
                expect(($this->invoke)('rebrandAssetRel', ['IRabi/gen/js/a.js', 'IRabi', 'IRabi']))
                    ->toBe('IRabi/gen/js/a.js');
                expect(($this->invoke)('rebrandAssetRel', ['IRabi/gen/js/a.js', '', '']))
                    ->toBe('IRabi/gen/js/a.js');
            });
        });

        describe('::preflightFileLimit (deploy safety cap, #390)', function (): void {
            it('returns the total when scope is within the limit', function (): void {
                $cat = ['framework' => [1], 'app' => [1], 'runtime' => [], 'public' => [1]];
                expect(($this->invoke)('preflightFileLimit', [$cat, 200]))->toBe(3);
            });

            it('returns the total when scope exactly equals the limit', function (): void {
                $cat = ['framework' => [1, 2], 'app' => [], 'runtime' => [], 'public' => []];
                expect(($this->invoke)('preflightFileLimit', [$cat, 2]))->toBe(2);
            });

            it('aborts loudly instead of silently truncating when scope exceeds the limit', function (): void {
                $cat = ['framework' => array_fill(0, 150, 1), 'app' => array_fill(0, 100, 1), 'runtime' => [], 'public' => []];
                $call = fn () => ($this->invoke)('preflightFileLimit', [$cat, 200]);
                expect($call)->toThrow(new RuntimeException('safety limit: 250 files in scope > limit 200. Pass --limit=N to override.'));
            });
        });

        describe('::planBatches (asset-before-code ordering, #390)', function (): void {
            $layout = [
                'framework_dir' => 'framework', 'app_dir' => 'app',
                'runtime_dir' => 'runtime', 'public_dir' => 'public',
                'remote_path' => '/srv/app', 'public_name' => 'myapp',
            ];
            $this->planLayout = $layout;

            it('uploads public (assets) before framework/app/runtime (code) — hashed bundle '
                . 'names make asset uploads additive, so code must never go live before its assets exist', function (): void {
                    $cat = [
                        'framework' => [['status' => 'M', 'path' => 'Framework/x.php', 'old' => null, 'rel_remote' => 'x.php']],
                        'app' => [['status' => 'M', 'path' => 'Apps/MyApp/x.php', 'old' => null, 'rel_remote' => 'x.php']],
                        'runtime' => [['status' => 'M', 'path' => 'Apps/MyApp/WorkDir/x.ini', 'old' => null, 'rel_remote' => 'x.ini']],
                        'public' => [['status' => 'A', 'path' => 'Apps/MyApp/Public/assets/app.abc123.js', 'old' => null, 'rel_remote' => 'assets/app.abc123.js']],
                    ];
                    $plan = ($this->invoke)('planBatches', [$cat, $this->planLayout, 'MyApp', ['no_delete' => false]]);
                    $buckets = array_map(
                        fn ($u) => str_contains($u['remote'], '/public/') ? 'public' : 'code',
                        $plan['uploads'],
                    );
                    expect($buckets)->toBe(['public', 'code', 'code', 'code']);
                });

            it('rebrands the asset segment whatever case the app dir uses — a case-sensitive match '
                . 'shipped assets to assets/<AppName>/ while the *Gen.php beside them pointed at '
                . 'assets/<public_name>/, i.e. a live page referencing a 404', function (): void {
                    $cat = [
                        'framework' => [], 'app' => [], 'runtime' => [],
                        'public' => [[
                            'status' => 'A',
                            'path' => 'Apps/MyApp/Public/assets/MyApp/gen/js/a.abc.gen.js',
                            'old' => null,
                            'rel_remote' => 'assets/MyApp/gen/js/a.abc.gen.js',
                        ]],
                    ];
                    $plan = ($this->invoke)('planBatches', [$cat, $this->planLayout, 'MyApp', ['no_delete' => false]]);
                    expect($plan['uploads'][0]['remote'])->toBe('/srv/app/public/assets/myapp/gen/js/a.abc.gen.js');
                });

            it('rebrands the upload/ segment the same way', function (): void {
                $cat = [
                    'framework' => [], 'app' => [], 'runtime' => [],
                    'public' => [[
                        'status' => 'A',
                        'path' => 'Apps/MyApp/Public/upload/MyApp/x.png',
                        'old' => null,
                        'rel_remote' => 'upload/MyApp/x.png',
                    ]],
                ];
                $plan = ($this->invoke)('planBatches', [$cat, $this->planLayout, 'MyApp', ['no_delete' => false]]);
                expect($plan['uploads'][0]['remote'])->toBe('/srv/app/public/upload/myapp/x.png');
            });

            it('leaves the path alone when no public_name is configured at all', function (): void {
                $layout = ['framework_dir' => 'framework', 'app_dir' => 'app',
                    'runtime_dir' => 'runtime', 'public_dir' => 'public',
                    'remote_path' => '/srv/app', 'public_name' => ''];
                $cat = [
                    'framework' => [], 'app' => [], 'runtime' => [],
                    'public' => [[
                        'status' => 'A',
                        'path' => 'Apps/MyApp/Public/assets/MyApp/gen/js/a.abc.gen.js',
                        'old' => null,
                        'rel_remote' => 'assets/MyApp/gen/js/a.abc.gen.js',
                    ]],
                ];
                $plan = ($this->invoke)('planBatches', [$cat, $layout, 'MyApp', ['no_delete' => false]]);
                expect($plan['uploads'][0]['remote'])->toBe('/srv/app/public/assets/MyApp/gen/js/a.abc.gen.js');
            });

            it('is idempotent on a segment that already equals public_name', function (): void {
                $cat = [
                    'framework' => [], 'app' => [], 'runtime' => [],
                    'public' => [[
                        'status' => 'A',
                        'path' => 'Apps/MyApp/Public/assets/myapp/gen/js/a.abc.gen.js',
                        'old' => null,
                        'rel_remote' => 'assets/myapp/gen/js/a.abc.gen.js',
                    ]],
                ];
                $plan = ($this->invoke)('planBatches', [$cat, $this->planLayout, 'MyApp', ['no_delete' => false]]);
                expect($plan['uploads'][0]['remote'])->toBe('/srv/app/public/assets/myapp/gen/js/a.abc.gen.js');
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

        describe('::gitTry (public — GarnetDeployFullCommand relies on this exact contract '
            . 'to advance the deploy-sha marker without risking exit(1) after a deploy already succeeded)', function (): void {
                it('returns [0, HEAD sha] for a valid command against this repo', function (): void {
                    [$rc, $out] = GarnetDeployDiffCommand::gitTry(['rev-parse', 'HEAD']);
                    expect($rc)->toBe(0);
                    expect(trim($out))->toMatch('/^[0-9a-f]{40}$/');
                });

                it('returns a non-zero code (never throws/exits) for an invalid git subcommand', function (): void {
                    [$rc] = GarnetDeployDiffCommand::gitTry(['not-a-real-git-subcommand']);
                    expect($rc)->not->toBe(0);
                });
            });
    });
}
