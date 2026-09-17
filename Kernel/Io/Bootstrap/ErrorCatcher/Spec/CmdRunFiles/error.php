<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\Spec\CmdRunFiles {
    use PHPCraftdream\Garnet\Kernel\Io\Bootstrap\ErrorCatcher\ErrorCatcher;

    require_once __DIR__ . '/../../../../../../vendor/autoload.php';

    ErrorCatcher::init();

    trigger_error('A custom error has been triggered');
}
