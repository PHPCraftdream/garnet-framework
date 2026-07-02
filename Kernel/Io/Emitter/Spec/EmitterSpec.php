<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Emitter\Spec {
    use GuzzleHttp\Psr7\Response;
    use PHPCraftdream\Garnet\Kernel\Io\Emitter\Emitter;

    class EmitterSpec extends Emitter {
        public static array $testRunHeaders = [];

        protected static function header(string $header, bool $replace = true, int $response_code = 0): void {
            static::$testRunHeaders[] = [$header, $replace, $response_code];
        }

        public static function resetHeaders(): void {
            static::$testRunHeaders = [];
        }
    }

    describe('Emitter', function (): void {
        describe('::emit()', function (): void {
            beforeEach(function (): void {
                EmitterSpec::resetHeaders();
            });

            it('emits response with headers and body', function (): void {
                ob_start();
                $response = new Response(200, ['X-Test-Header' => 'test-value'], 'Hállö, wörld hí!');
                EmitterSpec::emit($response);

                expect(EmitterSpec::$testRunHeaders)->toContain(['X-Test-Header: test-value', true, 0]);
                expect(EmitterSpec::$testRunHeaders)->toContain(['HTTP/1.1 200 OK', true, 0]);
                expect(EmitterSpec::$testRunHeaders)->toContain(['X-Powered-By: Application', true, 0]);
                expect(EmitterSpec::$testRunHeaders)->toContain(['Content-Length: 20', true, 0]);
                expect(EmitterSpec::$testRunHeaders)->toContain(['Content-Encoding: none', true, 0]);
                expect(ob_get_clean())->toBe('Hállö, wörld hí!');
            });

            it('emits different HTTP status codes', function (): void {
                // 404 Not Found
                ob_start();
                $response = new Response(404, [], 'Not Found');
                EmitterSpec::emit($response);
                expect(EmitterSpec::$testRunHeaders)->toContain(['HTTP/1.1 404 Not Found', true, 0]);
                expect(ob_get_clean())->toBe('Not Found');

                EmitterSpec::resetHeaders();

                // 500 Internal Server Error
                ob_start();
                $response = new Response(500, [], 'Internal Server Error');
                EmitterSpec::emit($response);
                expect(EmitterSpec::$testRunHeaders)->toContain(['HTTP/1.1 500 Internal Server Error', true, 0]);
                expect(ob_get_clean())->toBe('Internal Server Error');
            });

            it('handles empty body', function (): void {
                ob_start();
                $response = new Response(204, [], '');
                EmitterSpec::emit($response);

                expect(EmitterSpec::$testRunHeaders)->toContain(['Content-Length: 0', true, 0]);
                expect(ob_get_clean())->toBe('');
            });

            it('emits multiple headers with same name', function (): void {
                ob_start();
                $response = new Response(200, ['Set-Cookie' => ['name=value', 'name2=value2']], '');
                EmitterSpec::emit($response);

                $cookieHeaders = array_values(array_filter(
                    EmitterSpec::$testRunHeaders,
                    fn ($h) => str_starts_with($h[0], 'Set-Cookie:')
                ));

                // Both values must be present.
                expect(count($cookieHeaders))->toBe(2);
                expect($cookieHeaders[0][0])->toBe('Set-Cookie: name=value');
                expect($cookieHeaders[1][0])->toBe('Set-Cookie: name2=value2');

                // Critical: the second call to header() MUST pass
                // $replace=false, otherwise it clobbers the first
                // Set-Cookie and only one cookie reaches the client —
                // a bug we hit in prod when shipping two cookies
                // (session + CSRF_TOKEN) in a single response.
                expect($cookieHeaders[0][1])->toBe(true);
                expect($cookieHeaders[1][1])->toBe(false);

                ob_get_clean();
            });

            it('handles JSON response', function (): void {
                ob_start();
                $jsonData = json_encode(['key' => 'value', 'number' => 42]);
                $response = new Response(200, ['Content-Type' => 'application/json'], $jsonData);
                EmitterSpec::emit($response);

                expect(EmitterSpec::$testRunHeaders)->toContain(['Content-Type: application/json', true, 0]);
                expect(ob_get_clean())->toBe($jsonData);
            });

            it('calculates content length for multibyte characters', function (): void {
                ob_start();
                $body = 'Γειά σου κόσμε! 你好世界! مرحبا!';
                $response = new Response(200, [], $body);
                EmitterSpec::emit($response);

                $contentLengthHeader = array_filter(
                    EmitterSpec::$testRunHeaders,
                    fn ($h) => str_starts_with($h[0], 'Content-Length:')
                );

                expect(count($contentLengthHeader))->toBe(1);
                ob_get_clean();
            });

            it('emits with HTTP/1.0 protocol version', function (): void {
                ob_start();
                $body = new \GuzzleHttp\Psr7\Stream(fopen('php://memory', 'r+'));
                $body->write('test');
                $body->rewind();
                $response = new Response(200, [], $body, '1.0');
                EmitterSpec::emit($response);

                expect(EmitterSpec::$testRunHeaders)->toContain(['HTTP/1.0 200 OK', true, 0]);
                ob_get_clean();
            });

            it('returns true on successful emit', function (): void {
                ob_start();
                $response = new Response(200, [], 'body');
                $result = EmitterSpec::emit($response);

                expect($result)->toBe(true);
                ob_get_clean();
            });
        });
    });
}
