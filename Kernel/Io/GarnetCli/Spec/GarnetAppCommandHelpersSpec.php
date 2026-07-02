<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function basename;
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetAppCommand;
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

    describe('GarnetAppCommand helpers', function (): void {
        $invoke = function (string $method, array $args) {
            $m = new ReflectionMethod(GarnetAppCommand::class, $method);
            $m->setAccessible(true);

            return $m->invokeArgs(null, $args);
        };
        $this->invoke = $invoke;

        describe('::parseCreateOpts', function (): void {
            it('returns sane defaults for empty argv', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [[]]);
                expect($r)->toBe(['name' => '', 'target' => '', 'noInstall' => false, 'quiet' => false]);
            });

            it('reads the first positional as the app name', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [['MyApp']]);
                expect($r['name'])->toBe('MyApp');
            });

            it('keeps the FIRST positional only — later positionals are ignored', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [['First', 'Second']]);
                expect($r['name'])->toBe('First');
            });

            it('parses --target=PATH and trims surrounding quotes / whitespace', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [['--target=./my-app']]);
                expect($r['target'])->toBe('./my-app');

                $r2 = ($this->invoke)('parseCreateOpts', [['--target=  "spaced/path"  ']]);
                expect($r2['target'])->toBe('spaced/path');
            });

            it('honours --no-install', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [['MyApp', '--no-install']]);
                expect($r['noInstall'])->toBe(true);
            });

            it('honours --quiet and -q', function (): void {
                $r1 = ($this->invoke)('parseCreateOpts', [['MyApp', '--quiet']]);
                expect($r1['quiet'])->toBe(true);

                $r2 = ($this->invoke)('parseCreateOpts', [['MyApp', '-q']]);
                expect($r2['quiet'])->toBe(true);
            });

            it('parses a realistic argv with flags around the positional', function (): void {
                $r = ($this->invoke)('parseCreateOpts', [['--target=./fixture', 'FixtureApp', '--no-install', '-q']]);
                expect($r)->toBe([
                    'name' => 'FixtureApp',
                    'target' => './fixture',
                    'noInstall' => true,
                    'quiet' => true,
                ]);
            });
        });

        describe('::isTextFile', function (): void {
            it('recognises PHP / TSX / TS / JS / CSS / Twig / etc. as text', function (): void {
                foreach (['x.php', 'a.tsx', 'b.ts', 'c.js', 'd.css', 'e.less', 'f.twig', 'g.md', 'h.json', 'i.ini', 'j.yml', 'k.yaml', 'l.html', 'm.htm', 'n.xml', 'o.bat', 'p.sh', 'q.conf', 'r.template'] as $f) {
                    expect(($this->invoke)('isTextFile', [$f]))->toBe(true);
                }
            });

            it('skips binary file types', function (): void {
                foreach (['logo.png', 'photo.jpg', 'icon.ico', 'font.woff', 'archive.zip', 'compiled.so'] as $f) {
                    expect(($this->invoke)('isTextFile', [$f]))->toBe(false);
                }
            });

            it('treats .env and .env.example as text by basename rule', function (): void {
                expect(($this->invoke)('isTextFile', ['/some/path/.env']))->toBe(true);
                expect(($this->invoke)('isTextFile', ['/some/path/.env.example']))->toBe(true);
            });

            it('extension match is case-insensitive', function (): void {
                expect(($this->invoke)('isTextFile', ['README.MD']))->toBe(true);
                expect(($this->invoke)('isTextFile', ['Config.JSON']))->toBe(true);
            });
        });

        describe('::isAbsolutePath', function (): void {
            it('returns false for empty string', function (): void {
                expect(($this->invoke)('isAbsolutePath', ['']))->toBe(false);
            });

            it('returns true for unix-style absolute paths', function (): void {
                expect(($this->invoke)('isAbsolutePath', ['/var/www']))->toBe(true);
                expect(($this->invoke)('isAbsolutePath', ['/tmp/x']))->toBe(true);
            });

            it('returns true for Windows drive-letter paths (C:\\, D:/)', function (): void {
                expect(($this->invoke)('isAbsolutePath', ['C:\\Users']))->toBe(true);
                expect(($this->invoke)('isAbsolutePath', ['D:/dev']))->toBe(true);
            });

            it('returns true for backslash-only roots', function (): void {
                expect(($this->invoke)('isAbsolutePath', ['\\server\\share']))->toBe(true);
            });

            it('returns false for relative paths', function (): void {
                expect(($this->invoke)('isAbsolutePath', ['./relative']))->toBe(false);
                expect(($this->invoke)('isAbsolutePath', ['../up']))->toBe(false);
                expect(($this->invoke)('isAbsolutePath', ['relative/path']))->toBe(false);
            });
        });

        describe('::relativePath', function (): void {
            it('returns "." when from === to', function (): void {
                $here = sys_get_temp_dir();
                expect(($this->invoke)('relativePath', [$here, $here]))->toBe('.');
            });

            it('computes a sibling relative path', function (): void {
                $base = sys_get_temp_dir();
                $a = $base . DS . 'a' . uniqid();
                $b = $base . DS . 'b' . uniqid();
                mkdir($a, 0o777, true);
                mkdir($b, 0o777, true);

                $rel = ($this->invoke)('relativePath', [$a, $b]);
                expect($rel)->toBe('../' . basename($b));

                rmdir($a);
                rmdir($b);
            });

            it('computes a nested relative path going up multiple levels', function (): void {
                $base = sys_get_temp_dir() . DS . 'gtest_rel_' . uniqid();
                mkdir($base . DS . 'deep' . DS . 'nested', 0o777, true);
                mkdir($base . DS . 'sibling', 0o777, true);

                $rel = ($this->invoke)('relativePath',
                    [$base . DS . 'deep' . DS . 'nested', $base . DS . 'sibling']);

                expect($rel)->toBe('../../sibling');

                rmdir($base . DS . 'deep' . DS . 'nested');
                rmdir($base . DS . 'deep');
                rmdir($base . DS . 'sibling');
                rmdir($base);
            });
        });
    });
}
