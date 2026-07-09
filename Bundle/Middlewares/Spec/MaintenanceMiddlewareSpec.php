<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Middlewares\Spec {
    use PHPCraftdream\Garnet\Bundle\Middlewares\MaintenanceMiddleware;
    use ReflectionClass;

    describe('MaintenanceMiddleware', function (): void {
        describe('parseAllowedIps()', function (): void {
            beforeEach(function (): void {
                $ref = new ReflectionClass(MaintenanceMiddleware::class);
                $this->method = $ref->getMethod('parseAllowedIps');
                $this->method->setAccessible(true);
            });

            it('returns empty array for empty string', function (): void {
                $result = $this->method->invoke(null, '');
                expect($result)->toBe([]);
            });

            it('parses JSON with allowed_ips array', function (): void {
                $json = '{"enabled_at":"2024-01-01","allowed_ips":["1.2.3.4","5.6.7.8"]}';
                $result = $this->method->invoke(null, $json);
                expect($result)->toBe(['1.2.3.4', '5.6.7.8']);
            });

            it('parses JSON with only admin_ip', function (): void {
                $json = '{"enabled_at":"2024-01-01","admin_ip":"10.0.0.1"}';
                $result = $this->method->invoke(null, $json);
                expect($result)->toBe(['10.0.0.1']);
            });

            it('parses legacy one-IP-per-line format', function (): void {
                $content = "1.2.3.4\n5.6.7.8\n";
                $result = $this->method->invoke(null, $content);
                expect($result)->toBe(['1.2.3.4', '5.6.7.8']);
            });

            it('skips blank lines in legacy format', function (): void {
                $content = "1.2.3.4\n\n\n5.6.7.8\n";
                $result = $this->method->invoke(null, $content);
                expect($result)->toBe(['1.2.3.4', '5.6.7.8']);
            });

            it('falls through to legacy parsing for JSON with no ip fields', function (): void {
                $json = '{"enabled_at":"2024-01-01"}';
                $result = $this->method->invoke(null, $json);
                // No allowed_ips or admin_ip -> falls to legacy line-by-line, treats whole string as one "IP"
                expect($result)->toBe([$json]);
            });

            it('handles single IP without newline', function (): void {
                $result = $this->method->invoke(null, '192.168.1.1');
                expect($result)->toBe(['192.168.1.1']);
            });
        });

        describe('getClientIp()', function (): void {
            beforeEach(function (): void {
                $ref = new ReflectionClass(MaintenanceMiddleware::class);
                $this->method = $ref->getMethod('getClientIp');
                $this->method->setAccessible(true);
            });

            it('returns REMOTE_ADDR when no X-Forwarded-For', function (): void {
                $globals = new class implements \PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams {
                    public function readServerValue(string $name, mixed $default = null): ?string { return null; }
                    public function readServerAll(): array { return ['REMOTE_ADDR' => '10.0.0.5']; }
                    public function readGetValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readGetAll(): array { return []; }
                    public function readPostValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readPostAll(): array { return []; }
                    public function readCookieValue(string $name, mixed $default = null): ?string { return null; }
                    public function readCookieAll(): array { return []; }
                    public function readFilesValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readFilesAll(): array { return []; }
                    public function getUri(): string { return '/'; }
                    public function httpMethod(): string { return 'GET'; }
                    public function isPost(): bool { return false; }
                    public function isEmptyPost(): bool { return false; }
                    public function isGet(): bool { return true; }
                    public function isLocalhost(): bool { return false; }
                    public function isPhpServer(): bool { return false; }
                    public function isDev(): bool { return false; }
                    public function ip(): string { return '10.0.0.5'; }
                };

                $result = $this->method->invoke(null, $globals);
                expect($result)->toBe('10.0.0.5');
            });

            it('returns first IP from X-Forwarded-For when present', function (): void {
                $globals = new class implements \PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams {
                    public function readServerValue(string $name, mixed $default = null): ?string { return null; }
                    public function readServerAll(): array {
                        return [
                            'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 70.41.3.18, 150.172.238.178',
                            'REMOTE_ADDR' => '127.0.0.1',
                        ];
                    }
                    public function readGetValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readGetAll(): array { return []; }
                    public function readPostValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readPostAll(): array { return []; }
                    public function readCookieValue(string $name, mixed $default = null): ?string { return null; }
                    public function readCookieAll(): array { return []; }
                    public function readFilesValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readFilesAll(): array { return []; }
                    public function getUri(): string { return '/'; }
                    public function httpMethod(): string { return 'GET'; }
                    public function isPost(): bool { return false; }
                    public function isEmptyPost(): bool { return false; }
                    public function isGet(): bool { return true; }
                    public function isLocalhost(): bool { return false; }
                    public function isPhpServer(): bool { return false; }
                    public function isDev(): bool { return false; }
                    public function ip(): string { return '127.0.0.1'; }
                };

                $result = $this->method->invoke(null, $globals);
                expect($result)->toBe('203.0.113.50');
            });

            it('returns empty string when neither header is present', function (): void {
                $globals = new class implements \PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams {
                    public function readServerValue(string $name, mixed $default = null): ?string { return null; }
                    public function readServerAll(): array { return []; }
                    public function readGetValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readGetAll(): array { return []; }
                    public function readPostValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readPostAll(): array { return []; }
                    public function readCookieValue(string $name, mixed $default = null): ?string { return null; }
                    public function readCookieAll(): array { return []; }
                    public function readFilesValue(string $name, mixed $default = null): mixed { return $default; }
                    public function readFilesAll(): array { return []; }
                    public function getUri(): string { return '/'; }
                    public function httpMethod(): string { return 'GET'; }
                    public function isPost(): bool { return false; }
                    public function isEmptyPost(): bool { return false; }
                    public function isGet(): bool { return true; }
                    public function isLocalhost(): bool { return false; }
                    public function isPhpServer(): bool { return false; }
                    public function isDev(): bool { return false; }
                    public function ip(): string { return ''; }
                };

                $result = $this->method->invoke(null, $globals);
                expect($result)->toBe('');
            });
        });
    });
}
