<?php declare(strict_types=1);

namespace PHPCraftdream\Application {
    require_once __DIR__ . '/autoload.php';

    use PHPCraftdream\Application\Common\Backend\CommonController;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Io\Emitter\Emitter;
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;
    use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunWeb;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
    use PHPCraftdream\Garnet\Kernel\Io\Router\RouterDevFile;
    use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
    use Psr\Http\Message\ResponseInterface;
    use Throwable;

    // -------------------------------
    BenchmarkLog::init(($_SERVER['REQUEST_METHOD'] ?? 'GET') . ': ' . ($_SERVER['REQUEST_URI'] ?? '/'));

    gc_disable();

    $errorCallback = [CommonController::class, 'internal_error_500'](...);
    $globalParams = GlobalReqParams::from($_SERVER, $_GET, GlobalReqParams::currentPost(), $_COOKIE, $_FILES);
    $isDev = $globalParams->isDev() && Env::isDevDir();

    // -------------------------------

    ErrorCatcher::init(
        static function (string $type, string $message) use (&$globalParams, &$errorCallback): void {
            $uriParams = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

            try {
                Logger::get(Logger::ERROR_LOGGER)->write($type, $message);
            } catch (Throwable $e) {
            }

            $result = $errorCallback($globalParams, $uriParams, $message);
            Emitter::emit($result);
        }
    );

    $app = new Application($isDev);
    $app->webInit();

    // -------------------------------

    if ($isDev && defined('PUBLIC_DIR')) {
        $fileRouter = new RouterDevFile();
        $fileRouter->addFilesDir('/', PUBLIC_DIR);

        $result = $fileRouter->dispatch($globalParams);

        if ($result instanceof ResponseInterface) {
            Emitter::emit($result);

            exit;
        }
    }

    BenchmarkLog::log('config_done');

    // -------------------------------

    $isEnabledDb = DbPool::get()->getDbConfig()->paramInt('enabled') !== 0;

    if ($isEnabledDb) {
        DbPool::get()->newLink();
        BenchmarkLog::log('db_connected');
    }

    IoRunWeb::run(
        $globalParams,
        [$app, 'runWebApp'](...),
        $errorCallback,
    );

    BenchmarkLog::log('output_done');

    if ($isEnabledDb) {
        DbPool::get()->pollFinishAll();
    }

    BenchmarkLog::log('loop_done');

    if (BenchmarkLog::last() > 0.5) {
        Logger::get(Logger::SYSTEM_LOGGER)->append('benchmark', BenchmarkLog::printItems());
    }
}
