<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\Spec {
    use Mockery;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\AuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionData;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookies;
    use Psr\Http\Message\ResponseInterface;
    use ReflectionClass;

    /**
     * Test-only subclass that no-ops sendSuccessLogin(). processPhaseSentCodePost()
     * calls sendSuccessLogin() via `static::` (NOT self::) — the framework relies
     * on this late-static-binding so app overrides of sendSuccessLogin() fire
     * (see EmailAuthMiddleware::completeLogin docblock). We use the SAME
     * mechanism to short-circuit the login-email side effect (Mailer/Twig/
     * DateTools) WITHOUT depending on kahlan's allow()->toReceive(), which does
     * not intercept Account::touchAccount() in this project's kahlan setup.
     *
     * publicProcessPhaseSentCodePost() is a public bridge so the spec triggers
     * the inherited protected method through a REAL static call (not
     * ReflectionMethod::invoke, which does not establish LSB to the subclass
     * and would route static::sendSuccessLogin back to AuthMiddleware).
     */
    class SentCodePostTestMiddleware extends AuthMiddleware {
        public static function sendSuccessLogin(IGlobalReqParams $globals, string $authEmail): void {
            // no-op — isolates the test from Mailer/Twig
        }

        public static function publicProcessPhaseSentCodePost(
            IGlobalReqParams $globals,
            IRouterUriParams $params
        ): ResponseInterface {
            return static::processPhaseSentCodePost($globals, $params);
        }
    }

    /**
     * Pre-seed Account::$items so Account::touchAccount() short-circuits to a
     * cache hit (Account.php lines 110-118) and never reaches its DB INSERT
     * (exInsertIgnoreAsync, line 123), which would fail on the test DB (no
     * accounts table). The mock reports readParam('login') === $login so
     * touchAccount()'s cache-match guard returns it directly; every other
     * method (setParam, readDataAsyncPollFinishAll) is ignored.
     */
    function seedAccountItemsCache(string $login): void {
        $accountMock = Mockery::mock(Account::class);
        $accountMock->shouldReceive('readParam')->with('login')->andReturn($login);
        $accountMock->shouldIgnoreMissing();

        $ref = new ReflectionClass(Account::class);
        $itemsProp = $ref->getProperty('items');
        $items = $itemsProp->getValue();
        $items[$login] = $accountMock;
        $itemsProp->setValue(null, $items);
    }

    /**
     * Build a Session singleton simulating the state at the START of the
     * code-verification request: a PRIOR request ran AuthMiddleware::sendCode(),
     * which setValue()'d PHASE_SENT_CODE + auth_code + auth_code_ut +
     * auth_tries + auth_login and flushed them to the resolved DB row (id 42).
     * In THIS request nothing has been setValue()'d yet — auth_login exists
     * only as a READ value in $sessionData, which is exactly the rotate()
     * discard trap documented on Session::rotate() (commits 5bd2b08/de6969e):
     * flush() persists ONLY $changedValues, so a merely-read value is dropped
     * across the rotation boundary unless re-setValue()'d afterwards.
     *
     * Real stateful Cookies + a Mockery SessionData spy (so rotate()'s
     * deleteSessionAsync is a no-op) mirror the harness in
     * CloseAuthSessionRotateSpec.php. MockGlobalReqParams is defined in
     * AuthMiddlewareSpec.php (same Spec namespace — the full Auth/Middlewares
     * spec suite loads together, same shared-mock convention used by
     * EmailAuthMiddlewareSpec.php and CloseAuthSessionRotateSpec.php).
     *
     * Returns [$session, $cookies, $beforeCookie].
     */
    function buildSentCodeVerifySession(): array {
        $cookies = new Cookies();

        $sessionDataSpy = Mockery::mock(SessionData::class)->shouldIgnoreMissing();
        $sdRef = new ReflectionClass(SessionData::class);
        $sdRef->getProperty('instance')->setValue(null, $sessionDataSpy);

        $sessionRef = new ReflectionClass(Session::class);
        $sessionRef->getProperty('instance')->setValue(null, null);
        $session = $sessionRef->newInstanceWithoutConstructor();

        $before = str_repeat('x', Session::COOKIE_VALUE_LEN);
        $cookies->get(Session::COOKIE_NAME_SESSION)->setValue($before);

        $sessionRef->getProperty('cookies')->setValue($session, $cookies);
        $sessionRef->getProperty('read')->setValue($session, true);
        $sessionRef->getProperty('sessionValue')->setValue($session, $before);
        $sessionRef->getProperty('sessionId')->setValue($session, 42);
        $sessionRef->getProperty('csrfToken')->setValue($session, str_repeat('c', Session::COOKIE_VALUE_LEN));
        $sessionRef->getProperty('changedValues')->setValue($session, []);
        $sessionRef->getProperty('unsetValues')->setValue($session, []);

        $sessionRef->getProperty('sessionData')->setValue($session, [
            AuthMiddleware::PHASE_KEY => AuthMiddleware::PHASE_SENT_CODE,
            Account::SESSION_AUTH_LOGIN => 'user@example.com',
            AuthMiddleware::SESSION_AUTH_CODE => '12345678',
            AuthMiddleware::SESSION_AUTH_CODE_UT => (string)time(),
            AuthMiddleware::SESSION_AUTH_TRIES => '3',
        ]);

        $sessionRef->getProperty('instance')->setValue(null, $session);

        return [$session, $cookies, $before];
    }

    function resetSentCodeVerifySpecSingletons(): void {
        $sessionRef = new ReflectionClass(Session::class);
        $sessionRef->getProperty('instance')->setValue(null, null);
        $sdRef = new ReflectionClass(SessionData::class);
        $sdRef->getProperty('instance')->setValue(null, null);

        // Clear the Account cache we seeded so it doesn't leak into other specs.
        $accRef = new ReflectionClass(Account::class);
        $accRef->getProperty('items')->setValue(null, []);
        $accRef->getProperty('sessionAccount')->setValue(null, null);
    }

    describe('processPhaseSentCodePost() persists auth_login across rotate()', function (): void {
        beforeEach(function (): void {
            seedAccountItemsCache('user@example.com');
        });

        afterEach(function (): void {
            Mockery::close();
            resetSentCodeVerifySpecSingletons();
        });

        it('re-setValue()s auth_login after rotate() so it lands in $changedValues for the new row', function (): void {
            [$session, $_, $_] = buildSentCodeVerifySession();

            $globals = new MockGlobalReqParams();
            $globals->post = ['code' => '12345678'];

            $params = Mockery::mock(IRouterUriParams::class);

            SentCodePostTestMiddleware::publicProcessPhaseSentCodePost($globals, $params);

            // flush() writes ONLY $changedValues under the post-rotate
            // identifier — so auth_login MUST be present here or the next
            // request finds an empty value and Account::fromSession()
            // returns null (user appears logged out right after login).
            //
            // NOTE: asserting getValue(SESSION_AUTH_LOGIN) here instead would
            // NOT catch the bug — rotate() doesn't clear $sessionData and the
            // success path's unsetValues([CODE, CODE_UT, TRIES]) doesn't list
            // auth_login, so the pre-rotation read value lingers in memory
            // for the rest of THIS request even with the bug present. The
            // $changedValues reflection check below is the real guard.
            $changedValues = (new ReflectionClass(Session::class))
                ->getProperty('changedValues')
                ->getValue($session);

            expect(array_key_exists(Account::SESSION_AUTH_LOGIN, $changedValues))->toBe(true);
            expect($changedValues[Account::SESSION_AUTH_LOGIN])->toBe('user@example.com');
            expect($changedValues[AuthMiddleware::PHASE_KEY])->toBe(AuthMiddleware::PHASE_DONE);
        });

        it('rotates the session cookie on the anonymous->authenticated transition', function (): void {
            [$_, $cookies, $before] = buildSentCodeVerifySession();

            $globals = new MockGlobalReqParams();
            $globals->post = ['code' => '12345678'];

            $params = Mockery::mock(IRouterUriParams::class);

            SentCodePostTestMiddleware::publicProcessPhaseSentCodePost($globals, $params);

            $after = $cookies->get(Session::COOKIE_NAME_SESSION)->getValue();
            expect(strlen($after))->toBe(Session::COOKIE_VALUE_LEN);
            expect($after)->not->toBe($before);
        });
    });
}
