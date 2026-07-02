<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\Spec {
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;
    use function file_exists;
    use function file_put_contents;
    use function is_dir;
    use function json_encode;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminAuth;

    use function preg_match;
    use function rmdir;
    use function strlen;
    use function sys_get_temp_dir;

    use Throwable;

    use function uniqid;
    use function unlink;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 6));
    }

    describe('AdminAuth', function (): void {
        // Per-spec tmp dir + $_ENV override so the token file lands in a
        // controlled location and we never touch the real .garnet_admin.

        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_aauth_' . uniqid();
            mkdir($this->tempDir, 0o777, true);
            $this->prevRoot = $_ENV['GARNET_ROOT'] ?? null;
            $_ENV['GARNET_ROOT'] = $this->tempDir;

            $this->tokenFile = $this->tempDir . DIRECTORY_SEPARATOR . '.garnet_admin';
        });

        afterEach(function (): void {
            if ($this->prevRoot === null) {
                unset($_ENV['GARNET_ROOT']);
            } else {
                $_ENV['GARNET_ROOT'] = $this->prevRoot;
            }

            if (file_exists($this->tokenFile)) {
                unlink($this->tokenFile);
            }

            if (is_dir($this->tempDir)) {
                rmdir($this->tempDir);
            }
        });

        describe('::generateToken', function (): void {
            it('returns a 32-char lowercase hex string (16 random bytes)', function (): void {
                $t = AdminAuth::generateToken();
                expect(strlen($t))->toBe(32);
                expect((bool)preg_match('/^[0-9a-f]{32}$/', $t))->toBe(true);
            });

            it('returns different values on consecutive calls (entropy)', function (): void {
                $a = AdminAuth::generateToken();
                $b = AdminAuth::generateToken();
                expect($a)->not->toBe($b);
            });
        });

        describe('::saveToken + ::readToken', function (): void {
            it('round-trips a token through the json file with status=pending', function (): void {
                AdminAuth::saveToken('abc123');
                $data = AdminAuth::readToken();

                expect($data['token'])->toBe('abc123');
                expect($data['status'])->toBe('pending');
                expect($data['created'])->toBeA('integer');
            });

            it('readToken returns null when the file is missing', function (): void {
                expect(AdminAuth::readToken())->toBeNull();
            });

            it('readToken returns null when the file is corrupt JSON', function (): void {
                file_put_contents($this->tokenFile, 'not-json{');
                expect(AdminAuth::readToken())->toBeNull();
            });

            it('readToken returns null when token field is missing', function (): void {
                file_put_contents($this->tokenFile, json_encode(['status' => 'pending']));
                expect(AdminAuth::readToken())->toBeNull();
            });

            it('readToken returns null when token field is empty string', function (): void {
                file_put_contents($this->tokenFile, json_encode(['token' => '', 'status' => 'pending']));
                expect(AdminAuth::readToken())->toBeNull();
            });
        });

        describe('::activateToken — state machine pending → active', function (): void {
            it('flips status from pending to active when the supplied token matches', function (): void {
                AdminAuth::saveToken('match-me');
                expect(AdminAuth::activateToken('match-me'))->toBe(true);

                $data = AdminAuth::readToken();
                expect($data['status'])->toBe('active');
                expect($data['token'])->toBe('match-me');  // token itself unchanged
            });

            it('rejects activation with a wrong token (no state change, returns false)', function (): void {
                AdminAuth::saveToken('correct');
                expect(AdminAuth::activateToken('wrong'))->toBe(false);

                $data = AdminAuth::readToken();
                expect($data['status'])->toBe('pending');  // still pending
            });

            it('rejects double activation — once active, the correct token still fails', function (): void {
                AdminAuth::saveToken('token-x');
                AdminAuth::activateToken('token-x');

                // Second call with the same correct token: status is now 'active',
                // so the precondition (status === 'pending') is false and the call
                // returns false. Critical: an attacker replaying the activation
                // request can't re-roll status.
                expect(AdminAuth::activateToken('token-x'))->toBe(false);
                $data = AdminAuth::readToken();
                expect($data['status'])->toBe('active');
            });

            it('returns false when no token file exists', function (): void {
                expect(AdminAuth::activateToken('anything'))->toBe(false);
            });
        });

        describe('::validateCookie', function (): void {
            it('returns true when the cookie matches an ACTIVE token', function (): void {
                AdminAuth::saveToken('alive');
                AdminAuth::activateToken('alive');

                expect(AdminAuth::validateCookie('alive'))->toBe(true);
            });

            it('returns false when the token is PENDING (not yet activated)', function (): void {
                AdminAuth::saveToken('alive');
                // Skip activation
                expect(AdminAuth::validateCookie('alive'))->toBe(false);
            });

            it('returns false for a wrong cookie value', function (): void {
                AdminAuth::saveToken('alive');
                AdminAuth::activateToken('alive');

                expect(AdminAuth::validateCookie('not-alive'))->toBe(false);
                expect(AdminAuth::validateCookie(''))->toBe(false);
            });

            it('returns false when no token file exists', function (): void {
                expect(AdminAuth::validateCookie('anything'))->toBe(false);
            });
        });

        describe('::deleteToken', function (): void {
            it('removes the token file when present', function (): void {
                AdminAuth::saveToken('tmp');
                expect(file_exists($this->tokenFile))->toBe(true);

                AdminAuth::deleteToken();
                expect(file_exists($this->tokenFile))->toBe(false);
            });

            it('is a no-op when the token file is absent (no throw)', function (): void {
                $threw = false;

                try {
                    AdminAuth::deleteToken();
                } catch (Throwable $e) {
                    $threw = true;
                }
                expect($threw)->toBe(false);
            });

            it('post-delete, validateCookie returns false', function (): void {
                AdminAuth::saveToken('x');
                AdminAuth::activateToken('x');
                expect(AdminAuth::validateCookie('x'))->toBe(true);

                AdminAuth::deleteToken();
                expect(AdminAuth::validateCookie('x'))->toBe(false);
            });
        });
    });
}
