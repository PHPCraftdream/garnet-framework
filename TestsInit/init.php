<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

require_once __DIR__ . '/../vendor/autoload.php';

$envIniDir = __DIR__ . '/TestConfig/';

IniConfig::defineAppIni($envIniDir . 'app.ini');
IniConfig::defineDbIni($envIniDir . 'db.ini');
IniConfig::defineEmailIni($envIniDir . 'email.ini');
BenchmarkLog::init('init tests');

// Register the framework's bundled Twig templates so specs that call
// Twig::get()->render('Layout/...') resolve. Production loads these via
// BaseAppInit; the test runner has no Bundle bootstrap, so register
// here once.
Twig::get()->addFsPath(realpath(__DIR__ . '/../Bundle/TwigTemplates') ?: __DIR__ . '/../Bundle/TwigTemplates');

$pdo = ExtPDO::get();
