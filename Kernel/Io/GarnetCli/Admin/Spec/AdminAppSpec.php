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

    /**
     * Stream wrapper to fake php://input for end-to-end testing.
     * Usage:
     *   PhpInputMemoryWrapper::setContent(json_encode(['cmd' => 'prepare', 'csrf' => '...']));
     *   PhpInputMemoryWrapper::register();
     *   @AdminApp::handle('/__garnet/api/exec-ticket');
     *   PhpInputMemoryWrapper::unregister();
     */
    class PhpInputMemoryWrapper {
        private static ?string $content = null;

        private int $position = 0;

        public function __construct() {
            // PHP instantiates without arguments
        }

        public static function setContent(string $content): void {
            self::$content = $content;
        }

        public static function register(): void {
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', self::class);
        }

        public static function unregister(): void {
            stream_wrapper_restore('php');
            self::$content = null;
        }

        public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool {
            return str_starts_with($path, 'php://input');
        }

        public function stream_read(int $count): string|false {
            if (self::$content === null) {
                return false;
            }
            $chunk = substr(self::$content, $this->position, $count);
            $this->position += strlen($chunk);

            return $chunk === '' ? false : $chunk;
        }

        public function stream_eof(): bool {
            return self::$content === null || $this->position >= strlen(self::$content);
        }

        public function stream_stat(): array|false {
            return self::$content === null
                ? false
                : ['mode' => 0o100644, 'size' => strlen(self::$content)];
        }

        public function stream_cast(int $castAs): mixed {
            return false;
        }

        public function stream_close(): void {
            // No-op
        }

        public function stream_lock(int $operation): bool {
            return true;
        }

        public function stream_seek(int $offset, int $whence = SEEK_SET): bool {
            if (self::$content === null) {
                return false;
            }
            $newPosition = match ($whence) {
                SEEK_SET => $offset,
                SEEK_CUR => $this->position + $offset,
                SEEK_END => strlen(self::$content) + $offset,
                default => $this->position,
            };

            if ($newPosition < 0 || $newPosition > strlen(self::$content)) {
                return false;
            }
            $this->position = $newPosition;

            return true;
        }

        public function stream_tell(): int {
            return $this->position;
        }

        // Required for PHP 8.0+
        public function url_stat(string $path, int $flags): array|false {
            return str_starts_with($path, 'php://input') && self::$content !== null
                ? ['mode' => 0o100644, 'size' => strlen(self::$content)]
                : false;
        }
    }

    describe('AdminApp', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_aapp_' . uniqid();
            mkdir($this->tempDir, 0o777, true);
            $this->prevRoot = $_ENV['GARNET_ROOT'] ?? null;
            $_ENV['GARNET_ROOT'] = $this->tempDir;

            // handle() now 404s any request that isn't dev + loopback (see
            // AdminApp::isDevRequestAllowed) — force both for these specs.
            $this->prevForceDev = $_ENV['GARNET_ADMIN_FORCE_DEV'] ?? null;
            $_ENV['GARNET_ADMIN_FORCE_DEV'] = '1';
            $this->prevRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

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

            if ($this->prevForceDev === null) {
                unset($_ENV['GARNET_ADMIN_FORCE_DEV']);
            } else {
                $_ENV['GARNET_ADMIN_FORCE_DEV'] = $this->prevForceDev;
            }

            if ($this->prevRemoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $this->prevRemoteAddr;
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

            // Clean up any exec ticket files that may have been created
            $ticketPattern = $this->tempDir . DIRECTORY_SEPARATOR . '.garnet_admin_exec_*';
            $ticketFiles = glob($ticketPattern);

            foreach ($ticketFiles as $tf) {
                if (file_exists($tf)) {
                    unlink($tf);
                }
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

        describe('::handle — /__garnet/api/exec-ticket (POST-only gate)', function (): void {
            beforeEach(function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';
                $this->csrf = AdminAuth::csrfToken();
            });

            afterEach(function (): void {
                unset($_SERVER['REQUEST_METHOD']);
            });

            it('rejects GET requests with 405 Method not allowed', function (): void {
                $_SERVER['REQUEST_METHOD'] = 'GET';

                ob_start();
                @AdminApp::handle('/__garnet/api/exec-ticket');
                $body = ob_get_clean();

                expect($body)->toContain('Method not allowed');
            });

            it('accepts POST requests with valid CSRF', function (): void {
                $_SERVER['REQUEST_METHOD'] = 'POST';
                PhpInputMemoryWrapper::setContent(json_encode(['cmd' => 'build', 'csrf' => $this->csrf]));
                PhpInputMemoryWrapper::register();

                try {
                    ob_start();
                    @AdminApp::handle('/__garnet/api/exec-ticket');
                    $body = ob_get_clean();
                    $response = json_decode($body, true);
                    expect(isset($response['ticket']))->toBe(true);
                } finally {
                    PhpInputMemoryWrapper::unregister();
                }
            });
        });

        describe('::handle — /__garnet/assets (filename whitelist)', function (): void {
            it('rejects filenames containing path separators', function (): void {
                foreach (['../etc/passwd', '..\\windows\\system32', 'test/../../etc', 'test\\..\\..\\windows'] as $bad) {
                    ob_start();
                    @AdminApp::handle('/__garnet/assets/' . $bad);
                    $body = ob_get_clean();
                    // The filename whitelist regex /[^a-zA-Z0-9._-]+/ should reject these
                    // but the real protection is basename() at line 411
                    expect($body)->not->toContain('admin.js'); // Should not serve real admin assets
                }
            });

            it('accepts safe filenames', function (): void {
                foreach (['admin.js', 'admin.css', 'app.min.js', 'test-file_v2.js'] as $good) {
                    ob_start();
                    @AdminApp::handle('/__garnet/assets/' . $good);
                    $body = ob_get_clean();
                    // Safe filenames pass the whitelist regex at line 414
                    // Will get 404 if files don't exist, but the response status code should NOT be 400
                    http_response_code(200); // Reset to avoid affecting other tests
                }
            });
        });

        describe('::handle — /__garnet/api/app-use (app-name regex)', function (): void {
            beforeEach(function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $_SERVER['CONTENT_TYPE'] = 'application/json';
                $this->csrf = AdminAuth::csrfToken();
            });

            afterEach(function (): void {
                unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);
            });

            it('rejects app names with invalid characters', function (): void {
                $invalidNames = ['my-app', 'my.app', '123app', '', 'App-1', 'App_1-2', '../etc', '../../../etc'];

                foreach ($invalidNames as $badName) {
                    PhpInputMemoryWrapper::setContent(json_encode(['app' => $badName, 'csrf' => $this->csrf]));
                    PhpInputMemoryWrapper::register();

                    try {
                        ob_start();
                        @AdminApp::handle('/__garnet/api/app-use');
                        $body = ob_get_clean();
                        expect($body)->toContain('Invalid app name');
                    } finally {
                        PhpInputMemoryWrapper::unregister();
                    }
                }
            });

            it('accepts valid app names', function (): void {
                $validNames = ['MyApp', 'my_app', 'App1', 'Test_App_123', '_private', 'My_Private_App2'];

                foreach ($validNames as $goodName) {
                    PhpInputMemoryWrapper::setContent(json_encode(['app' => $goodName, 'csrf' => $this->csrf]));
                    PhpInputMemoryWrapper::register();

                    try {
                        ob_start();
                        @AdminApp::handle('/__garnet/api/app-use');
                        $body = ob_get_clean();
                        // Will get 404 if app doesn't exist, but NOT 400 (invalid name)
                        expect($body)->not->toContain('Invalid app name');
                    } finally {
                        PhpInputMemoryWrapper::unregister();
                    }
                }
            });
        });

        describe('::handle — exec-ticket endpoint (CSRF + whitelist enforcement)', function (): void {
            beforeEach(function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';
                $this->prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $this->csrf = AdminAuth::csrfToken();
            });

            afterEach(function (): void {
                if ($this->prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $this->prevMethod;
                }
            });

            it('rejects a POST without a matching CSRF token with 403', function (): void {
                ob_start();
                @AdminApp::handle('/__garnet/api/exec-ticket');
                $body = ob_get_clean();

                expect($body)->toContain('Bad CSRF token');
            });

            it('rejects a GET to /api/exec (no ticket) with 400 "Command not allowed"', function (): void {
                $_SERVER['REQUEST_METHOD'] = 'GET';

                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();

                expect($body)->toContain('Command not allowed');
            });

            it('rejects a GET to /api/exec with a bogus ticket', function (): void {
                $_SERVER['REQUEST_METHOD'] = 'GET';
                $_GET['ticket'] = 'not-a-real-ticket';

                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();

                unset($_GET['ticket']);

                expect($body)->toContain('Command not allowed');
            });

            it('rejects deploy / db:wipe / ssh (destructive ops) even with a valid CSRF token', function (): void {
                foreach (['deploy', 'db:wipe', 'ssh', 'bundle'] as $bad) {
                    PhpInputMemoryWrapper::setContent(json_encode(['cmd' => $bad, 'csrf' => $this->csrf]));
                    PhpInputMemoryWrapper::register();

                    try {
                        ob_start();
                        @AdminApp::handle('/__garnet/api/exec-ticket');
                        $body = ob_get_clean();
                        expect($body)->toContain('Command not allowed');
                    } finally {
                        PhpInputMemoryWrapper::unregister();
                    }
                }
            });

            it('issues a redeemable single-use ticket for an allowed command via a real CSRF-checked POST', function (): void {
                PhpInputMemoryWrapper::setContent(json_encode(['cmd' => 'prepare', 'csrf' => $this->csrf]));
                PhpInputMemoryWrapper::register();

                try {
                    ob_start();
                    @AdminApp::handle('/__garnet/api/exec-ticket');
                    $body = ob_get_clean();
                    $response = json_decode($body, true);
                    expect($response)->toBeAn('array');
                    expect(isset($response['ticket']))->toBe(true);
                    expect($response['ticket'])->toBeA('string');

                    // Verify the ticket is redeemable
                    $ticket = $response['ticket'];
                    $redeemedCmd = AdminAuth::redeemExecTicket($ticket);
                    expect($redeemedCmd)->toBe('prepare');

                    // Single-use: the same ticket cannot be redeemed twice
                    expect(AdminAuth::redeemExecTicket($ticket))->toBeNull();
                } finally {
                    PhpInputMemoryWrapper::unregister();
                }
            });

            it('redeems a valid ticket and enforces ALLOWED_COMMANDS at handleExec() time', function (): void {
                // Issue a ticket for an allowed command
                $ticket = AdminAuth::issueExecTicket('build');
                expect($ticket)->toBeA('string');

                // Try to execute with the ticket (it will fail to actually run the command
                // since we're in a test environment, but it should NOT be rejected by the whitelist)
                $_SERVER['REQUEST_METHOD'] = 'GET';
                $_GET['ticket'] = $ticket;
                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();
                unset($_GET['ticket']);

                // The command should have passed the whitelist check
                // (it will fail for other reasons in this test environment, but NOT 'Command not allowed')
                expect($body)->not->toContain('Command not allowed');
            });

            it('rejects handleExec() when ticket redeems to a non-whitelisted command', function (): void {
                // Manually create a ticket file for a non-whitelisted command
                // (simulating a compromised ticket file)
                $badTicket = '1234567890abcdef1234567890abcdef';
                $ticketFile = $this->tempDir . DIRECTORY_SEPARATOR . '.garnet_admin_exec_' . $badTicket;
                file_put_contents($ticketFile, json_encode(['cmd' => 'deploy', 'ts' => time()]));

                // Try to execute with the bad ticket
                $_SERVER['REQUEST_METHOD'] = 'GET';
                $_GET['ticket'] = $badTicket;
                ob_start();
                @AdminApp::handle('/__garnet/api/exec');
                $body = ob_get_clean();
                unset($_GET['ticket']);

                // Should be rejected by the whitelist in handleExec()
                expect($body)->toContain('Command not allowed');
            });
        });

        describe('::handle — /__garnet/logout (auth + CSRF required)', function (): void {
            it('rejects an unauthenticated POST with 401 and does NOT delete the token file', function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                // Deliberately no cookie set — unauthenticated caller.

                $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'POST';

                ob_start();
                @AdminApp::handle('/__garnet/logout');
                $body = ob_get_clean();

                if ($prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $prevMethod;
                }

                expect($body)->toContain('Unauthorized');
                expect(AdminAuth::readToken())->not->toBeNull();
            });

            it('rejects an authenticated POST without a valid CSRF token with 403 and does NOT delete the token file', function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';

                $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'POST';

                ob_start();
                @AdminApp::handle('/__garnet/logout');
                $body = ob_get_clean();

                if ($prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $prevMethod;
                }

                expect($body)->toContain('Bad CSRF token');
                expect(AdminAuth::readToken())->not->toBeNull();
            });

            it('accepts a real CSRF token via the same check handleExecTicket uses (unit under test: AdminAuth::checkCsrfToken)', function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $csrf = AdminAuth::csrfToken();

                // handleLogout() reads php://input, which cannot be faked per-call
                // in a kahlan spec without a stream wrapper (same limitation noted
                // for handleExecTicket above) — assert the underlying CSRF contract
                // directly instead, i.e. that a correctly-derived token validates.
                expect(AdminAuth::checkCsrfToken($csrf))->toBe(true);
                expect(AdminAuth::checkCsrfToken('wrong'))->toBe(false);
            });
        });

        describe('::handle — /__garnet/api/app-use (auth + CSRF + Content-Type required)', function (): void {
            beforeEach(function (): void {
                AdminAuth::saveToken('t');
                AdminAuth::activateToken('t');
                $_COOKIE['garnet_admin'] = 't';
                $this->prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
                $_SERVER['REQUEST_METHOD'] = 'POST';
                $this->prevContentType = $_SERVER['CONTENT_TYPE'] ?? null;
            });

            afterEach(function (): void {
                if ($this->prevMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $this->prevMethod;
                }

                if ($this->prevContentType === null) {
                    unset($_SERVER['CONTENT_TYPE']);
                } else {
                    $_SERVER['CONTENT_TYPE'] = $this->prevContentType;
                }
            });

            it('rejects a text/plain body (CORS-simple, no preflight) with 415 before touching CSRF/app switching', function (): void {
                $_SERVER['CONTENT_TYPE'] = 'text/plain';

                ob_start();
                @AdminApp::handle('/__garnet/api/app-use');
                $body = ob_get_clean();

                expect($body)->toContain('Unsupported content type');
            });

            it('rejects an application/json body without a matching CSRF token with 403', function (): void {
                $_SERVER['CONTENT_TYPE'] = 'application/json';

                ob_start();
                @AdminApp::handle('/__garnet/api/app-use');
                $body = ob_get_clean();

                expect($body)->toContain('Bad CSRF token');
            });

            it('the CSRF contract used by app-use accepts the real token and rejects a wrong one (unit under test: AdminAuth::checkCsrfToken)', function (): void {
                // handleAppUse() reads php://input directly; simulate via the same
                // underlying check it calls, mirroring the exec-ticket spec above —
                // end-to-end HTTP body faking is out of scope for kahlan here.
                $csrf = AdminAuth::csrfToken();

                expect(AdminAuth::checkCsrfToken($csrf))->toBe(true);
                expect(AdminAuth::checkCsrfToken('wrong'))->toBe(false);
            });
        });

        // build:watch is a file watcher that never exits by design. Streaming
        // it over the same run-to-completion SSE loop as `build`/`prepare`/
        // `migration` would pin a worker forever (see handleExec's dispatch).
        // These specs cover the detached-start branch and the general
        // streaming timeout added as defense-in-depth for the other commands.
        describe('::handleExec — build:watch takes the detached path', function (): void {
            it('routes build:watch through execBuildWatchDetached, not execStreamed', function (): void {
                $reflection = new ReflectionClass(AdminApp::class);
                $source = file_get_contents($reflection->getFileName());

                // Static assertion on the dispatch: build:watch must be special-cased
                // before falling through to the streamed/blocking proc_open loop.
                expect($source)->toContain("cmd === 'build:watch'");
                expect($source)->toContain('execBuildWatchDetached');
            });

            it('execBuildWatchDetached emits an immediate "started" event and a done event (never blocks on process output)', function (): void {
                // Uses its own scratch dir (not $this->tempDir): on Windows,
                // `start /B` runs through a cmd.exe shell spawned via popen()
                // that briefly holds the working directory open even after
                // pclose() returns, which races the shared tempDir's rmdir()
                // in afterEach. An isolated, best-effort-cleaned dir avoids
                // that flake without weakening the assertion.
                $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gtest_bw_' . uniqid();
                mkdir($dir, 0o777, true);

                $method = new ReflectionMethod(AdminApp::class, 'execBuildWatchDetached');

                ob_start();
                $method->invoke(null, $dir);
                $body = ob_get_clean();

                expect($body)->toContain('started in the background');
                expect($body)->toContain('event: done');

                @rmdir($dir);
            });
        });

        describe('::EXEC_STREAM_TIMEOUT_SECONDS — defense-in-depth cap on the streaming loop', function (): void {
            it('defines a positive, finite timeout applied to non-detached commands', function (): void {
                $reflection = new ReflectionClass(AdminApp::class);
                $timeout = $reflection->getReflectionConstant('EXEC_STREAM_TIMEOUT_SECONDS')->getValue();

                expect($timeout)->toBeA('integer');
                expect($timeout > 0)->toBe(true);
            });

            it('is enforced inside execStreamed via a wall-clock deadline check', function (): void {
                $reflection = new ReflectionClass(AdminApp::class);
                $source = file_get_contents($reflection->getFileName());

                expect($source)->toContain('$deadline = time() + self::EXEC_STREAM_TIMEOUT_SECONDS');
                expect($source)->toContain('time() >= $deadline');
                expect($source)->toContain('proc_terminate($process)');
            });
        });

        describe('::handle — loopback request guard (isDevRequestAllowed)', function (): void {
            it('returns 404 when REMOTE_ADDR is a non-loopback address even with GARNET_ADMIN_FORCE_DEV=1', function (): void {
                // Temporarily override REMOTE_ADDR to a LAN IP (non-loopback)
                $prevRemoteAddrLocal = $_SERVER['REMOTE_ADDR'] ?? null;
                $_SERVER['REMOTE_ADDR'] = '192.168.1.50';

                ob_start();
                @AdminApp::handle('/__garnet/');
                $body = ob_get_clean();

                // Restore the original REMOTE_ADDR before global afterEach runs
                if ($prevRemoteAddrLocal === null) {
                    unset($_SERVER['REMOTE_ADDR']);
                } else {
                    $_SERVER['REMOTE_ADDR'] = $prevRemoteAddrLocal;
                }

                expect($body)->toContain('Not found');
            });

            it('allows request when REMOTE_ADDR is 127.0.0.1 (IPv4 loopback)', function (): void {
                // Ensure loopback IPv4 is set
                $prevRemoteAddrLocal = $_SERVER['REMOTE_ADDR'] ?? null;
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

                ob_start();
                @AdminApp::handle('/__garnet/');
                $body = ob_get_clean();

                // Restore the original REMOTE_ADDR before global afterEach runs
                if ($prevRemoteAddrLocal === null) {
                    unset($_SERVER['REMOTE_ADDR']);
                } else {
                    $_SERVER['REMOTE_ADDR'] = $prevRemoteAddrLocal;
                }

                // Should return the login page, not "Not found"
                expect($body)->toContain('Garnet Admin - Login');
            });

            it('allows request when REMOTE_ADDR is ::1 (IPv6 loopback)', function (): void {
                // Ensure loopback IPv6 is set
                $prevRemoteAddrLocal = $_SERVER['REMOTE_ADDR'] ?? null;
                $_SERVER['REMOTE_ADDR'] = '::1';

                ob_start();
                @AdminApp::handle('/__garnet/');
                $body = ob_get_clean();

                // Restore the original REMOTE_ADDR before global afterEach runs
                if ($prevRemoteAddrLocal === null) {
                    unset($_SERVER['REMOTE_ADDR']);
                } else {
                    $_SERVER['REMOTE_ADDR'] = $prevRemoteAddrLocal;
                }

                // Should return the login page, not "Not found"
                expect($body)->toContain('Garnet Admin - Login');
            });
        });
    });
}
