<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function base64_decode;
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function mb_convert_encoding;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetServeCommand;

    use function preg_match;

    use ReflectionMethod;

    use function str_contains;
    use function str_starts_with;
    use function strlen;
    use function substr;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    /**
     * `killStale()` shells out to real OS process managers (PowerShell/wmic/
     * pkill) so it can't be exercised end-to-end here. These specs cover the
     * pure string-building helpers that make the kill scoped and injection-safe:
     * the PowerShell -like escaper, the ERE escaper, the wmic LIKE-clause
     * escaper, and the -EncodedCommand builder — plus (mirroring what a real
     * pkill / Get-CimInstance match would do) that a path with shell/regex
     * metacharacters round-trips as a literal match instead of corrupting the
     * filter.
     */
    describe('GarnetServeCommand', function (): void {
        $invoke = function (string $method, array $args) {
            $m = new ReflectionMethod(GarnetServeCommand::class, $method);

            return $m->invokeArgs(null, $args);
        };
        $this->invoke = $invoke;

        describe('wmicLikeEscape', function (): void {
            it('doubles backslashes first, since `\\` is WQL\'s own escape char', function (): void {
                // A raw single-backslash path breaks the WQL parser outright
                // ("Invalid query") — confirmed against a live `wmic` call.
                // Windows paths like `D:\dev\R&D\app` MUST come out with every
                // backslash doubled so the LIKE clause parses and matches literally.
                $escaped = ($this->invoke)('wmicLikeEscape', ['D:\\dev\\R&D\\app']);

                expect($escaped)->toBe('D:\\\\dev\\\\R&D\\\\app');
            });

            it('escapes WQL LIKE wildcards so a literal path cannot widen the match', function (): void {
                $escaped = ($this->invoke)('wmicLikeEscape', ['D:\\dev\\R&D\\app_v1']);

                // `_` is a single-char WQL wildcard — must be escaped, not left live.
                expect($escaped)->toBe('D:\\\\dev\\\\R&D\\\\app[_]v1');
            });

            it('escapes % so it cannot be used to broaden the LIKE match', function (): void {
                $escaped = ($this->invoke)('wmicLikeEscape', ['D:\\dev\\100%done']);

                expect($escaped)->toBe('D:\\\\dev\\\\100[%]done');
            });

            it('escapes an embedded single-quote so it cannot break out of the WQL string literal', function (): void {
                $escaped = ($this->invoke)('wmicLikeEscape', ["D:\\dev\\it's\\app"]);

                expect(str_contains($escaped, "''"))->toBe(true);
                // No lone, un-doubled quote remains that could terminate the
                // surrounding 'commandline like \'%...%\'' WQL string early.
                expect($escaped)->toBe("D:\\\\dev\\\\it''s\\\\app");
            });
        });

        describe('psLikeEscape (PowerShell -like patterns)', function (): void {
            it('escapes the -like wildcard characters so a literal path cannot widen the match', function (): void {
                // * ? [ ] are PowerShell -like wildcards; backtick-prefix escapes them.
                $escaped = ($this->invoke)('psLikeEscape', ['app*v1?x[old]new']);

                expect($escaped)->toBe('app`*v1`?x`[old`]new');
            });

            it('escapes an embedded single-quote so it cannot break out of the PS string literal', function (): void {
                $escaped = ($this->invoke)('psLikeEscape', ["it's here"]);

                expect($escaped)->toBe("it''s here");
            });

            it('doubles literal backticks since backtick is -like\'s escape character', function (): void {
                $escaped = ($this->invoke)('psLikeEscape', ['a`b']);

                expect($escaped)->toBe('a``b');
            });

            it('does not escape $ or " which are literal inside single-quoted PS strings', function (): void {
                $escaped = ($this->invoke)('psLikeEscape', ['a$b"c']);

                expect($escaped)->toBe('a$b"c');
            });
        });

        describe('escapeEre (POSIX ERE patterns for pkill -f)', function (): void {
            it('escapes every actual ERE metacharacter', function (): void {
                $escaped = ($this->invoke)('escapeEre', ['.()*+?{}|^$[]']);

                // Each metachar is backslash-escaped.
                expect($escaped)->toBe('\.\(\)\*\+\?\{\}\|\^\$\[\]');
            });

            it('does NOT over-escape characters preg_quote() wrongly escapes for ERE', function (): void {
                // # ! - = : < > / are NOT ERE metacharacters — backslash-escaping
                // them is formally undefined per POSIX (GNU tolerates it; BSD/macOS
                // behavior is not guaranteed). escapeEre() must leave them bare.
                $escaped = ($this->invoke)('escapeEre', ['#!=<>:/-']);

                expect($escaped)->toBe('#!=<>:/-');
            });

            it('doubles literal backslashes without re-doubling the escapes it adds', function (): void {
                // Input D:\app.v2 (single backslash in PHP single-quoted) → each
                // \ doubled, . escaped, but the escape-backslash for . is NOT doubled.
                $escaped = ($this->invoke)('escapeEre', ['D:\\app.v2']);

                expect($escaped)->toBe('D:\\\\app\.v2');
            });
        });

        describe('psKillCommand (PowerShell -EncodedCommand builder)', function (): void {
            it('builds a Stop-Process pipeline scoped by name, commandline, and PID', function (): void {
                $cmd = ($this->invoke)('psKillCommand', ['php.exe', ['D:\\test\\dir'], 4242]);
                $prefix = 'powershell -NoProfile -NonInteractive -EncodedCommand ';

                expect(str_starts_with($cmd, $prefix))->toBe(true);

                $script = mb_convert_encoding(base64_decode(substr($cmd, strlen($prefix)), true), 'UTF-8', 'UTF-16LE');

                expect(str_contains($script, 'Get-CimInstance Win32_Process'))->toBe(true);
                expect(str_contains($script, 'Stop-Process -Id $_.ProcessId -Force'))->toBe(true);
                expect(str_contains($script, "Name -eq 'php.exe'"))->toBe(true);
                expect(str_contains($script, 'ProcessId -ne 4242'))->toBe(true);
            });

            it('psLikeEscape-s commandline patterns so -like wildcards stay literal', function (): void {
                $cmd = ($this->invoke)('psKillCommand', ['node.exe', ['garnet-serve.mjs', 'app*v1'], null]);
                $prefix = 'powershell -NoProfile -NonInteractive -EncodedCommand ';
                $script = mb_convert_encoding(base64_decode(substr($cmd, strlen($prefix)), true), 'UTF-8', 'UTF-16LE');

                // garnet-serve.mjs has no -like wildcards → passed through bare.
                expect(str_contains($script, "CommandLine -like '*garnet-serve.mjs*'"))->toBe(true);
                // app*v1 → app`*v1 (the * is wildcard-escaped for -like).
                expect(str_contains($script, "CommandLine -like '*app`*v1*'"))->toBe(true);
            });
        });

        describe('pkill regex scoping (Unix path)', function (): void {
            // killStaleUnix() builds escapeEre($publicDir) . '.*php-worker-router\.php'
            // as the pkill -f pattern. Mirror that construction here and verify a
            // path with ERE metacharacters matches itself literally and does not
            // accidentally match an unrelated app directory.
            it('matches its own worker commandline literally despite regex metacharacters in the path', function (): void {
                $publicDir = 'D:' . DIRECTORY_SEPARATOR . 'dev' . DIRECTORY_SEPARATOR . 'R&D(app)'
                    . DIRECTORY_SEPARATOR . 'Public';
                // PCRE delimiter ~ avoids clashing with the literal / that
                // escapeEre() correctly leaves unescaped (it's not an ERE
                // metachar) but would break a /…/ delimited preg pattern.
                $pattern = '~' . ($this->invoke)('escapeEre', [$publicDir]) . '.*php-worker-router\.php~';

                $ownCommandline = 'php -d opcache.enable=1 -S 127.0.0.1:8011 -t ' . $publicDir
                    . ' /framework/tooling/server/php-worker-router.php';

                expect((bool)preg_match($pattern, $ownCommandline))->toBe(true);
            });

            it('does not match a different app whose public dir merely shares a prefix', function (): void {
                $publicDir = 'D:' . DIRECTORY_SEPARATOR . 'dev' . DIRECTORY_SEPARATOR . 'R&D(app)'
                    . DIRECTORY_SEPARATOR . 'Public';
                $pattern = '~' . ($this->invoke)('escapeEre', [$publicDir]) . '.*php-worker-router\.php~';

                $otherAppCommandline = 'php -S 127.0.0.1:8011 -t D:' . DIRECTORY_SEPARATOR . 'dev'
                    . DIRECTORY_SEPARATOR . 'R&D(app)-other' . DIRECTORY_SEPARATOR . 'Public'
                    . ' /framework/tooling/server/php-worker-router.php';

                expect((bool)preg_match($pattern, $otherAppCommandline))->toBe(false);
            });
        });
    });
}
