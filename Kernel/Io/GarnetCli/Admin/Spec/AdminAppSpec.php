<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function file_exists;
    use function in_array;
    use function is_dir;
    use function mkdir;
    use function ob_get_clean;
    use function ob_start;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminApp;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminAuth;
    use ReflectionClass;
    use ReflectionMethod;

    use function rmdir;
    use function sys_get_temp_dir;
    use function uniqid;
    use function unlink;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 6));
    }

    describe('AdminApp', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_aapp_' . uniqid();
            mkdir($this->tempDir, 0o777, true);
            $this->prevRoot = $_ENV['GARNET_ROOT'] ?? null;
            $_ENV['GARNET_ROOT'] = $this->tempDir;

            // Wipe cookie state per spec
            $this->prevCookie = $_COOKIE['garnet_admin'] ?? null;
            unset($_COOKIE['garnet_admin']);
        });

        afterEach(function (): void {
            if ($this->prevRoot === null) {
                unset($_ENV['GARNET_ROOT']);
            } else {
                $_ENV['GARNET_ROOT'] = $this->prevRoot;
            }

            if ($this->prevCookie === null) {
                unset($_COOKIE['garnet_admin']);
            } else {
                $_COOKIE['garnet_admin'] = $this->prevCookie;
            }

            $tokenFile = $this->tempDir . DIRECTORY_SEPARATOR . '.garnet_admin';

            if (file_exists($tokenFile)) {
                unlink($tokenFile);
            }

            if (is_dir($this->tempDir)) {
                rmdir($this->tempDir);
            }
        });

        describe('::ALLOWED_COMMANDS', function (): void {
            it('whitelists exactly four CLI commands (no shell injection vector)', function (): void {
                $reflection = new ReflectionClass(AdminApp::class);
                $allowed = $reflection->getReflectionConstant('ALLOWED_COMMANDS')->getValue();

                expect($allowed)->toBe(['build', 'build:watch', 'prepare', 'migration']);
            });

            it('does NOT include destructive commands like deploy / db:wipe', function (): void {
                $reflection = new ReflectionClass(AdminApp::class);
                $allowed = $reflection->getReflectionConstant('ALLOWED_COMMANDS')->getValue();

                foreach (['deploy', 'deploy:diff', 'db:wipe', 'ssh', 'bundle', 'uninstall'] as $bad) {
                    expect(in_array($bad, $allowed, true))->toBe(false);
                }
            });
        });

        describe('::isAuthenticated (private — via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(AdminApp::class, 'isAuthenticated');
                $this->fn->setAccessible(true);
            });

            it('returns false when no cookie is set', function (): void {
                expect($this->fn->invoke(null))->toBe(false);
            });

            it('returns false when cookie is empty string', function (): void {
                $_COOKIE['garnet_admin'] = '';
                expect($this->fn->invoke(null))->toBe(false);
            });

            it('returns false when cookie does not match an active token', function (): void {
                AdminAuth::saveToken('correct-token');
                AdminAuth::activateToken('correct-token');

                $_COOKIE['garnet_admin'] = 'wrong-cookie';
                expect($this->fn->invoke(null))->toBe(false);
            });

            it('returns false when token exists but is still pending', function (): void {
                AdminAuth::saveToken('pending');
                // Skip activation
                $_COOKIE['garnet_admin'] = 'pending';
                expect($this->fn->invoke(null))->toBe(false);
            });

            it('returns true when cookie matches an active token', function (): void {
                AdminAuth::saveToken('live');
                AdminAuth::activateToken('live');
                $_COOKIE['garnet_admin'] = 'live';

                expect($this->fn->invoke(null))->toBe(true);
            });
        });

        describe('::handle — unauthenticated reaches login page', function (): void {
            it('renders the login HTML when no token + no cookie + clean URI', function (): void {
                ob_start();
                // Suppress header() warnings — we only want the body
                @AdminApp::handle('/__garnet/');
                $html = ob_get_clean();

                expect($html)->toContain('Garnet Admin - Login');
            });

            it('returns 401 JSON for protected routes without auth', function (): void {
                $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'GET';

                ob_start();
                @AdminApp::handle('/__garnet/api/status');
                $body = ob_get_clean();

                expect($body)->toContain('Unauthorized');

                if ($prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $prevMethod;
                }
            });

            it('returns 404 JSON for an unknown protected route (when authenticated)', function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';

                $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'GET';

                ob_start();
                @AdminApp::handle('/__garnet/api/nope');
                $body = ob_get_clean();

                expect($body)->toContain('Not found');

                if ($prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $prevMethod;
                }
            });
        });

        describe('::handle — token activation flow', function (): void {
            // handle() reads the token from $_GET, not from the parsed URI —
            // so we set $_GET['token'] directly per spec.
            beforeEach(function (): void {
                $this->prevGetToken = $_GET['token'] ?? null;
            });

            afterEach(function (): void {
                if ($this->prevGetToken === null) {
                    unset($_GET['token']);
                } else {
                    $_GET['token'] = $this->prevGetToken;
                }
            });

            it('shows denied page when activating a non-existent token', function (): void {
                $_GET['token'] = 'fake-token';

                ob_start();
                @AdminApp::handle('/__garnet/');
                $body = ob_get_clean();

                expect($body)->toContain('Garnet Admin - Denied');
            });

            it('shows denied page when activating with a wrong token', function (): void {
                AdminAuth::saveToken('correct');
                $_GET['token'] = 'wrong';

                ob_start();
                @AdminApp::handle('/__garnet/');
                $body = ob_get_clean();

                expect($body)->toContain('Garnet Admin - Denied');
            });
        });

        describe('::handle — exec endpoint whitelist enforcement', function (): void {
            beforeEach(function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';
                $this->prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'GET';
            });

            afterEach(function (): void {
                if ($this->prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $this->prevMethod;
                }
                unset($_GET['cmd']);
            });

            it('rejects an arbitrary command with 400 "Command not allowed"', function (): void {
                $_GET['cmd'] = 'rm -rf /';

                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();

                expect($body)->toContain('Command not allowed');
            });

            it('rejects empty cmd', function (): void {
                $_GET['cmd'] = '';

                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();

                expect($body)->toContain('Command not allowed');
            });

            it('rejects deploy / db:wipe / ssh (destructive ops)', function (): void {
                foreach (['deploy', 'db:wipe', 'ssh', 'bundle'] as $bad) {
                    $_GET['cmd'] = $bad;

                    ob_start();
                    @AdminApp::handle('/__garnet/api/exec');
                    $body = ob_get_clean();

                    expect($body)->toContain('Command not allowed');
                }
            });
        });
    });
}
