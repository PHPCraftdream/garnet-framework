<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Spec;

use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
use ReflectionClass;
use Throwable;

// Test bridge — exposes the protected makeErrorPage() so we can call it,
// and lets us re-point $appIniNamespace to a temp ini.
class TestFrameworkController extends FrameworkController {
    public static function testMakeErrorPage(string $title, string $error, bool $isLocal): string {
        return static::makeErrorPage($title, $error, $isLocal);
    }

    public static function setAppIniNamespace(string $namespace): void {
        static::$appIniNamespace = $namespace;
    }
}

describe('FrameworkController', function (): void {
    // Pre-resolved once per file: the framework's bundled Twig templates dir,
    // re-registered after every spec because beforeEach wipes Twig instances.
    $frameworkTwigDir = realpath(__DIR__ . '/../../../Bundle/TwigTemplates') ?: __DIR__ . '/../../../Bundle/TwigTemplates';

    // The beforeEach below wipes several global singletons (IniConfig,
    // Twig, Logger, RuntimeParams) to isolate this file's own tests from
    // whatever real .ini paths/instances TestsInit/init.php set up at
    // bootstrap — but nothing restored them afterward, so every OTHER spec
    // file that ran later in kahlan's single shared process (e.g. anything
    // alphabetically after Kernel/Core/) saw permanently empty
    // IniConfig::$initParams, surfacing as spurious "Env not found: ENV_DB"
    // failures far away from here. Snapshot once and restore once so the
    // reset stays contained to this file.
    $realIniConfigParams = null;
    $realIniConfigItems = null;
    $realTwigInstances = null;
    $realLoggerLoggers = null;
    $realLoggerParams = null;
    $realRuntimeParamsInstance = null;

    beforeAll(function () use (
        &$realIniConfigParams,
        &$realIniConfigItems,
        &$realTwigInstances,
        &$realLoggerLoggers,
        &$realLoggerParams,
        &$realRuntimeParamsInstance
    ): void {
        $reflection = new ReflectionClass(IniConfig::class);
        $property = $reflection->getProperty('initParams');
        $realIniConfigParams = $property->getValue();

        $property = $reflection->getProperty('items');
        $realIniConfigItems = $property->getValue();

        $reflection = new ReflectionClass(Twig::class);
        $property = $reflection->getProperty('instances');
        $realTwigInstances = $property->getValue();

        $reflection = new ReflectionClass(Logger::class);
        $property = $reflection->getProperty('loggers');
        $realLoggerLoggers = $property->getValue();

        $property = $reflection->getProperty('params');
        $realLoggerParams = $property->getValue();

        $reflection = new ReflectionClass(\PHPCraftdream\Garnet\Kernel\Core\Tools\RuntimeParams::class);
        $property = $reflection->getProperty('instance');
        $realRuntimeParamsInstance = $property->getValue();
    });

    afterAll(function () use (
        &$realIniConfigParams,
        &$realIniConfigItems,
        &$realTwigInstances,
        &$realLoggerLoggers,
        &$realLoggerParams,
        &$realRuntimeParamsInstance
    ): void {
        $reflection = new ReflectionClass(IniConfig::class);
        $property = $reflection->getProperty('initParams');
        $property->setValue(null, $realIniConfigParams);

        $property = $reflection->getProperty('items');
        $property->setValue(null, $realIniConfigItems);

        $reflection = new ReflectionClass(Twig::class);
        $property = $reflection->getProperty('instances');
        $property->setValue(null, $realTwigInstances);

        $reflection = new ReflectionClass(Logger::class);
        $property = $reflection->getProperty('loggers');
        $property->setValue(null, $realLoggerLoggers);

        $property = $reflection->getProperty('params');
        $property->setValue(null, $realLoggerParams);

        $reflection = new ReflectionClass(\PHPCraftdream\Garnet\Kernel\Core\Tools\RuntimeParams::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, $realRuntimeParamsInstance);
    });

    beforeEach(function () use ($frameworkTwigDir): void {
        // Reset all singletons that the controller touches, so each test
        // starts from a clean slate.
        $reflection = new ReflectionClass(IniConfig::class);
        $property = $reflection->getProperty('initParams');
        $property->setValue(null, []);

        $property = $reflection->getProperty('items');
        $property->setValue(null, []);

        $reflection = new ReflectionClass(Twig::class);
        $property = $reflection->getProperty('instances');

        try {
            $property->setValue(null, []);
        } catch (Throwable $e) {
        }

        $reflection = new ReflectionClass(Logger::class);
        $property = $reflection->getProperty('loggers');

        try {
            $property->setValue(null, []);
        } catch (Throwable $e) {
        }

        $property = $reflection->getProperty('params');

        try {
            $property->setValue(null, []);
        } catch (Throwable $e) {
        }

        $reflection = new ReflectionClass(\PHPCraftdream\Garnet\Kernel\Core\Tools\RuntimeParams::class);
        $property = $reflection->getProperty('instance');

        try {
            $property->setValue(null, null);
        } catch (Throwable $e) {
        }

        // Twig::$instances was just wiped — re-register the framework's
        // template path so the controller can find Layout/ErrorPage.twig.
        Twig::get()->addFsPath($frameworkTwigDir);

        TestFrameworkController::setAppIniNamespace(IniConfig::ENV_APP);
    });

    describe('makeErrorPage()', function (): void {
        beforeEach(function (): void {
            // Provide a minimal app.ini so AppConfig::get() finds title/description.
            $iniFile = tempnam(sys_get_temp_dir(), 'app_test');
            file_put_contents($iniFile, '
title=Test App
description=Test Description
');

            IniConfig::defineAppIni($iniFile);

            $logDir = sys_get_temp_dir() . '/test_logs';

            if (!is_dir($logDir)) {
                mkdir($logDir, 0o777, true);
            }

            Logger::define($logDir, Logger::ERROR_LOGGER);
        });

        it('returns HTML error page in production mode', function (): void {
            $result = TestFrameworkController::testMakeErrorPage('Error Title', 'Something went wrong', false);

            expect($result)->toContain('<html');
            expect($result)->toContain('Internal server error.');
            expect($result)->not->toContain('Something went wrong');
        });

        it('returns detailed HTML error page in local mode', function (): void {
            $error = "Error: Something went wrong\n  at line 10";
            $result = TestFrameworkController::testMakeErrorPage('Error Title', $error, true);

            expect($result)->toContain('<html');
            expect($result)->toContain('Something went wrong');
            expect($result)->toContain('line');
        });

        it('includes app title from config', function (): void {
            $result = TestFrameworkController::testMakeErrorPage('Test', 'Error', false);

            expect($result)->toContain('Test App');
        });

        it('includes app description from config', function (): void {
            $result = TestFrameworkController::testMakeErrorPage('Test', 'Error', false);

            expect($result)->toContain('Test Description');
        });

        it('handles multiple error lines in local mode', function (): void {
            $error = "Error 1\nError 2\nError 3";
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            expect($result)->toContain('Error 1');
            expect($result)->toContain('Error 2');
            expect($result)->toContain('Error 3');
        });

        it('handles empty error message', function (): void {
            $result = TestFrameworkController::testMakeErrorPage('Title', '', false);

            expect($result)->toContain('Internal server error.');
        });

        it('formats error with line class', function (): void {
            $error = 'Error message';
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            // Layout/ErrorPage.twig uses single quotes on its class attrs.
            expect($result)->toContain("class='line'");
        });

        it('handles special characters in error message', function (): void {
            $error = "Error: <script>alert('test')</script>";
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            // ErrorTools doesn't sanitise — raw HTML survives.
            expect($result)->toContain("<script>alert('test')</script>");
        });

        it('handles whitespace in error message', function (): void {
            $error = '  Error    message  ';
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            expect($result)->toContain('&nbsp;&nbsp;&nbsp;&nbsp;');
        });

        it('preserves original title in output', function (): void {
            $result = TestFrameworkController::testMakeErrorPage('Custom Title', 'Error', false);

            expect($result)->toContain('Custom Title');
        });

        it('handles missing app config gracefully', function (): void {
            // Strip configured ini so AppConfig::get() falls through to the
            // catch branch in makeErrorPage().
            $reflection = new ReflectionClass(IniConfig::class);
            $property = $reflection->getProperty('items');
            $property->setValue(null, []);

            TestFrameworkController::setAppIniNamespace('NON_EXISTENT_NAMESPACE');

            $result = TestFrameworkController::testMakeErrorPage('Title', 'Error', false);

            expect($result)->toContain('Internal server error.');
        });

        it('formats error with bold labels for local mode', function (): void {
            $error = 'ErrorType: Something went wrong';
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            expect($result)->toContain('<b>ErrorType</b>');
        });

        it('handles nested error traces', function (): void {
            $error = "Error 1\n  Error 2\n    Error 3";
            $result = TestFrameworkController::testMakeErrorPage('Title', $error, true);

            expect($result)->toContain('Error 1');
            expect($result)->toContain('Error 2');
            expect($result)->toContain('Error 3');
        });
    });
});
