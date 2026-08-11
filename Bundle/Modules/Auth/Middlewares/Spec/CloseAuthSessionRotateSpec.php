<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\Spec {
    use Mockery;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\AuthMiddleware;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\EmailAuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionData;
    use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookies;
    use ReflectionClass;

    /**
     * Build a Session singleton simulating the state at the START of a logout
     * request on an already-authenticated session: the browser sent a session
     * cookie, readDataAsync() resolved the DB row (id 42) and its session_data
     * (auth keys + an unrelated non-auth "cart" value), and nothing has been
     * setValue()'d yet this request.
     *
     * SessionData is swapped for a permissive Mockery spy so rotate()'s
     * deleteSessionAsync() is observable instead of hitting a real DB.
     *
     * Returns [$session, $cookies, $beforeValue, $sessionDataSpy].
     */
    function buildAuthenticatedLogoutSession(): array {
        // Real, stateful Cookies so setValue()->getValue() round-trips work —
        // a Set-Cookie carrying the rotated value is observable through the
        // cookie object exactly as the browser / a later request would see it.
        $cookies = new Cookies();

        // Permissive SessionData spy: deleteSessionAsync() becomes a no-op we
        // assert on via shouldHaveReceived(), instead of a live DELETE query.
        $sessionDataSpy = Mockery::mock(SessionData::class)->shouldIgnoreMissing();
        $sdRef = new ReflectionClass(SessionData::class);
        $sdRef->getProperty('instance')->setValue(null, $sessionDataSpy);

        $sessionRef = new ReflectionClass(Session::class);
        $sessionRef->getProperty('instance')->setValue(null, null);
        $session = $sessionRef->newInstanceWithoutConstructor();

        // Fixed "before" cookie value standing in for the authenticated
        // session identifier the browser is currently holding.
        $before = str_repeat('x', Session::COOKIE_VALUE_LEN);
        $cookies->get(Session::COOKIE_NAME_SESSION)->setValue($before);

        $sessionRef->getProperty('cookies')->setValue($session, $cookies);
        $sessionRef->getProperty('read')->setValue($session, true);
        $sessionRef->getProperty('sessionValue')->setValue($session, $before);
        $sessionRef->getProperty('sessionId')->setValue($session, 42);
        $sessionRef->getProperty('csrfToken')->setValue($session, str_repeat('c', Session::COOKIE_VALUE_LEN));
        $sessionRef->getProperty('changedValues')->setValue($session, []);
        $sessionRef->getProperty('unsetValues')->setValue($session, []);

        // sessionData as read from the DB row: auth phase + auth_login + a
        // pending code, AND an unrelated non-auth value ("cart") that the
        // narrow unsetValues() list in closeAuthSession() does NOT clear.
        $sessionRef->getProperty('sessionData')->setValue($session, [
            AuthMiddleware::PHASE_KEY => AuthMiddleware::PHASE_DONE,
            Account::SESSION_AUTH_LOGIN => 'user@example.com',
            AuthMiddleware::SESSION_AUTH_CODE => '12345678',
            AuthMiddleware::SESSION_AUTH_CODE_UT => (string)time(),
            AuthMiddleware::SESSION_AUTH_TRIES => '2',
            'cart' => 'user-a-cart-contents',
        ]);

        $sessionRef->getProperty('instance')->setValue(null, $session);

        return [$session, $cookies, $before, $sessionDataSpy];
    }

    function invokeCloseAuthSession(string $middlewareClass): void {
        $ref = new ReflectionClass($middlewareClass);
        $ref->getMethod('closeAuthSession')->invoke(null);
    }

    function resetAuthLogoutSpecSingletons(): void {
        $sessionRef = new ReflectionClass(Session::class);
        $sessionRef->getProperty('instance')->setValue(null, null);
        $sdRef = new ReflectionClass(SessionData::class);
        $sdRef->getProperty('instance')->setValue(null, null);
    }

    describe('closeAuthSession() rotates the session identifier on logout', function (): void {
        afterEach(function (): void {
            Mockery::close();
            resetAuthLogoutSpecSingletons();
        });

        foreach ([
            AuthMiddleware::class => 'AuthMiddleware',
            EmailAuthMiddleware::class => 'EmailAuthMiddleware',
        ] as $middlewareClass => $label) {
            describe("{$label}::closeAuthSession()", function () use ($middlewareClass): void {
                it('mints a NEW session cookie value distinct from the pre-logout one', function () use ($middlewareClass): void {
                    [$_, $cookies, $before] = buildAuthenticatedLogoutSession();

                    invokeCloseAuthSession($middlewareClass);

                    $after = $cookies->get(Session::COOKIE_NAME_SESSION)->getValue();
                    expect(strlen($after))->toBe(Session::COOKIE_VALUE_LEN);
                    expect($after)->not->toBe($before);
                });

                it('deletes the OLD session row so accumulated non-auth data does not survive', function () use ($middlewareClass): void {
                    [$session, $_, $_, $sessionDataSpy] = buildAuthenticatedLogoutSession();

                    invokeCloseAuthSession($middlewareClass);

                    // rotate() nulls the active row id so flush() targets the
                    // fresh identifier — the old id (42) no longer points at a
                    // live row.
                    $sessionIdAfter = (new ReflectionClass(Session::class))
                        ->getProperty('sessionId')
                        ->getValue($session);
                    expect($sessionIdAfter)->toBe(null);

                    // Row 42 held the "cart" value among its session_data;
                    // deleting it is what stops that value lingering under the
                    // now-superseded identifier a kiosk's next user would inherit.
                    $sessionDataSpy->shouldHaveReceived('deleteSessionAsync')
                        ->once()
                        ->with(42);
                });

                it('persists only AUTH_PHASE=PHASE_NULL under the new id (cart not carried forward)', function () use ($middlewareClass): void {
                    [$session] = buildAuthenticatedLogoutSession();

                    invokeCloseAuthSession($middlewareClass);

                    $changedValues = (new ReflectionClass(Session::class))
                        ->getProperty('changedValues')
                        ->getValue($session);

                    // flush() writes ONLY changedValues under the new id, so
                    // this is exactly what would land in the fresh anonymous row.
                    expect($changedValues)->toBe([AuthMiddleware::PHASE_KEY => AuthMiddleware::PHASE_NULL]);
                    expect(array_key_exists('cart', $changedValues))->toBe(false);
                    expect(array_key_exists(Account::SESSION_AUTH_LOGIN, $changedValues))->toBe(false);
                });

                it('leaves the in-memory auth keys cleared for the rest of this request', function () use ($middlewareClass): void {
                    [$session] = buildAuthenticatedLogoutSession();

                    invokeCloseAuthSession($middlewareClass);

                    expect($session->getValue(AuthMiddleware::PHASE_KEY))->toBe(AuthMiddleware::PHASE_NULL);
                    expect($session->getValue(Account::SESSION_AUTH_LOGIN))->toBe(null);
                    expect($session->getValue(AuthMiddleware::SESSION_AUTH_CODE))->toBe(null);
                });

                it('is safe on an already-anonymous session (no row to delete)', function () use ($middlewareClass): void {
                    [$session, $cookies, $before, $sessionDataSpy] = buildAuthenticatedLogoutSession();

                    // Simulate a logout hit on a session with no resolved row.
                    (new ReflectionClass(Session::class))
                        ->getProperty('sessionId')
                        ->setValue($session, null);

                    // Must not throw — rotate() guards the delete on
                    // `if (!empty($oldSessionId))` and still mints a fresh id.
                    invokeCloseAuthSession($middlewareClass);

                    $after = $cookies->get(Session::COOKIE_NAME_SESSION)->getValue();
                    expect(strlen($after))->toBe(Session::COOKIE_VALUE_LEN);
                    expect($after)->not->toBe($before);
                    $sessionDataSpy->shouldNotHaveReceived('deleteSessionAsync');
                });
            });
        }
    });
}
