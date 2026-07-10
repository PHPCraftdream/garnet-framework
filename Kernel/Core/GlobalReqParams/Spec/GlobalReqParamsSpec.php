<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;

describe('GlobalReqParams', function (): void {
    describe('from()', function (): void {
        it('stores server values', function (): void {
            $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'];
            $params = GlobalReqParams::from($server, [], [], [], []);
            expect($params->readServerAll())->toBe($server);
        });

        it('stores get values', function (): void {
            $get = ['param1' => 'value1'];
            $params = GlobalReqParams::from([], $get, [], [], []);
            expect($params->readGetAll())->toBe($get);
        });

        it('stores post values', function (): void {
            $post = ['key' => 'val'];
            $params = GlobalReqParams::from([], [], $post, [], []);
            expect($params->readPostAll())->toBe($post);
        });

        it('stores cookie values', function (): void {
            $cookie = ['session' => 'abc123'];
            $params = GlobalReqParams::from([], [], [], $cookie, []);
            expect($params->readCookieAll())->toBe($cookie);
        });

        it('stores files values', function (): void {
            $files = ['upload' => ['name' => 'test.txt']];
            $params = GlobalReqParams::from([], [], [], [], $files);
            expect($params->readFilesAll())->toBe($files);
        });
    });

    describe('readServerValue()', function (): void {
        it('returns server value by key', function (): void {
            $params = GlobalReqParams::from(['HTTP_HOST' => 'example.com'], [], [], [], []);
            expect($params->readServerValue('HTTP_HOST'))->toBe('example.com');
        });

        it('returns default when key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readServerValue('NOT_EXISTS', 'default'))->toBe('default');
        });

        it('returns null when no default and key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readServerValue('NOT_EXISTS'))->toBeNull();
        });
    });

    describe('readGetValue()', function (): void {
        it('returns get value by key', function (): void {
            $params = GlobalReqParams::from([], ['id' => '123'], [], [], []);
            expect($params->readGetValue('id'))->toBe('123');
        });

        it('returns default when key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readGetValue('missing', 'def'))->toBe('def');
        });
    });

    describe('readPostValue()', function (): void {
        it('returns post value by key', function (): void {
            $params = GlobalReqParams::from([], [], ['username' => 'john'], [], []);
            expect($params->readPostValue('username'))->toBe('john');
        });

        it('returns default when key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readPostValue('missing', 'def'))->toBe('def');
        });
    });

    describe('readCookieValue()', function (): void {
        it('returns cookie value by key', function (): void {
            $params = GlobalReqParams::from([], [], [], ['token' => 'xyz'], []);
            expect($params->readCookieValue('token'))->toBe('xyz');
        });

        it('returns default when key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readCookieValue('missing', 'def'))->toBe('def');
        });
    });

    describe('readFilesValue()', function (): void {
        it('returns files value by key', function (): void {
            $file = ['name' => 'test.jpg', 'type' => 'image/jpeg'];
            $params = GlobalReqParams::from([], [], [], [], ['upload' => $file]);
            expect($params->readFilesValue('upload'))->toBe($file);
        });

        it('returns default when key not found', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->readFilesValue('missing', 'def'))->toBe('def');
        });
    });

    describe('getUri()', function (): void {
        it('returns REQUEST_URI from server', function (): void {
            $params = GlobalReqParams::from(['REQUEST_URI' => '/path/to/page'], [], [], [], []);
            expect($params->getUri())->toBe('/path/to/page');
        });

        it('returns slash when REQUEST_URI not set', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->getUri())->toBe('/');
        });
    });

    describe('httpMethod()', function (): void {
        it('returns REQUEST_METHOD', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'POST'], [], [], [], []);
            expect($params->httpMethod())->toBe('POST');
        });

        it('returns GET when not set', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->httpMethod())->toBe('GET');
        });

        it('prioritizes HTTP_X_HTTP_METHOD', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD' => 'PUT'], [], [], [], []);
            expect($params->httpMethod())->toBe('PUT');
        });
    });

    describe('isPost()', function (): void {
        it('returns true for POST method', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'POST'], [], [], [], []);
            expect($params->isPost())->toBe(true);
        });

        it('returns true for lowercase post', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'post'], [], [], [], []);
            expect($params->isPost())->toBe(true);
        });

        it('returns false for GET method', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'GET'], [], [], [], []);
            expect($params->isPost())->toBe(false);
        });
    });

    describe('isGet()', function (): void {
        it('returns true for GET method', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'GET'], [], [], [], []);
            expect($params->isGet())->toBe(true);
        });

        it('returns true for lowercase get', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'get'], [], [], [], []);
            expect($params->isGet())->toBe(true);
        });

        it('returns false for POST method', function (): void {
            $params = GlobalReqParams::from(['REQUEST_METHOD' => 'POST'], [], [], [], []);
            expect($params->isGet())->toBe(false);
        });
    });

    describe('isEmptyPost()', function (): void {
        it('returns true when post array is empty', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->isEmptyPost())->toBe(true);
        });

        it('returns false when post has values', function (): void {
            $params = GlobalReqParams::from([], [], ['key' => 'val'], [], []);
            expect($params->isEmptyPost())->toBe(false);
        });
    });

    describe('isLocalhost()', function (): void {
        it('returns true for localhost', function (): void {
            $params = GlobalReqParams::from(['SERVER_NAME' => 'localhost'], [], [], [], []);
            expect($params->isLocalhost())->toBe(true);
        });

        it('returns true for 127.0.0.1', function (): void {
            $params = GlobalReqParams::from(['SERVER_NAME' => '127.0.0.1'], [], [], [], []);
            expect($params->isLocalhost())->toBe(true);
        });

        it('returns true for 0.0.0.0', function (): void {
            $params = GlobalReqParams::from(['SERVER_NAME' => '0.0.0.0'], [], [], [], []);
            expect($params->isLocalhost())->toBe(true);
        });

        it('returns false for other domains', function (): void {
            $params = GlobalReqParams::from(['SERVER_NAME' => 'example.com'], [], [], [], []);
            expect($params->isLocalhost())->toBe(false);
        });

        it('returns false when SERVER_NAME not set', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->isLocalhost())->toBe(false);
        });
    });

    describe('isPhpServer()', function (): void {
        it('returns true for PHP built-in server', function (): void {
            $params = GlobalReqParams::from(['SERVER_SOFTWARE' => 'PHP 8.2.0 Development Server'], [], [], [], []);
            expect($params->isPhpServer())->toBe(true);
        });

        it('returns true for the real PHP built-in server SERVER_SOFTWARE format', function (): void {
            // The actual value PHP's built-in server sets (verified against a
            // real `php -S` process), slash-separated rather than space-separated.
            $params = GlobalReqParams::from(['SERVER_SOFTWARE' => 'PHP/8.3.32 (Development Server)'], [], [], [], []);
            expect($params->isPhpServer())->toBe(true);
        });

        it('returns false for apache', function (): void {
            $params = GlobalReqParams::from(['SERVER_SOFTWARE' => 'Apache/2.4.41'], [], [], [], []);
            expect($params->isPhpServer())->toBe(false);
        });

        it('returns false when SERVER_SOFTWARE not set', function (): void {
            $params = GlobalReqParams::from([], [], [], [], []);
            expect($params->isPhpServer())->toBe(false);
        });
    });

    describe('isDev()', function (): void {
        it('returns true when localhost and PHP server', function (): void {
            $params = GlobalReqParams::from([
                'SERVER_NAME' => 'localhost',
                'SERVER_SOFTWARE' => 'PHP 8.2.0 Development Server',
            ], [], [], [], []);
            expect($params->isDev())->toBe(true);
        });

        it('returns false when not localhost', function (): void {
            $params = GlobalReqParams::from([
                'SERVER_NAME' => 'example.com',
                'SERVER_SOFTWARE' => 'PHP 8.2.0 Development Server',
            ], [], [], [], []);
            expect($params->isDev())->toBe(false);
        });

        it('returns false when not PHP server', function (): void {
            $params = GlobalReqParams::from([
                'SERVER_NAME' => 'localhost',
                'SERVER_SOFTWARE' => 'Apache/2.4.41',
            ], [], [], [], []);
            expect($params->isDev())->toBe(false);
        });
    });

    describe('ip()', function (): void {
        it('returns REMOTE_ADDR', function (): void {
            $params = GlobalReqParams::from(['REMOTE_ADDR' => '192.168.1.100'], [], [], [], []);
            expect($params->ip())->toBe('192.168.1.100');
        });

        it('returns HTTP_X_FORWARDED_FOR when set', function (): void {
            $params = GlobalReqParams::from([
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            ], [], [], [], []);
            expect($params->ip())->toBe('203.0.113.1');
        });

        it('returns first IP from comma-separated X_FORWARDED_FOR', function (): void {
            $params = GlobalReqParams::from([
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 198.51.100.1',
            ], [], [], [], []);
            expect($params->ip())->toBe('203.0.113.1');
        });

        it('trims whitespace from first IP', function (): void {
            $params = GlobalReqParams::from([
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_X_FORWARDED_FOR' => '  203.0.113.1  , 198.51.100.1',
            ], [], [], [], []);
            expect($params->ip())->toBe('203.0.113.1');
        });
    });

    describe('makeGet4Tests()', function (): void {
        it('creates params for GET request', function (): void {
            $params = GlobalReqParams::makeGet4Tests('/test/path');
            expect($params->httpMethod())->toBe('GET');
            expect($params->getUri())->toBe('/test/path');
        });
    });

    describe('makePost4Tests()', function (): void {
        it('creates params for POST request', function (): void {
            $params = GlobalReqParams::makePost4Tests('/submit');
            expect($params->httpMethod())->toBe('POST');
            expect($params->getUri())->toBe('/submit');
        });
    });
});
