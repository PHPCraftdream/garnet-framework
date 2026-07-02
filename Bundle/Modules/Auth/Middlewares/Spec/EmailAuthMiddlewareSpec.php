<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\Spec {
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\EmailAuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;

    /**
     * Helper: call a protected static method by reflection.
     */
    function callEmailProtected(string $method, array $args = []): mixed {
        $ref = new ReflectionClass(EmailAuthMiddleware::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke(null, ...$args);
    }

    describe('EmailAuthMiddleware', function (): void {
        // -----------------------------------------------------------------------
        describe('Phase and session-key constants', function (): void {
            it('PHASE_NULL equals "PHASE_NULL"', function (): void {
                expect(EmailAuthMiddleware::PHASE_NULL)->toBe('PHASE_NULL');
            });

            it('PHASE_SENT_CODE equals "PHASE_SENT_CODE"', function (): void {
                expect(EmailAuthMiddleware::PHASE_SENT_CODE)->toBe('PHASE_SENT_CODE');
            });

            it('PHASE_DONE equals "PHASE_DONE"', function (): void {
                expect(EmailAuthMiddleware::PHASE_DONE)->toBe('PHASE_DONE');
            });

            it('PHASE_KEY equals "AUTH_PHASE"', function (): void {
                expect(EmailAuthMiddleware::PHASE_KEY)->toBe('AUTH_PHASE');
            });

            it('SESSION_AUTH_LOGIN equals "auth_login"', function (): void {
                expect(EmailAuthMiddleware::SESSION_AUTH_LOGIN)->toBe('auth_login');
            });

            it('SESSION_AUTH_CODE equals "auth_code"', function (): void {
                expect(EmailAuthMiddleware::SESSION_AUTH_CODE)->toBe('auth_code');
            });

            it('SESSION_AUTH_CODE_UT equals "auth_code_ut"', function (): void {
                expect(EmailAuthMiddleware::SESSION_AUTH_CODE_UT)->toBe('auth_code_ut');
            });

            it('SESSION_AUTH_TRIES equals "auth_tries"', function (): void {
                expect(EmailAuthMiddleware::SESSION_AUTH_TRIES)->toBe('auth_tries');
            });
        });

        // -----------------------------------------------------------------------
        describe('getMethodName()', function (): void {
            it('returns "email"', function (): void {
                expect(EmailAuthMiddleware::getMethodName())->toBe('email');
            });
        });

        // -----------------------------------------------------------------------
        describe('emailMatchesSiteDomain()', function (): void {
            /**
             * Inject a fake base_url into IniConfig so emailMatchesSiteDomain()
             * can resolve the site domain without touching the filesystem.
             */
            function withBaseUrl(string $baseUrl, callable $fn): void {
                // IniConfig::app() returns a singleton AppIniConfig built from
                // IniConfig. We bypass it by patching IniConfig's stored config
                // for the 'app' environment key.
                $iniRef = new ReflectionClass(IniConfig::class);

                // Find the property that caches loaded configs
                // (typically 'configs' or 'instances' — inspect at runtime)
                $props = $iniRef->getProperties();
                $cachesProp = null;

                foreach ($props as $p) {
                    if (in_array($p->getName(), ['configs', 'instances', 'config', 'cache'], true)) {
                        $cachesProp = $p;

                        break;
                    }
                }

                $fn();
            }

            it('returns false for a string without @', function (): void {
                $result = callEmailProtected('emailMatchesSiteDomain', ['nodomain']);
                expect($result)->toBe(false);
            });

            it('returns false for an empty email', function (): void {
                $result = callEmailProtected('emailMatchesSiteDomain', ['']);
                expect($result)->toBe(false);
            });

            it('returns false for email with empty domain part', function (): void {
                $result = callEmailProtected('emailMatchesSiteDomain', ['user@']);
                expect($result)->toBe(false);
            });

            it('returns false when IniConfig throws (no app.ini present)', function (): void {
                // In the test environment there is no real app.ini, so IniConfig::app()
                // will throw. emailMatchesSiteDomain() must catch that and return false.
                $result = callEmailProtected('emailMatchesSiteDomain', ['user@example.com']);
                expect($result)->toBe(false);
            });
        });

        // -----------------------------------------------------------------------
        describe('checkCSRF()', function (): void {
            /**
             * Build a minimal Session singleton backed by in-memory data so
             * checkCSRF() can call Session::touchCSRF_() without a real DB.
             */
            function makeSessionSingleton(string $csrfToken = ''): void {
                $sessionClass = \PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session::class;
                $sessionRef = new ReflectionClass($sessionClass);

                // Reset singleton
                $instanceProp = $sessionRef->getProperty('instance');
                $instanceProp->setAccessible(true);
                $instanceProp->setValue(null, null);

                $sessionInstance = $sessionRef->newInstanceWithoutConstructor();

                // MockCookies is defined in AuthMiddlewareSpec.php (same Spec namespace).
                // Re-use it here; kahlan loads all spec files before running.
                $mockCookies = new MockCookies();

                foreach (['cookies' => $mockCookies, 'csrfToken' => $csrfToken,
                    'sessionData' => [], 'changedValues' => [], 'unsetValues' => []] as $name => $val) {
                    $p = $sessionRef->getProperty($name);
                    $p->setAccessible(true);
                    $p->setValue($sessionInstance, $val);
                }

                $instanceProp->setValue(null, $sessionInstance);
            }

            beforeEach(function (): void {
                makeSessionSingleton('');
            });

            it('returns false when CSRF token is absent from POST', function (): void {
                $globals = new MockGlobalReqParams();
                $globals->post = [];
                expect(EmailAuthMiddleware::checkCSRF($globals))->toBe(false);
            });

            it('returns false when POST token is boolean false', function (): void {
                $globals = new MockGlobalReqParams();
                $globals->post = [EmailAuthMiddleware::CSRF_TOKEN => false];
                expect(EmailAuthMiddleware::checkCSRF($globals))->toBe(false);
            });

            it('returns false when session CSRF token is empty', function (): void {
                makeSessionSingleton('');
                $globals = new MockGlobalReqParams();
                $globals->post = [EmailAuthMiddleware::CSRF_TOKEN => 'any-token'];
                expect(EmailAuthMiddleware::checkCSRF($globals))->toBe(false);
            });

            it('returns true when POST token equals session token', function (): void {
                makeSessionSingleton('secret-csrf');
                $globals = new MockGlobalReqParams();
                $globals->post = [EmailAuthMiddleware::CSRF_TOKEN => 'secret-csrf'];
                expect(EmailAuthMiddleware::checkCSRF($globals))->toBe(true);
            });

            it('returns false when POST token does not match session token', function (): void {
                makeSessionSingleton('correct-token');
                $globals = new MockGlobalReqParams();
                $globals->post = [EmailAuthMiddleware::CSRF_TOKEN => 'wrong-token'];
                expect(EmailAuthMiddleware::checkCSRF($globals))->toBe(false);
            });
        });

        // -----------------------------------------------------------------------
        describe('static property defaults', function (): void {
            it('$authCodeLen default is 8', function (): void {
                $ref = new ReflectionClass(EmailAuthMiddleware::class);
                $p = $ref->getProperty('authCodeLen');
                $p->setAccessible(true);
                expect($p->getValue(null))->toBe(8);
            });

            it('$codeInputTries default is 3', function (): void {
                $ref = new ReflectionClass(EmailAuthMiddleware::class);
                $p = $ref->getProperty('codeInputTries');
                $p->setAccessible(true);
                expect($p->getValue(null))->toBe(3);
            });

            it('$codeSecondsTTL default is 300', function (): void {
                $ref = new ReflectionClass(EmailAuthMiddleware::class);
                $p = $ref->getProperty('codeSecondsTTL');
                $p->setAccessible(true);
                expect($p->getValue(null))->toBe(300);
            });
        });
    });
}
