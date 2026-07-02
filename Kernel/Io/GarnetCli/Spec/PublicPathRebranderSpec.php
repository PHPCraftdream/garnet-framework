<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function count;
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function implode;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\PublicPathRebrander;

    use function str_ends_with;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    describe('PublicPathRebrander', function (): void {
        describe('::rewritePairs', function (): void {
            it('produces both original-case and lowercase from→to pairs', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('MyApp', 'myapp-deployed');

                expect($pairs)->toBe([
                    '/assets/MyApp/' => '/assets/myapp-deployed/',
                    '/upload/MyApp/' => '/upload/myapp-deployed/',
                    '/assets/myapp/' => '/assets/myapp-deployed/',
                    '/upload/myapp/' => '/upload/myapp-deployed/',
                ]);
            });

            it('collapses to identical from-and-to when app and public names already match', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('myapp', 'myapp');

                // /assets/myapp/ → /assets/myapp/ — no-op, but it's the same key
                // showing up twice in the array (PHP merges duplicate string keys).
                expect($pairs['/assets/myapp/'])->toBe('/assets/myapp/');
                expect($pairs['/upload/myapp/'])->toBe('/upload/myapp/');
            });
        });

        describe('::rewriteContent', function (): void {
            it('rewrites every occurrence of the from-paths', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('MyApp', 'myapp');
                $in = 'href="/assets/MyApp/foo.css" src="/upload/MyApp/x.png"';
                $out = PublicPathRebrander::rewriteContent($in, $pairs);

                expect($out)->toBe('href="/assets/myapp/foo.css" src="/upload/myapp/x.png"');
            });

            it('handles content already in the lowercase variant', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('MyApp', 'myapp');
                $in = 'src="/assets/myapp/foo.js"';
                $out = PublicPathRebrander::rewriteContent($in, $pairs);

                expect($out)->toBe('src="/assets/myapp/foo.js"');
            });

            it('is idempotent: running twice yields the same result', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('MyApp', 'live');
                $in = '/assets/MyApp/x /upload/MyApp/y';
                $once = PublicPathRebrander::rewriteContent($in, $pairs);
                $twice = PublicPathRebrander::rewriteContent($once, $pairs);

                expect($twice)->toBe($once);
                expect($twice)->toBe('/assets/live/x /upload/live/y');
            });

            it('leaves unrelated path segments alone', function (): void {
                $pairs = PublicPathRebrander::rewritePairs('MyApp', 'live');
                $in = '/api/MyApp/x /static/MyApp.png';
                $out = PublicPathRebrander::rewriteContent($in, $pairs);

                expect($out)->toBe($in);  // no match for /api/ or filename suffix
            });
        });

        describe('::perAppIndexContent', function (): void {
            it('produces a runnable PHP shim that requires <runtime>/_shared_index.php', function (): void {
                $out = PublicPathRebrander::perAppIndexContent('garnet-runtime-myapp');

                expect($out)->toBe(
                    "<?php\nrequire __DIR__ . '/../garnet-runtime-myapp/_shared_index.php';\n"
                );
            });

            it('respects whatever runtime name is passed (no escaping, no normalising)', function (): void {
                $out = PublicPathRebrander::perAppIndexContent('custom-runtime-name');

                expect($out)->toContain("'/../custom-runtime-name/_shared_index.php'");
            });
        });

        describe('::genFiles', function (): void {
            beforeEach(function (): void {
                // genFiles resolves the framework bundle dir from GarnetRunner;
                // the CLI runner never booted in this spec, so pin it.
                $this->savedFrameworkDir = GarnetRunner::$frameworkDir;
                GarnetRunner::$frameworkDir = GARNET_ROOT;
            });

            afterEach(function (): void {
                GarnetRunner::$frameworkDir = $this->savedFrameworkDir;
            });

            it('returns absolute paths to the four *Gen.php files', function (): void {
                $files = PublicPathRebrander::genFiles('MyApp');

                expect(count($files))->toBe(4);

                foreach ($files as $f) {
                    expect($f)->toContain(GARNET_ROOT);
                    expect(str_ends_with($f, 'Gen.php'))->toBe(true);
                }
            });

            it('covers both the app foreground and the framework bundle pair', function (): void {
                $files = PublicPathRebrander::genFiles('MyApp');

                $joined = implode('|', $files);
                expect($joined)->toContain('Apps' . DS . 'MyApp' . DS . 'Foreground' . DS . 'ForegroundJsGen.php');
                expect($joined)->toContain('Apps' . DS . 'MyApp' . DS . 'Foreground' . DS . 'ForegroundCssGen.php');
                expect($joined)->toContain('Bundle' . DS . 'FrameworkJsGen.php');
                expect($joined)->toContain('Bundle' . DS . 'FrameworkCssGen.php');
            });
        });
    });
}
