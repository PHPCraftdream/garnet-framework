<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router\Spec;

use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\Router;
use ReflectionClass;

describe('Router', function (): void {
    describe('__construct()', function (): void {
        it('stores handlerNotFound closure', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            expect($router->getHandlerNotFound())->toBe($handler);
        });
    });

    describe('getHandlerNotFound()', function (): void {
        it('returns the handlerNotFound closure', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            expect($router->getHandlerNotFound())->toBe($handler);
        });
    });

    describe('setHandlerNotFound()', function (): void {
        it('sets new handlerNotFound closure', function (): void {
            $handler1 = fn () => 'not found 1';
            $handler2 = fn () => 'not found 2';
            $router = new Router($handler1);

            $router->setHandlerNotFound($handler2);

            expect($router->getHandlerNotFound())->toBe($handler2);
        });
    });

    describe('add()', function (): void {
        it('adds route to routes array', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $router->add('/test', TestController::class);

            $reflection = new ReflectionClass($router);
            $property = $reflection->getProperty('routes');
            $property->setAccessible(true);

            $routes = $property->getValue($router);

            expect(array_key_exists('/test', $routes))->toBe(true);
            expect($routes['/test'])->toBe([TestController::class, [], []]);
        });

        it('normalizes URI with leading slash', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $router->add('test', TestController::class);

            $reflection = new ReflectionClass($router);
            $property = $reflection->getProperty('routes');
            $property->setAccessible(true);

            $routes = $property->getValue($router);

            expect(array_key_exists('/test', $routes))->toBe(true);
        });

        it('adds route with callBefore callbacks', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $before = [fn () => 'before'];
            $router->add('/test', TestController::class, $before);

            $reflection = new ReflectionClass($router);
            $property = $reflection->getProperty('routes');
            $property->setAccessible(true);

            $routes = $property->getValue($router);

            expect($routes['/test'])->toBe([TestController::class, $before, []]);
        });

        it('adds route with callAfter callbacks', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $after = [fn () => 'after'];
            $router->add('/test', TestController::class, [], $after);

            $reflection = new ReflectionClass($router);
            $property = $reflection->getProperty('routes');
            $property->setAccessible(true);

            $routes = $property->getValue($router);

            expect($routes['/test'])->toBe([TestController::class, [], $after]);
        });

        it('throws exception when route already exists', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $router->add('/test', TestController::class);

            expect(function () use ($router): void {
                $router->add('/test', TestController::class);
            })->toThrow();
        });
    });

    describe('dispatch()', function (): void {
        it('calls handlerNotFound when route not found', function (): void {
            $handlerCalled = false;
            $handler = function () use (&$handlerCalled) {
                $handlerCalled = true;

                return 'not found';
            };

            $router = new Router($handler);

            $uriParams = MockRouterUriParams::create('/not-found', 'GET');
            $globals = MockGlobalParams::create();

            $result = $router->dispatch($globals, $uriParams);

            expect($handlerCalled)->toBe(true);
            expect($result)->toBe('not found');
        });

        it('returns handlerNotFound result when route not found', function (): void {
            $handler = fn () => '404';
            $router = new Router($handler);

            $uriParams = MockRouterUriParams::create('/not-found', 'GET');
            $globals = MockGlobalParams::create();

            $result = $router->dispatch($globals, $uriParams);

            expect($result)->toBe('404');
        });

        it('dispatches to existing route', function (): void {
            $handler = fn () => 'not found';
            $router = new Router($handler);

            $router->add('/test', TestController::class);

            $uriParams = MockRouterUriParams::create('/test', 'GET');
            $globals = MockGlobalParams::create();

            $result = $router->dispatch($globals, $uriParams);

            expect($result)->toBe('GET__main called');
        });
    });
});

// Helper classes for testing
class TestController {
    public static function GET__main(): string {
        return 'GET__main called';
    }
}

class MockRouterUriParams implements IRouterUriParams {
    public function __construct(
        protected string $routeVal,
        protected string $httpMethod = 'GET'
    ) {
    }

    public static function create(string $routeVal, string $httpMethod = 'GET'): self {
        return new self($routeVal, $httpMethod);
    }

    public function getHttpMethod(): string {
        return $this->httpMethod;
    }

    public function getRouteVal(): string {
        return $this->routeVal;
    }

    public function getUriParams(): array {
        return [];
    }

    public function getUriParam(string|int $name, ?string $defaultVal = null): ?string {
        return $defaultVal;
    }

    public function getMethodName(): string {
        return 'main';
    }

    public function getMethodParams(): array {
        return [];
    }

    public function getMethodParam(string|int $name, ?string $defaultVal = null): ?string {
        return $defaultVal;
    }
}

class MockGlobalParams implements IGlobalReqParams {
    public static function create(): self {
        return new self();
    }

    public function httpMethod(): string {
        return 'GET';
    }

    public function getUri(): string {
        return '/test';
    }

    public function readServerValue(string $name, mixed $default = null): string|null {
        return null;
    }

    public function readServerAll(): array {
        return [];
    }

    public function readGetValue(string $name, mixed $default = null): mixed {
        return $default;
    }

    public function readGetAll(): array {
        return [];
    }

    public function readPostValue(string $name, mixed $default = null): mixed {
        return $default;
    }

    public function readPostAll(): array {
        return [];
    }

    public function readCookieValue(string $name, mixed $default = null): ?string {
        return null;
    }

    public function readCookieAll(): array {
        return [];
    }

    public function readFilesValue(string $name, mixed $default = null): mixed {
        return $default;
    }

    public function readFilesAll(): array {
        return [];
    }

    public function isPost(): bool {
        return false;
    }

    public function isEmptyPost(): bool {
        return true;
    }

    public function isGet(): bool {
        return true;
    }

    public function isLocalhost(): bool {
        return false;
    }

    public function isPhpServer(): bool {
        return false;
    }

    public function isDev(): bool {
        return false;
    }

    public function ip(): string {
        return '127.0.0.1';
    }
}
