<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Logs\Spec {
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionClass;

    describe('Logger', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(Logger::class);
            $paramsProperty = $reflection->getProperty('params');
            $paramsProperty->setAccessible(true);
            $paramsProperty->setValue([]);

            $loggersProperty = $reflection->getProperty('loggers');
            $loggersProperty->setAccessible(true);
            $loggersProperty->setValue([]);
        });

        afterEach(function (): void {
            $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

            if (is_dir($logDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($logDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getPathname());
                    } else {
                        unlink($file->getPathname());
                    }
                }
                rmdir($logDir);
            }
        });

        describe('define()', function (): void {
            it('defines a logger with valid directory', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');

                $reflection = new ReflectionClass(Logger::class);
                $paramsProperty = $reflection->getProperty('params');
                $paramsProperty->setAccessible(true);
                $params = $paramsProperty->getValue();

                expect(array_key_exists('test_logger', $params))->toBe(true);
                expect($params['test_logger'])->toBe($logDir . DIRECTORY_SEPARATOR);
            });

            it('throws exception when directory does not exist', function (): void {
                expect(function (): void {
                    Logger::define('/nonexistent/directory', 'test_logger');
                })->toThrow(new LoggerException('Directory not found: /nonexistent/directory' . DIRECTORY_SEPARATOR));
            });

            it('normalizes directory path with trailing slash', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test' . DIRECTORY_SEPARATOR;

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');

                $reflection = new ReflectionClass(Logger::class);
                $paramsProperty = $reflection->getProperty('params');
                $paramsProperty->setAccessible(true);
                $params = $paramsProperty->getValue();

                expect($params['test_logger'])->toBe($logDir);
            });
        });

        describe('get()', function (): void {
            it('returns a logger instance for defined name', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                expect($logger)->toBeAnInstanceOf(Logger::class);

                $reflection = new ReflectionClass($logger);
                $nameProperty = $reflection->getProperty('name');
                $nameProperty->setAccessible(true);
                expect($nameProperty->getValue($logger))->toBe('test_logger');
            });

            it('throws exception when logger not defined', function (): void {
                expect(function (): void {
                    Logger::get('nonexistent_logger');
                })->toThrow(new LoggerException('Logger not found: nonexistent_logger'));
            });

            it('returns same instance for subsequent calls', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger1 = Logger::get('test_logger');
                $logger2 = Logger::get('test_logger');

                expect($logger1)->toBe($logger2);
            });
        });

        describe('silentGet()', function (): void {
            it('returns null when logger not defined', function (): void {
                $logger = Logger::silentGet('nonexistent_logger');
                expect($logger)->toBeNull();
            });

            it('returns logger instance for defined name', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::silentGet('test_logger');

                expect($logger)->toBeAnInstanceOf(Logger::class);
            });

            it('returns same instance for subsequent calls', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger1 = Logger::silentGet('test_logger');
                $logger2 = Logger::silentGet('test_logger');

                expect($logger1)->toBe($logger2);
            });
        });

        describe('write()', function (): void {
            it('creates a log file with message', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->write('test_log', 'Test message');

                $dateStamp = date('Y-m-d');
                $hash = md5('test_logTest message');
                $expectedFile = $logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-test_log-' . $hash . '.log';

                expect(is_file($expectedFile))->toBe(true);
            });

            it('does not create duplicate log files for same name and message', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->write('test_log', 'Test message');
                $logger->write('test_log', 'Test message');

                $dateStamp = date('Y-m-d');
                $files = glob($logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-test_log-*.log');

                expect(count($files))->toBe(1);
            });

            it('creates separate log files for different names or messages', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->write('test_log1', 'Test message');
                $logger->write('test_log2', 'Test message');
                $logger->write('test_log1', 'Different message');

                $dateStamp = date('Y-m-d');
                $files = glob($logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-*.log');

                expect(count($files))->toBe(3);
            });

            it('includes timestamp in log file', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->write('test_log', 'Test message');

                $dateStamp = date('Y-m-d');
                $hash = md5('test_logTest message');
                $file = $logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-test_log-' . $hash . '.log';

                $content = file_get_contents($file);
                expect($content)->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}:/');
            });
        });

        describe('append()', function (): void {
            it('appends message to log file', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->append('test_log', 'Message 1');
                $logger->append('test_log', 'Message 2');

                $dateStamp = date('Y-m-d');
                $file = $logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-test_log.log';

                $content = file_get_contents($file);

                expect($content)->toContain('Message 1');
                expect($content)->toContain('Message 2');
                expect($content)->toMatch('/Message 1.*Message 2/s');
            });

            it('includes timestamp in appended messages', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->append('test_log', 'Test message');

                $dateStamp = date('Y-m-d');
                $file = $logDir . DIRECTORY_SEPARATOR . $dateStamp . DIRECTORY_SEPARATOR . 'test_logger-test_log.log';

                $content = file_get_contents($file);
                expect($content)->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}:/');
            });

            it('creates log directory if it does not exist', function (): void {
                $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet-logger-test';

                if (!is_dir($logDir)) {
                    mkdir($logDir, 0o777, true);
                }

                Logger::define($logDir, 'test_logger');
                $logger = Logger::get('test_logger');

                $logger->append('test_log', 'Test message');

                $dateStamp = date('Y-m-d');
                $logDirWithDate = $logDir . DIRECTORY_SEPARATOR . $dateStamp;

                expect(is_dir($logDirWithDate))->toBe(true);
            });
        });
    });
}
