<?php declare(strict_types=1);
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\Spec\CmdRunFiles {
    use PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\ErrorCatcher;

    require_once __DIR__ . '/../../../../../../vendor/autoload.php';

    (new class() extends ErrorCatcher {
        /**
         * @param bool $disabledExceptionCatchForTest
         */
        public static function setDisabledExceptionCatchForTest(bool $disabledExceptionCatchForTest): void {
            parent::$disabledExceptionCatchForTest = $disabledExceptionCatchForTest;
        }
    })::setDisabledExceptionCatchForTest(true);

    ErrorCatcher::init();

    function run1(): void {
        $run = 'undef_func();';
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
