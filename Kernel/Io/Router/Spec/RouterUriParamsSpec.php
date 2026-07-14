<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router\Spec;

use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
use ReflectionClass;

describe('RouterUriParams', function (): void {
    describe('makeClear()', function (): void {
        it('creates instance with clear URI', function (): void {
            $params = RouterUriParams::makeClear('GET');

            expect($params->getRouteVal())->toBe('/');
            expect($params->getHttpMethod())->toBe('GET');
            expect($params->getMethodName())->toBe('main');
        });

        it('sets custom HTTP method', function (): void {
            $params = RouterUriParams::makeClear('POST');

            expect($params->getHttpMethod())->toBe('POST');
        });
    });

    describe('fromGlobals()', function (): void {
        it('creates instance from globals', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/test');
            expect($params->getHttpMethod())->toBe('GET');
            expect($params->getMethodName())->toBe('main');
        });

        it('strips query string from URI', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test?foo=bar&baz=qux');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/test');
        });

        it('normalizes URI with leading slash', function (): void {
            $globals = MockGlobalParamsForRouter::create('test');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/test');
        });

        it('extracts method name from URI with tilde', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~customMethod');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/test');
            expect($params->getMethodName())->toBe('customMethod');
        });

        it('extracts method params from URI', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~method/param1/param2');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodName())->toBe('method');
            expect($params->getMethodParams())->toBe(['param1', 'param2']);
        });

        it('throws exception for direct main call', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~main');

            expect(function () use ($globals): void {
                RouterUriParams::fromGlobals($globals);
            })->toThrow(new RouterException('DIRECT_CALL_MAIN_DISABLED'));
        });

        it('extracts URI params with tilde syntax', function (): void {
            $globals = MockGlobalParamsForRouter::create('/users/id~123');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/users/{id}');
            expect($params->getUriParam('id'))->toBe('123');
        });

        it('extracts multiple URI params', function (): void {
            $globals = MockGlobalParamsForRouter::create('/posts/postId~45/comments/commentId~67');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/posts/{postId}/comments/{commentId}');
            expect($params->getUriParam('postId'))->toBe('45');
            expect($params->getUriParam('commentId'))->toBe('67');
        });

        it('returns empty array when no URI params', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getUriParams())->toBe([]);
        });
    });

    describe('getHttpMethod()', function (): void {
        it('returns HTTP method', function (): void {
            $params = RouterUriParams::makeClear('POST');

            expect($params->getHttpMethod())->toBe('POST');
        });
    });

    describe('getRouteVal()', function (): void {
        it('returns route value', function (): void {
            $params = RouterUriParams::makeClear('GET');

            expect($params->getRouteVal())->toBe('/');
        });
    });

    describe('getUriParams()', function (): void {
        it('returns all URI params', function (): void {
            $globals = MockGlobalParamsForRouter::create('/users/id~123/posts/postId~45');
            $params = RouterUriParams::fromGlobals($globals);

            $uriParams = $params->getUriParams();

            expect($uriParams)->toBe(['id' => '123', 'postId' => '45']);
        });
    });

    describe('getUriParam()', function (): void {
        it('returns specific URI param', function (): void {
            $globals = MockGlobalParamsForRouter::create('/users/id~123');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getUriParam('id'))->toBe('123');
        });

        it('returns default value when param not found', function (): void {
            $globals = MockGlobalParamsForRouter::create('/users');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getUriParam('id', 'default'))->toBe('default');
        });

        it('returns null when param not found and no default', function (): void {
            $globals = MockGlobalParamsForRouter::create('/users');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getUriParam('id'))->toBeNull();
        });

        it('accepts integer key', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getUriParam(0, 'default'))->toBe('default');
        });
    });

    describe('getMethodName()', function (): void {
        it('returns main by default', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodName())->toBe('main');
        });

        it('returns custom method name', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~customMethod');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodName())->toBe('customMethod');
        });

        it('returns empty string when methodName is null', function (): void {
            $params = new RouterUriParams();

            expect($params->getMethodName())->toBe('');
        });
    });

    describe('getMethodParams()', function (): void {
        it('returns empty array when no method params', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodParams())->toBe([]);
        });

        it('returns method params', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~method/param1/param2');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodParams())->toBe(['param1', 'param2']);
        });
    });

    describe('getMethodParam()', function (): void {
        it('returns specific method param', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~method/param1/param2');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodParam(0))->toBe('param1');
            expect($params->getMethodParam(1))->toBe('param2');
        });

        it('returns default value when param not found', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test/~method');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodParam(0, 'default'))->toBe('default');
        });

        it('accepts string key', function (): void {
            $globals = MockGlobalParamsForRouter::create('/test');
            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getMethodParam('key', 'default'))->toBe('default');
        });
    });

    describe('Edge cases', function (): void {
        it('handles empty URI', function (): void {
            $globals = MockGlobalParamsForRouter::create('');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/');
        });

        it('handles root URI', function (): void {
            $globals = MockGlobalParamsForRouter::create('/');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/');
        });

        it('handles complex URI with multiple parts', function (): void {
            $globals = MockGlobalParamsForRouter::create('/api/v1/users/userId~5/posts/postId~10/~edit');

            $params = RouterUriParams::fromGlobals($globals);

            expect($params->getRouteVal())->toBe('/api/v1/users/{userId}/posts/{postId}');
            expect($params->getMethodName())->toBe('edit');
            expect($params->getUriParam('userId'))->toBe('5');
            expect($params->getUriParam('postId'))->toBe('10');
        });
    });

    describe('noPrefixPaths()', function (): void {
        afterEach(function (): void {
            // Static state is shared — reset prefix and no-prefix list so
            // unrelated specs don't pick up our configuration.
            RouterUriParams::setRoutePrefix('');
            $reflection = new ReflectionClass(RouterUriParams::class);
            $prop = $reflection->getProperty('noPrefixPaths');
            $prop->setValue(null, []);
        });

        it('returns true for paths matching a registered no-prefix root', function (): void {
            RouterUriParams::registerNoPrefixPath('/page');
            expect(RouterUriParams::isNoPrefixPath('/page'))->toBe(true);
            expect(RouterUriParams::isNoPrefixPath('/page/view~slug'))->toBe(true);
            expect(RouterUriParams::isNoPrefixPath('/page/'))->toBe(true);
        });

        it('returns false for paths that only share a prefix substring (e.g. /pages vs /page)', function (): void {
            RouterUriParams::registerNoPrefixPath('/page');
            expect(RouterUriParams::isNoPrefixPath('/pages'))->toBe(false);
            expect(RouterUriParams::isNoPrefixPath('/page-x'))->toBe(false);
        });

        it('ignores duplicate registrations', function (): void {
            RouterUriParams::registerNoPrefixPath('/page');
            RouterUriParams::registerNoPrefixPath('/page');
            expect(count(RouterUriParams::getNoPrefixPaths()))->toBe(1);
        });

        it('rejects "/" as a no-prefix path (would short-circuit everything)', function (): void {
            RouterUriParams::registerNoPrefixPath('/');
            expect(RouterUriParams::getNoPrefixPaths())->toBe([]);
        });

        it('still strips the system prefix from regular URIs', function (): void {
            RouterUriParams::setRoutePrefix('/system');
            RouterUriParams::registerNoPrefixPath('/page');

            $globals = MockGlobalParamsForRouter::create('/system/bookings');
            $params = RouterUriParams::fromGlobals($globals);
            expect($params->getRouteVal())->toBe('/bookings');
        });

        it('serves clean (un-prefixed) public URIs as the same routes', function (): void {
            RouterUriParams::setRoutePrefix('/system');
            RouterUriParams::registerNoPrefixPath('/page');

            // /page/view~home does not start with /system so the strip
            // branch never fires. The URI param "view" is captured into
            // routeVal /page/{view} with view=home — same shape the
            // /page/{view} controller sees in production.
            $globals = MockGlobalParamsForRouter::create('/page/view~home');
            $params = RouterUriParams::fromGlobals($globals);
            expect($params->getRouteVal())->toBe('/page/{view}');
            expect($params->getUriParam('view'))->toBe('home');
        });

        it('still accepts the legacy /system/page form for backward compatibility', function (): void {
            RouterUriParams::setRoutePrefix('/system');
            RouterUriParams::registerNoPrefixPath('/page');

            // Old links (emails, bookmarks) keep working: the system prefix
            // is stripped and the request lands on the same /page controller.
            $globals = MockGlobalParamsForRouter::create('/system/page/view~home');
            $params = RouterUriParams::fromGlobals($globals);
            expect($params->getRouteVal())->toBe('/page/{view}');
            expect($params->getUriParam('view'))->toBe('home');
        });
    });
});

class MockGlobalParamsForRouter implements IGlobalReqParams {
    protected string $uri;

    protected string $method = 'GET';

    public static function create(string $uri, string $method = 'GET'): self {
        $instance = new self();
        $instance->uri = $uri;
        $instance->method = $method;

        return $instance;
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function httpMethod(): string {
        return $this->method;
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
