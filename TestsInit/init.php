<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

require_once __DIR__ . '/../vendor/autoload.php';

$envIniDir = __DIR__ . '/TestConfig/';

IniConfig::defineAppIni($envIniDir . 'app.ini');
IniConfig::defineDbIni($envIniDir . 'db.ini');
IniConfig::defineEmailIni($envIniDir . 'email.ini');
BenchmarkLog::init('init tests');

// Mirror BaseAppInit::defineLogs() so specs that call Logger::get(...)
// (e.g. SessionIntegrationSpec.php) don't depend on some other spec having
// coincidentally defined the same logger first as a side effect — the
// exact test-pollution bug already fixed in FrameworkControllerSpec.php.
$logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-test-logs' . DIRECTORY_SEPARATOR;

if (!is_dir($logDir)) {
    mkdir($logDir, 0o755, true);
}

Logger::define($logDir, Logger::ERROR_LOGGER);
Logger::define($logDir, Logger::SYSTEM_LOGGER);
Logger::define($logDir, Logger::ROUTE_LOGGER);

// Register the framework's bundled Twig templates so specs that call
// Twig::get()->render('Layout/...') resolve. Production loads these via
// BaseAppInit; the test runner has no Bundle bootstrap, so register
// here once.
Twig::get()->addFsPath(realpath(__DIR__ . '/../Bundle/TwigTemplates') ?: __DIR__ . '/../Bundle/TwigTemplates');

$pdo = ExtPDO::get();
