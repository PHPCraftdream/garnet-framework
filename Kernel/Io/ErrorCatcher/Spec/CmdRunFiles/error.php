<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L0_Core\ErrorCatcher\Errors {
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;

    require_once __DIR__ . '/../../../../../vendor/autoload.php';

    ErrorCatcher::init();

    trigger_error('A custom error has been triggered');
}
