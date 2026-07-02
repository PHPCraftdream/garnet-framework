<?php declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

describe('ControllerTools', function (): void {
    describe('ok()', function (): void {
        it('creates 200 response with text/html', function (): void {
            $response = ControllerTools::ok('Hello World');
            expect($response->getStatusCode())->toBe(200);
            expect($response->getHeaderLine('Content-Type'))->toBe('text/html; charset=UTF-8');
            expect((string)$response->getBody())->toBe('Hello World');
        });

        it('adds CSP header', function (): void {
            $response = ControllerTools::ok('test');
            expect($response->getHeaderLine('Content-Security-Policy'))->toBe("frame-ancestors 'self'");
        });

        it('uses existing response if provided', function (): void {
            $base = new Response(200);
            $response = ControllerTools::ok('content', $base);
            expect((string)$response->getBody())->toBe('content');
        });
    });

    describe('notFound()', function (): void {
        it('creates 404 response', function (): void {
            $response = ControllerTools::notFound('Page not found');
            expect($response->getStatusCode())->toBe(404);
            expect((string)$response->getBody())->toBe('Page not found');
        });

        it('uses default message', function (): void {
            $response = ControllerTools::notFound();
            expect((string)$response->getBody())->toBe('Not found');
        });
    });

    describe('internalError()', function (): void {
        it('creates 500 response', function (): void {
            $response = ControllerTools::internalError('Server error');
            expect($response->getStatusCode())->toBe(500);
            expect((string)$response->getBody())->toBe('Server error');
        });

        it('uses default message', function (): void {
            $response = ControllerTools::internalError();
            expect((string)$response->getBody())->toBe('Internal server error');
        });
    });

    describe('JSON()', function (): void {
        it('creates JSON response with data', function (): void {
            $response = ControllerTools::JSON(['key' => 'value']);
            expect($response->getStatusCode())->toBe(200);
            expect($response->getHeaderLine('Content-type'))->toContain('application/json');
            $body = (string)$response->getBody();
            expect($body)->toBe('{"key":"value"}');
        });

        it('handles null data', function (): void {
            $response = ControllerTools::JSON(null);
            $body = (string)$response->getBody();
            expect($body)->toBe('null');
        });

        it('handles scalars', function (): void {
            $response = ControllerTools::JSON('test');
            expect((string)$response->getBody())->toBe('"test"');
        });

        it('returns empty object on json_encode failure', function (): void {
            // Create a resource that can't be JSON encoded
            $resource = fopen('php://memory', 'r');
            $response = ControllerTools::JSON($resource);
            fclose($resource);
            expect((string)$response->getBody())->toBe('{}');
        });

        it('sets custom status code', function (): void {
            $response = ControllerTools::JSON(['error' => 'not found'], null, 404);
            expect($response->getStatusCode())->toBe(404);
        });
    });

    describe('okFile()', function (): void {
        it('sets correct MIME type for JavaScript file', function (): void {
            $response = ControllerTools::okFile('console.log("test");', 'script.js');
            expect($response->getStatusCode())->toBe(200);
            expect($response->getHeaderLine('Content-type'))->toContain('javascript');
        });

        it('sets correct MIME type for CSS file', function (): void {
            $response = ControllerTools::okFile('body { color: red; }', 'style.css');
            expect($response->getHeaderLine('Content-type'))->toContain('css');
        });

        it('handles unknown file types', function (): void {
            $response = ControllerTools::okFile('content', 'unknown.xyz');
            expect($response->getStatusCode())->toBe(200);
            // MIME might not be set for unknown types
        });
    });

    describe('okFilePath()', function (): void {
        it('reads file and creates response', function (): void {
            $tempFile = sys_get_temp_dir() . '/test_controller_' . uniqid() . '.txt';
            file_put_contents($tempFile, 'file content');

            $response = ControllerTools::okFilePath($tempFile, 'test.txt');
            unlink($tempFile);

            expect($response->getStatusCode())->toBe(200);
            expect((string)$response->getBody())->toBe('file content');
        });

        it('throws exception for non-existent file', function (): void {
            expect(function (): void {
                ControllerTools::okFilePath('/nonexistent/file.txt', 'file.txt');
            })->toThrow();
        });

        it('sets correct MIME based on filename', function (): void {
            $tempFile = sys_get_temp_dir() . '/test_controller_' . uniqid() . '.json';
            file_put_contents($tempFile, '{"test":true}');

            $response = ControllerTools::okFilePath($tempFile, 'data.json');
            unlink($tempFile);

            expect($response->getHeaderLine('Content-type'))->toContain('json');
        });
    });
});
