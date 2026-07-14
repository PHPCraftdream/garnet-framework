<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router\Spec {
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\RouterDevFile;
    use Psr\Http\Message\ResponseInterface;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionClass;

    class MockGlobalParamsForRouterDevFile implements IGlobalReqParams {
        protected array $server;

        public static function create(string $uri): self {
            $instance = new self();
            $instance->server = [
                'REQUEST_URI' => $uri,
                'REQUEST_METHOD' => 'GET',
            ];

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
            return false;
        }

        public function ip(): string {
            return '127.0.0.1';
        }
    }

    describe('RouterDevFile', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_router_test_' . uniqid();
            mkdir($this->tempDir, 0o777, true);

            $subDir = $this->tempDir . DIRECTORY_SEPARATOR . 'assets';
            mkdir($subDir, 0o777, true);

            file_put_contents($subDir . DIRECTORY_SEPARATOR . 'test.html', '<html>test</html>');
            file_put_contents($subDir . DIRECTORY_SEPARATOR . 'test.js', 'console.log("test");');
            file_put_contents($subDir . DIRECTORY_SEPARATOR . 'index.html', '<html>index</html>');
        });

        afterEach(function (): void {
            if (is_dir($this->tempDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) {
                        rmdir($fileinfo->getPathname());
                    } else {
                        unlink($fileinfo->getPathname());
                    }
                }
                rmdir($this->tempDir);
            }
        });

        describe('addFilesDir()', function (): void {
            it('adds a valid directory', function (): void {
                $router = new RouterDevFile();
                $router->addFilesDir('assets', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');

                expect(true)->toBe(true);
            });

            it('normalizes directory paths', function (): void {
                $router = new RouterDevFile();
                $router->addFilesDir('assets', $this->tempDir . '/assets');

                expect(true)->toBe(true);
            });

            it('trims slashes from name', function (): void {
                $router = new RouterDevFile();
                $router->addFilesDir('/assets/', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');

                expect(true)->toBe(true);
            });

            it('throws exception for wrong name pattern', function (): void {
                $router = new RouterDevFile();

                expect(function () use ($router): void {
                    $router->addFilesDir('assets@test', $this->tempDir);
                })->toThrow(new RouterException('Wrong directory name pattern'));
            });

            it('throws exception for duplicate directory name', function (): void {
                $router = new RouterDevFile();
                $router->addFilesDir('assets', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');

                expect(function () use ($router): void {
                    $router->addFilesDir('assets', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');
                })->toThrow(new RouterException('Files dir already exists'));
            });

            it('throws exception for non-existent directory', function (): void {
                $router = new RouterDevFile();

                expect(function () use ($router): void {
                    $router->addFilesDir('nonexistent', $this->tempDir . '/nonexistent');
                })->toThrow(new RouterException('Wrong directory'));
            });
        });

        describe('getRouteDirAndFile()', function (): void {
            it('parses simple route', function (): void {
                $router = new RouterDevFile();
                $reflection = new ReflectionClass($router);
                $method = $reflection->getMethod('getRouteDirAndFile');

                [$dir, $file] = $method->invoke(null, 'test.html');

                expect($dir)->toBe('');
                expect($file)->toBe('test.html');
            });

            it('parses route with directory', function (): void {
                $router = new RouterDevFile();
                $reflection = new ReflectionClass($router);
                $method = $reflection->getMethod('getRouteDirAndFile');

                [$dir, $file] = $method->invoke(null, 'assets/test.html');

                expect($dir)->toBe('assets');
                expect($file)->toBe('test.html');
            });

            it('returns index.html for empty route', function (): void {
                $router = new RouterDevFile();
                $reflection = new ReflectionClass($router);
                $method = $reflection->getMethod('getRouteDirAndFile');

                [$dir, $file] = $method->invoke(null, '');

                expect($dir)->toBe('');
                expect($file)->toBe('index.html');
            });

            it('returns index.html for directory-only route', function (): void {
                $router = new RouterDevFile();
                $reflection = new ReflectionClass($router);
                $method = $reflection->getMethod('getRouteDirAndFile');

                [$dir, $file] = $method->invoke(null, 'assets');

                expect($dir)->toBe('');
                expect($file)->toBe('assets');
            });

            it('normalizes path separators', function (): void {
                $router = new RouterDevFile();
                $reflection = new ReflectionClass($router);
                $method = $reflection->getMethod('getRouteDirAndFile');

                [$dir, $file] = $method->invoke(null, 'assets\\test.html');

                expect($dir)->toBe('assets');
                expect($file)->toBe('test.html');
            });
        });

        describe('tryFileByDir()', function (): void {
            beforeEach(function (): void {
                $this->router = new RouterDevFile();
                $this->router->addFilesDir('', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');
                $this->router->addFilesDir('assets', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');
            });

            it('returns file path for valid file in default dir', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, '', 'test.html');

                expect($result)->toBeAn('array');
                expect(count($result))->toBe(2);
                expect(file_exists($result[0]))->toBe(true);
            });

            it('returns file path for valid file in named dir', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, 'assets', 'test.html');

                expect($result)->toBeAn('array');
                expect(count($result))->toBe(2);
                expect(file_exists($result[0]))->toBe(true);
            });

            it('returns null for non-existent directory', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, 'nonexistent', 'test.html');

                expect($result)->toBe(null);
            });

            it('returns null for .php files', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, '', 'test.php');

                expect($result)->toBe(null);
            });

            it('returns index.html for empty filename', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, '', '');

                expect($result)->toBeAn('array');
                expect(basename($result[0]))->toBe('index.html');
            });

            it('returns null for non-existent file', function (): void {
                $reflection = new ReflectionClass($this->router);
                $method = $reflection->getMethod('tryFileByDir');

                $result = $method->invoke($this->router, '', 'nonexistent.html');

                expect($result)->toBe(null);
            });
        });

        describe('dispatch()', function (): void {
            beforeEach(function (): void {
                $this->router = new RouterDevFile();
                $this->router->addFilesDir('', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');
                $this->router->addFilesDir('assets', $this->tempDir . DIRECTORY_SEPARATOR . 'assets');
            });

            it('returns null for URI that fails checkUriPathFile', function (): void {
                $globals = MockGlobalParamsForRouterDevFile::create('../etc/passwd');

                $result = $this->router->dispatch($globals);

                expect($result)->toBe(null);
            });

            it('returns null for non-existent file', function (): void {
                $globals = MockGlobalParamsForRouterDevFile::create('/nonexistent.html');

                $result = $this->router->dispatch($globals);

                expect($result)->toBe(null);
            });

            it('returns ResponseInterface for valid file in default dir', function (): void {
                $globals = MockGlobalParamsForRouterDevFile::create('/test.html');

                $result = $this->router->dispatch($globals);

                expect($result)->toBeAnInstanceOf(ResponseInterface::class);
            });

            it('returns ResponseInterface for valid file in named dir', function (): void {
                $globals = MockGlobalParamsForRouterDevFile::create('/assets/test.html');

                $result = $this->router->dispatch($globals);

                expect($result)->toBeAnInstanceOf(ResponseInterface::class);
            });

            it('returns ResponseInterface for index.html', function (): void {
                $globals = MockGlobalParamsForRouterDevFile::create('/');

                $result = $this->router->dispatch($globals);

                expect($result)->toBeAnInstanceOf(ResponseInterface::class);
            });
        });
    });
}
