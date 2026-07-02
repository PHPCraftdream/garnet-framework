<?php declare(strict_types=1);
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace PHPCraftdream\Garnet\Kernel\L0_Core\ErrorCatcher\Errors {
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;

    require_once __DIR__ . '/../../../../../vendor/autoload.php';

    ErrorCatcher::init();

    function run1(): void {
        $run = 'undef_func(;';
        eval($run);
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
