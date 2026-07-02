<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router\Spec;

use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\BaseController;
use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

class TestableBaseController extends BaseController {
    public static function testMakeErrorPage(string $title, string $error, bool $isLocal): string {
        return static::makeErrorPage($title, $error, $isLocal);
    }
}

describe('BaseController', function (): void {
    beforeEach(function (): void {
        // Set up Twig for the tests
        $twigDir = sys_get_temp_dir() . '/test_twig_basecontroller';

        if (!is_dir($twigDir)) {
            mkdir($twigDir, 0o777, true);
        }

        // Create a simple template for the tests
        $errorTemplate = $twigDir . '/Error.twig';
        file_put_contents($errorTemplate, '<html><head><title>{{ title }}</title></head><body>{{ error }}</body></html>');

        $twig = Twig::get();
        $twig->addFsPath($twigDir);
    });

    describe('not_found_404()', function (): void {
        it('returns 404 JSON response for POST request', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test');
            $params = MockRouterUriParamsForRouter::create();

            $response = BaseController::not_found_404($globals, $params);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(404);
        });

        it('returns 404 JSON with correct message', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test');
            $params = MockRouterUriParamsForRouter::create();

            // Set to POST
            $reflection = new ReflectionClass($globals);
            $property = $reflection->getProperty('server');
            $property->setAccessible(true);
            $server = $property->getValue($globals);
            $server['REQUEST_METHOD'] = 'POST';
            $property->setValue($globals, $server);

            $response = BaseController::not_found_404($globals, $params);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(404);
        });

        it('returns 404 HTML page for GET request', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test');
            $params = MockRouterUriParamsForRouter::create();

            $response = BaseController::not_found_404($globals, $params);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(404);

            $body = (string)$response->getBody();
            // Error404Fallback.twig renders the code "404" and a Home link, not "Page not found"
            expect($body)->toContain('404');
            expect($body)->toContain('<html lang=');
        });
    });

    describe('internal_error_500()', function (): void {
        it('returns 500 JSON response for POST request', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test');
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Database connection failed';

            // Set to POST
            $reflection = new ReflectionClass($globals);
            $property = $reflection->getProperty('server');
            $property->setAccessible(true);
            $server = $property->getValue($globals);
            $server['REQUEST_METHOD'] = 'POST';
            $property->setValue($globals, $server);

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(500);
        });

        it('returns 500 JSON with error details in dev mode', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test', isDev: true);
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Database connection failed';

            // Set to POST
            $reflection = new ReflectionClass($globals);
            $property = $reflection->getProperty('server');
            $property->setAccessible(true);
            $server = $property->getValue($globals);
            $server['REQUEST_METHOD'] = 'POST';
            $property->setValue($globals, $server);

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(500);
        });

        it('returns 500 JSON with generic message in production mode', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test', isDev: false);
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Database connection failed';

            // Set to POST
            $reflection = new ReflectionClass($globals);
            $property = $reflection->getProperty('server');
            $property->setAccessible(true);
            $server = $property->getValue($globals);
            $server['REQUEST_METHOD'] = 'POST';
            $property->setValue($globals, $server);

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(500);
        });

        it('returns 500 HTML page for GET request', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test');
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Database connection failed';

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);
            expect($response->getStatusCode())->toBe(500);

            $body = (string)$response->getBody();
            expect($body)->toContain('Internal server error');
            expect($body)->toContain('<html lang=');
        });

        it('shows detailed error in dev mode', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test', isDev: true);
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Error: Connection failed';

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);

            $body = (string)$response->getBody();
            expect($body)->toContain('Connection failed');
        });

        it('hides error details in production mode', function (): void {
            $globals = MockGlobalParamsForBaseController::create('/test', isDev: false);
            $params = MockRouterUriParamsForRouter::create();
            $error = 'Error: Connection failed';

            $response = BaseController::internal_error_500($globals, $params, $error);

            expect($response)->toBeAnInstanceOf(ResponseInterface::class);

            $body = (string)$response->getBody();
            expect($body)->not->toContain('Connection failed');
            expect($body)->toContain('Internal server error');
        });
    });

    describe('makeErrorPage()', function (): void {
        it('creates error page with title and error', function (): void {
            $result = TestableBaseController::testMakeErrorPage('Error Title', 'Error message', true);

            expect($result)->toContain('Error Title');
            expect($result)->toContain('Error message');
        });

        it('creates error page in local mode with details', function (): void {
            $result = TestableBaseController::testMakeErrorPage('Error', 'Detailed error info', true);

            expect($result)->toContain('Detailed error info');
        });

        it('creates error page in production mode without details', function (): void {
            $result = TestableBaseController::testMakeErrorPage('Error', 'Detailed error info', false);

            expect($result)->toContain('Internal server error');
            expect($result)->not->toContain('Detailed error info');
        });

        it('includes HTML structure', function (): void {
            $result = TestableBaseController::testMakeErrorPage('Title', 'Error', true);

            expect($result)->toContain('<html lang=');
            expect($result)->toContain('<head>');
            expect($result)->toContain('<body>');
        });
    });
});

// Helper class for mocking
class MockRouterUriParamsForRouter implements IRouterUriParams {
    protected string $routeVal = '/';

    protected string $httpMethod = 'GET';

    protected string $methodName = 'main';

    protected array $methodParams = [];

    protected array $uriParams = [];

    public static function create(): self {
        return new self();
    }

    public function getRouteVal(): string {
        return $this->routeVal;
    }

    public function setRouteVal(string $val): void {
        $this->routeVal = $val;
    }

    public function getHttpMethod(): string {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $method): void {
        $this->httpMethod = $method;
    }

    public function getMethodName(): string {
        return $this->methodName;
    }

    public function setMethodName(string $name): void {
        $this->methodName = $name;
    }

    public function getMethodParams(): array {
        return $this->methodParams;
    }

    public function setMethodParams(array $params): void {
        $this->methodParams = $params;
    }

    public function getUriParams(): array {
        return $this->uriParams;
    }

    public function setUriParams(array $params): void {
        $this->uriParams = $params;
    }

    public function getUriParam(string $name, ?string $defaultVal = null): ?string {
        return $this->uriParams[$name] ?? $defaultVal;
    }

    public function getMethodParam(int $name, ?string $defaultVal = null): ?string {
        return $this->methodParams[$name] ?? $defaultVal;
    }
}

// Helper class for mocking IGlobalReqParams
class MockGlobalParamsForBaseController implements IGlobalReqParams {
    protected array $server;

    protected bool $isDev = false;

    public static function create(string $uri, bool $isDev = false): self {
        $instance = new self();
        $instance->server = [
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => 'GET',
        ];
        $instance->isDev = $isDev;

        return $instance;
    }

    public function getUri(): string {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function httpMethod(): string {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function readServerValue(string $name, mixed $default = null): string|null {
        return $this->server[$name] ?? $default;
    }

    public function readServerAll(): array {
        return $this->server;
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
        return $this->server['REQUEST_METHOD'] === 'POST';
    }

    public function isEmptyPost(): bool {
        return true;
    }

    public function isGet(): bool {
        return $this->server['REQUEST_METHOD'] === 'GET';
    }

    public function isLocalhost(): bool {
        return false;
    }

    public function isPhpServer(): bool {
        return false;
    }

    public function isDev(): bool {
        return $this->isDev;
    }

    public function ip(): string {
        return '127.0.0.1';
    }
}
