<?php declare(strict_types=1);
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\Spec\CmdRunFiles {
    require_once __DIR__ . '/../../../../../../vendor/autoload.php';

    use Exception;
    use PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\ErrorCatcher;

    ErrorCatcher::init();

    function run1(): void {
        throw new Exception('A custom exception');
    }

    function run2(): void {
        run1();
    }

    class Run {
        public static function run(): void {
            run2();
        }
    }

    Run::run();
}
