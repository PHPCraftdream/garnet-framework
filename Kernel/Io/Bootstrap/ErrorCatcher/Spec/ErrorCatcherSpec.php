<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L0_Core\ErrorCatcher\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\Runtime\GlobalVars\GlobalVars;

    if (!GlobalVars::get('ErrorCatcherTestEnabled')) {
        return;
    }

    // shell_exec spawns a real child `php` process per call. Under Windows,
    // reading its output pipe can transiently return null/empty when the OS
    // is busy spawning many other processes at once (as happens mid full
    // suite run) even though the child ran and produced output fine — a
    // pipe-read race, not a real failure. Retry a couple of times before
    // trusting an empty result.
    $runCmd = function (string $cmd): string {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = shell_exec($cmd);

            if ($result !== null && $result !== '') {
                return $result;
            }
        }

        return (string)$result;
    };

    describe('ErrorCatcher', function () use ($runCmd): void {
        it('set_error_handler', function () use ($runCmd): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'error.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = $runCmd("{$phpRunCmd} {$file}");

            expect($result)->toContain('A custom error has been triggered');
        });

        it('set_exception_handler', function () use ($runCmd): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = $runCmd("{$phpRunCmd} {$file}");

            expect($result)->toContain('A custom exception');
        });

        it('set_exception_handler syntax', function () use ($runCmd): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception_syntax.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = $runCmd("{$phpRunCmd} {$file}");

            expect($result)->toContain('syntax error');
        });

        it('set_exception_handler undef_func', function () use ($runCmd): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception_undef_func.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = $runCmd("{$phpRunCmd} {$file}");

            expect($result)->toContain('syntax error');
        });

        it('set_exception_handler shutdown_function', function () use ($runCmd): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'shutdown_function.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = $runCmd("{$phpRunCmd} {$file}");

            expect($result)->toContain('Fatal');
            expect($result)->toContain('Call to undefined function');
        });
    });
}
