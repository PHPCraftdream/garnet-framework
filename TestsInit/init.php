<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Support\Benchmark\BenchmarkLog;
use PHPCraftdream\Garnet\Kernel\Io\Render\Twig\Twig;
use PHPCraftdream\Garnet\Kernel\Io\Services\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Services\Logs\Logger;

require_once __DIR__ . '/../vendor/autoload.php';

// GARNET_ROOT — тот же приём, что и с логгерами ниже: определить один раз
// здесь, а не оставлять на волю порядка загрузки спек. Раньше каждая спека,
// которой нужна константа, считала её сама через dirname(__DIR__, N) — и
// значение получалось от того, какая спека загрузилась первой. Любой перенос
// файла в подкаталог менял N, но не ронял ничего заметного: сосед с прежней
// глубиной успевал определить константу первым. Здесь путь считается от
// TestsInit/, который лежит в корне репозитория и с места не двигается.
if (!defined('GARNET_ROOT')) {
    define('GARNET_ROOT', dirname(__DIR__, 2));
}

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
