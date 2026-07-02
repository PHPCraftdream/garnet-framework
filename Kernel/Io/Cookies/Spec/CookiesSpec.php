<?php declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookie;
use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookies;

describe('Cookies', function (): void {
    describe('constructor', function (): void {
        it('creates empty collection and collection with initial cookies', function (): void {
            $cookies = new Cookies();
            expect($cookies->getAll())->toBe([]);

            $cookie1 = new Cookie('name1', 'value1');
            $cookie2 = new Cookie('name2', 'value2');
            $cookies = new Cookies([$cookie1, $cookie2]);
            expect(count($cookies->getAll()))->toBe(2);
        });
    });

    describe('has()', function (): void {
        it('returns true for existing and false for non-existent cookies', function (): void {
            $cookie = new Cookie('test', 'value');
            $cookies = new Cookies([$cookie]);
            expect($cookies->has('test'))->toBe(true);
            expect($cookies->has('missing'))->toBe(false);
        });
    });

    describe('get()', function (): void {
        it('returns existing cookie', function (): void {
            $cookie = new Cookie('test', 'value');
            $cookies = new Cookies([$cookie]);
            $retrieved = $cookies->get('test');
            expect($retrieved->getName())->toBe('test');
        });

        it('creates new cookie if not exists', function (): void {
            $cookies = new Cookies();
            $newCookie = $cookies->get('new');
            expect($newCookie->getName())->toBe('new');
            expect($newCookie->isNew())->toBe(true);
        });

        it('adds new cookie to collection', function (): void {
            $cookies = new Cookies();
            $cookies->get('new');
            expect($cookies->has('new'))->toBe(true);
        });
    });

    describe('getAll()', function (): void {
        it('returns all cookies as array', function (): void {
            $cookie1 = new Cookie('a', '1');
            $cookie2 = new Cookie('b', '2');
            $cookies = new Cookies([$cookie1, $cookie2]);
            $all = $cookies->getAll();
            expect(count($all))->toBe(2);
        });
    });

    describe('add()', function (): void {
        it('adds cookie to collection', function (): void {
            $cookies = new Cookies();
            $cookie = new Cookie('test', 'value');
            $cookies->add($cookie);
            expect($cookies->has('test'))->toBe(true);
        });

        it('overwrites existing cookie with same name', function (): void {
            $cookie1 = new Cookie('test', 'value1');
            $cookie2 = new Cookie('test', 'value2');
            $cookies = new Cookies([$cookie1]);
            $cookies->add($cookie2);
            expect($cookies->get('test')->getValue())->toBe('value2');
        });
    });

    describe('delete()', function (): void {
        it('removes cookie from collection', function (): void {
            $cookie = new Cookie('test', 'value');
            $cookies = new Cookies([$cookie]);
            $cookies->delete('test');
            expect($cookies->has('test'))->toBe(false);
        });

        it('does nothing when cookie not found', function (): void {
            $cookies = new Cookies();
            $cookies->delete('missing');
            expect($cookies->has('missing'))->toBe(false);
        });
    });

    describe('setItNew()', function (): void {
        it('marks all cookies as new', function (): void {
            $cookie1 = new Cookie('a', '1');
            $cookie2 = new Cookie('b', '2');
            $cookie1->setOld()->startObserveChanges();
            $cookie2->setOld()->startObserveChanges();

            $cookies = new Cookies([$cookie1, $cookie2]);
            $cookies->setItNew();

            $all = $cookies->getAll();
            expect($all[0]->isNew())->toBe(true);
            expect($all[1]->isNew())->toBe(true);
        });
    });

    describe('fromServer()', function (): void {
        it('parses cookies from server array and handles edge cases', function (): void {
            $cookies = new Cookies();
            $cookies->fromServer(['HTTP_COOKIE' => 'name1=value1; name2=value2']);
            expect($cookies->has('name1'))->toBe(true);
            expect($cookies->has('name2'))->toBe(true);
            expect($cookies->get('name1')->getValue())->toBe('value1');

            $cookies = new Cookies();
            $cookies->fromServer(['HTTP_COOKIE' => '']);
            expect(count($cookies->getAll()))->toBe(0);

            $cookies = new Cookies();
            $cookies->fromServer([]);
            expect(count($cookies->getAll()))->toBe(0);
        });
    });

    describe('fromGlobals()', function (): void {
        it('parses cookies from IGlobalReqParams', function (): void {
            $globals = new class ([] , [], [], [], []) implements IGlobalReqParams {
                public function readServerValue(string $name, mixed $default = null): string|null {
                    return $name === 'HTTP_COOKIE' ? 'name=value' : $default;
                }

                // Other required methods
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

                public function readCookieValue(string $name, mixed $default = null): string|null {
                    return $default;
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

                public function getUri(): string {
                    return '/';
                }

                public function httpMethod(): string {
                    return 'GET';
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
            };

            $cookies = new Cookies();
            $cookies->fromGlobals($globals);
            expect($cookies->has('name'))->toBe(true);
            expect($cookies->get('name')->getValue())->toBe('value');
        });
    });

    describe('fromCookieStrings()', function (): void {
        it('parses array of cookie strings', function (): void {
            $cookies = new Cookies();
            $cookies->fromCookieStrings([
                'name1=value1; Path=/',
                'name2=value2; Secure',
            ]);
            expect($cookies->has('name1'))->toBe(true);
            expect($cookies->has('name2'))->toBe(true);
        });

        it('replaces existing cookies', function (): void {
            $cookie = new Cookie('old', 'value');
            $cookies = new Cookies([$cookie]);
            $cookies->fromCookieStrings(['new=newval']);
            expect($cookies->has('old'))->toBe(false);
            expect($cookies->has('new'))->toBe(true);
        });
    });

    describe('toResponse()', function (): void {
        it('adds Set-Cookie headers and handles edge cases', function (): void {
            $cookie1 = new Cookie('name1', 'value1');
            $cookie2 = new Cookie('name2', 'value2');
            $cookies = new Cookies([$cookie1, $cookie2]);

            $response = new Response();
            $newResponse = $cookies->toResponse($response);
            expect(count($newResponse->getHeader('Set-Cookie')))->toBe(2);

            // Removes existing Set-Cookie headers
            $cookie = new Cookie('new', 'value');
            $cookies = new Cookies([$cookie]);
            $response = new Response(200, ['Set-Cookie' => 'old=value']);
            $newResponse = $cookies->toResponse($response);
            $headers = $newResponse->getHeader('Set-Cookie');
            expect(count($headers))->toBe(1);
            expect($headers[0])->toContain('new=value');

            // Skips empty cookie strings
            $cookie1 = new Cookie('has_value', 'val');
            $cookie2 = new Cookie();
            $cookies = new Cookies([$cookie1, $cookie2]);
            $response = new Response();
            $newResponse = $cookies->toResponse($response);
            expect(count($newResponse->getHeader('Set-Cookie')))->toBe(1);
        });
    });

    describe('fromResponse()', function (): void {
        it('parses cookies from response headers', function (): void {
            $response = new Response(200, ['Set-Cookie' => ['name1=value1', 'name2=value2']]);
            $cookies = new Cookies();
            $cookies->fromResponse($response);

            expect($cookies->has('name1'))->toBe(true);
            expect($cookies->has('name2'))->toBe(true);
        });
    });
});
