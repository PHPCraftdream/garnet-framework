<?php declare(strict_types=1);

namespace PHPCraftdream\Application {
    require_once __DIR__ . '/autoload.php';

    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunConsole;

    gc_disable();

    $isDev = Env::isDevDir();
    $app = new Application($isDev);
    $app->consoleInit();

    IoRunConsole::run();
}
