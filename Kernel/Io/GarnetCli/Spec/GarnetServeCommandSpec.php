<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetServeCommand;

    use function preg_match;
    use function preg_quote;

    use ReflectionMethod;

    use function str_contains;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    /**
     * `killStale()` shells out to real OS process managers (wmic/pkill) so
     * it can't be exercised end-to-end here. These specs cover the pure
     * string-building helpers that make the kill scoped and injection-safe:
     * the WMIC LIKE-clause escaper, and (mirroring what a real pkill ERE
     * match would do) that a path with shell/regex metacharacters round-trips
     * as a literal match instead of corrupting the filter.
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

        describe('pkill regex scoping (Unix path)', function (): void {
            // killStale() builds `preg_quote($publicDir, '/') . '.*php-worker-router\.php'`
            // as the pkill -f pattern. Mirror that construction here and
            // verify a path with ERE metacharacters (the case called out in
            // the audit: `D:\dev\R&D\app`) matches itself literally and does
            // not accidentally match an unrelated app directory.
            it('matches its own worker commandline literally despite regex metacharacters in the path', function (): void {
                $publicDir = 'D:' . DIRECTORY_SEPARATOR . 'dev' . DIRECTORY_SEPARATOR . 'R&D(app)'
                    . DIRECTORY_SEPARATOR . 'Public';
                $pattern = '/' . preg_quote($publicDir, '/') . '.*php-worker-router\.php/';

                $ownCommandline = 'php -d opcache.enable=1 -S 127.0.0.1:8011 -t ' . $publicDir
                    . ' /framework/tooling/server/php-worker-router.php';

                expect((bool)preg_match($pattern, $ownCommandline))->toBe(true);
            });

            it('does not match a different app whose public dir merely shares a prefix', function (): void {
                $publicDir = 'D:' . DIRECTORY_SEPARATOR . 'dev' . DIRECTORY_SEPARATOR . 'R&D(app)'
                    . DIRECTORY_SEPARATOR . 'Public';
                $pattern = '/' . preg_quote($publicDir, '/') . '.*php-worker-router\.php/';

                $otherAppCommandline = 'php -S 127.0.0.1:8011 -t D:' . DIRECTORY_SEPARATOR . 'dev'
                    . DIRECTORY_SEPARATOR . 'R&D(app)-other' . DIRECTORY_SEPARATOR . 'Public'
                    . ' /framework/tooling/server/php-worker-router.php';

                expect((bool)preg_match($pattern, $otherAppCommandline))->toBe(false);
            });
        });
    });
}
