<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L0_Core\Router\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;

    describe('UriParams', function (): void {
        describe('fromGlobals()', function (): void {
            it('should create UriParams object from valid URI', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getRouteVal())->toBe('/');

                // ----------------------------------------------------------

                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/lang~en/user-id~123'));

                expect($result->getRouteVal())->toBe('/{lang}/{user-id}');
                expect($result->getUriParams())->toBe(['lang' => 'en', 'user-id' => '123']);

                // ----------------------------------------------------------
                $str = '/lang~en?param=abc';
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests($str));

                expect($result->getRouteVal())->toBe('/{lang}');
                expect($result->getUriParams())->toBe(['lang' => 'en']);

                // ----------------------------------------------------------
                $str = '/lang~en/~update/10/20/q=hide/page=1';
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests($str));

                expect($result->getRouteVal())->toBe('/{lang}');
                expect($result->getUriParams())->toBe(['lang' => 'en']);
                expect($result->getMethodName())->toBe('update');
                expect($result->getMethodParams())->toBe(['10', '20', 'q=hide', 'page=1']);

                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/../etc/passwd'));
                expect($result->getRouteVal())->toEqual('/../etc/passwd');
            });

            it('throws an exception if the URI contains direct call to main', function (): void {
                expect(function (): void {
                    RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/~main'));
                })->toThrow(new RouterException('DIRECT_CALL_MAIN_DISABLED'));
            });

            it('handles empty URI', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests(''));

                expect($result->getRouteVal())->toBe('/');
                expect($result->getMethodName())->toBe('main');
            });

            it('handles URI without parameters', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/path/to/page'));

                expect($result->getRouteVal())->toBe('/path/to/page');
                expect($result->getUriParams())->toBe([]);
                expect($result->getMethodName())->toBe('main');
            });

            it('handles single parameter', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/id~123'));

                expect($result->getRouteVal())->toBe('/{id}');
                expect($result->getUriParams())->toBe(['id' => '123']);
            });

            it('handles parameter with special characters', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/email~test@example.com'));

                expect($result->getRouteVal())->toBe('/{email}');
                expect($result->getUriParams())->toBe(['email' => 'test@example.com']);
            });

            it('handles multiple consecutive parameters', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/a~1/b~2/c~3'));

                expect($result->getRouteVal())->toBe('/{a}/{b}/{c}');
                expect($result->getUriParams())->toBe(['a' => '1', 'b' => '2', 'c' => '3']);
            });

            it('handles mixed parameters and static path', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/api/user~john/posts'));

                expect($result->getRouteVal())->toBe('/api/{user}/posts');
                expect($result->getUriParams())->toBe(['user' => 'john']);
            });

            it('extracts query string correctly', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/path~val?query=1&other=2'));

                expect($result->getRouteVal())->toBe('/{path}');
                expect($result->getUriParams())->toBe(['path' => 'val']);
            });

            it('handles URI with leading/trailing slashes', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('///path///'));

                expect($result->getRouteVal())->toBe('/path');
            });
        });

        describe('getHttpMethod()', function (): void {
            it('returns GET by default', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getHttpMethod())->toBe('GET');
            });

            it('returns POST for POST requests', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makePost4Tests('/'));

                expect($result->getHttpMethod())->toBe('POST');
            });
        });

        describe('getUriParam()', function (): void {
            it('returns parameter value when exists', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/id~123/name~test'));

                expect($result->getUriParam('id'))->toBe('123');
                expect($result->getUriParam('name'))->toBe('test');
            });

            it('returns default value when parameter does not exist', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getUriParam('missing', 'default'))->toBe('default');
            });

            it('returns null when parameter does not exist and no default provided', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getUriParam('missing'))->toBe(null);
            });
        });

        describe('getMethodParam()', function (): void {
            it('returns method param value when exists', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/~method/param1/param2'));

                expect($result->getMethodParam(0))->toBe('param1');
                expect($result->getMethodParam(1))->toBe('param2');
            });

            it('returns default value when param does not exist', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getMethodParam(0, 'default'))->toBe('default');
            });

            it('returns null when param does not exist and no default provided', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getMethodParam(99))->toBe(null);
            });
        });

        describe('getMethodName()', function (): void {
            it('returns "main" by default', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getMethodName())->toBe('main');
            });

            it('returns custom method name when specified', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/~customMethod'));

                expect($result->getMethodName())->toBe('customMethod');
            });

            it('returns empty string when methodName is null', function (): void {
                $result = RouterUriParams::makeClear('GET');

                expect($result->getMethodName())->toBe('main');
            });
        });

        describe('makeClear()', function (): void {
            it('creates UriParams with default values', function (): void {
                $result = RouterUriParams::makeClear('GET');

                expect($result->getRouteVal())->toBe('/');
                expect($result->getMethodName())->toBe('main');
                expect($result->getHttpMethod())->toBe('GET');
            });

            it('creates UriParams with POST method', function (): void {
                $result = RouterUriParams::makeClear('POST');

                expect($result->getHttpMethod())->toBe('POST');
            });
        });

        describe('getMethodParams()', function (): void {
            it('returns empty array when no method params', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/'));

                expect($result->getMethodParams())->toBe([]);
            });

            it('returns array of method params', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/~method/a/b/c'));

                expect($result->getMethodParams())->toBe(['a', 'b', 'c']);
            });

            it('handles empty method params', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/~method/'));

                expect($result->getMethodParams())->toBe([]);
            });
        });

        describe('getUriParams()', function (): void {
            it('returns empty array when no URI params', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/path/to/page'));

                expect($result->getUriParams())->toBe([]);
            });

            it('returns all URI parameters', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/a~1/b~2/c~3'));

                expect($result->getUriParams())->toBe(['a' => '1', 'b' => '2', 'c' => '3']);
            });
        });

        describe('complex scenarios', function (): void {
            it('handles parameters with method call', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/lang~en/~update/10'));

                expect($result->getRouteVal())->toBe('/{lang}');
                expect($result->getUriParams())->toBe(['lang' => 'en']);
                expect($result->getMethodName())->toBe('update');
                expect($result->getMethodParams())->toBe(['10']);
            });

            it('handles numeric parameter names', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/0~value/1~another'));

                expect($result->getUriParam(0))->toBe('value');
                expect($result->getUriParam(1))->toBe('another');
            });

            it('preserves parameter order', function (): void {
                $result = RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('/first~1/second~2/third~3'));

                $keys = array_keys($result->getUriParams());
                expect($keys)->toBe(['first', 'second', 'third']);
            });
        });
    });
}
