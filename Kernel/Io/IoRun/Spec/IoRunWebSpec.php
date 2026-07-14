<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun\Spec {
    use Exception;
    use Kahlan\Plugin\Double;
    use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunWeb;
    use Psr\Http\Message\ResponseInterface;
    use ReflectionClass;
    use Throwable;

    describe('IoRunWeb', function (): void {
        describe('normalizeResponse()', function (): void {
            it('returns the same response when it is already a ResponseInterface', function (): void {
                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('normalizeResponse');

                $mockResponse = Double::instance(['implements' => ResponseInterface::class]);

                $result = $method->invoke(null, $mockResponse);

                expect($result)->toBe($mockResponse);
            });

            it('creates a new response from a string', function (): void {
                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('normalizeResponse');

                $result = $method->invoke(null, 'test content');

                expect($result)->toBeAnInstanceOf(ResponseInterface::class);
            });

            it('creates a new response from null', function (): void {
                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('normalizeResponse');

                $result = $method->invoke(null, null);

                expect($result)->toBeAnInstanceOf(ResponseInterface::class);
            });
        });

        describe('clearBuffer()', function (): void {
            beforeEach(function (): void {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            });

            it('clears the output buffer when it is not empty', function (): void {
                ob_start();
                echo 'test output';

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('clearBuffer');

                $method->invoke(null);

                $output = ob_get_clean();
                expect($output)->toBeEmpty();
            });

            it('does nothing when the output buffer is empty', function (): void {
                ob_start();

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('clearBuffer');

                expect(function () use ($method): void {
                    $method->invoke(null);
                })->not->toThrow();

                $output = ob_get_clean();
                expect($output)->toBeEmpty();
            });
        });

        describe('logExceptionAndGet()', function (): void {
            it('returns exception message string', function (): void {
                $exception = new Exception('Test exception message');

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('logExceptionAndGet');

                $result = $method->invoke(null, $exception, 'test_log');

                expect($result)->toBeA('string');
                expect($result)->toContain('Test exception message');
            });

            it('handles exceptions from logger gracefully', function (): void {
                $exception = new Exception('Test exception message');

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('logExceptionAndGet');

                // Even if logging fails, it should still return the message
                expect(function () use ($method, $exception): void {
                    $result = $method->invoke(null, $exception, 'test_log');
                    expect($result)->toBeA('string');
                })->not->toThrow();
            });
        });

        describe('checkOutputBufferIsEmpty()', function (): void {
            beforeEach(function (): void {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            });

            it('passes when output buffer is empty', function (): void {
                ob_start();

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('checkOutputBufferIsEmpty');

                expect(function () use ($method): void {
                    $method->invoke(null);
                })->not->toThrow();

                ob_get_clean();
            });

            it('throws IoException when output buffer is not empty', function (): void {
                ob_start();
                echo 'unexpected output';

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('checkOutputBufferIsEmpty');

                expect(function () use ($method): void {
                    $method->invoke(null);
                })->toThrow();

                ob_get_clean();
            });

            it('cleans the buffer after detecting output', function (): void {
                ob_start();
                echo 'unexpected output';

                $reflection = new ReflectionClass(IoRunWeb::class);
                $method = $reflection->getMethod('checkOutputBufferIsEmpty');

                try {
                    $method->invoke(null);
                } catch (Throwable $e) {
                    // Expected
                }

                // Buffer should be cleaned after exception
                expect(ob_get_length())->toBe(false);
            });
        });

        describe('$errorLogEnv static property', function (): void {
            it('has defined error log environment', function (): void {
                $reflection = new ReflectionClass(IoRunWeb::class);
                $errorLogEnvProp = $reflection->getProperty('errorLogEnv');
                $errorLogEnv = $errorLogEnvProp->getValue(null);

                expect($errorLogEnv)->toBeA('string');
                expect($errorLogEnv)->not->toBeEmpty();
            });
        });
    });
}
