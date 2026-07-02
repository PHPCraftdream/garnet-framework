<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L0_Core\ErrorCatcher\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars;

    if (!GlobalVars::get('ErrorCatcherTestEnabled')) {
        return;
    }

    describe('ErrorCatcher', function (): void {
        it('set_error_handler', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'error.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('A custom error has been triggered');
        });

        it('set_exception_handler', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('A custom exception');
        });

        it('set_exception_handler syntax', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception_syntax.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('syntax error');
        });

        it('set_exception_handler undef_func', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'exception_undef_func.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('syntax error');
        });

        it('set_exception_handler shutdown_function', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'shutdown_function.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('Fatal');
            expect($result)->toContain('Call to undefined function');
        });
    });
}
