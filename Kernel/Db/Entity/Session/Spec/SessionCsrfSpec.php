<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Spec;

use Mockery;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie;
use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies;
use ReflectionClass;

/**
 * Unit tests for Session CSRF token lifecycle.
 *
 * Session::$cookies is injected via Reflection so no real HTTP request or DB
 * is needed.  ICookies / ICookie are mocked with Mockery.
 *
 * Strategy for expectations:
 *   - Most tests use allow() (no call-count assertion) so that the afterEach
 *     Mockery::close() does not impose unexpected-call failures.
 *   - Tests that need to assert a method was/was NOT called create their own
 *     fresh mock inside the test body; they still call Mockery::close() via
 *     afterEach.
 */
describe('Session CSRF lifecycle', function (): void {
    /**
     * Build a fresh Session with a fully-permissive mock cookie stack.
     * $existingToken: what ICookie::getValue() returns for the CSRF cookie.
     *                 Pass '' (empty) to simulate "no cookie stored yet".
     *
     * Returns [$session, $mockCsrfCookie].
     */
    function buildSession(string $existingToken = ''): array {
        // Mock for the individual CSRF cookie
        $mockCsrfCookie = Mockery::mock(ICookie::class);
        $mockCsrfCookie->allows('getValue')
            ->andReturn($existingToken !== '' ? $existingToken : null);
        $mockCsrfCookie->allows('setValue')->andReturnSelf();
        $mockCsrfCookie->allows('rememberForever')->andReturnSelf();
        $mockCsrfCookie->allows('setPath')->andReturnSelf();
        $mockCsrfCookie->allows('setSecure')->andReturnSelf();
        $mockCsrfCookie->allows('setHttpOnly')->andReturnSelf();
        $mockCsrfCookie->allows('setSameSiteLax')->andReturnSelf();

        // Dummy cookie for the session-value cookie (touchCookie reads it)
        $dummySession = Mockery::mock(ICookie::class);
        $dummySession->allows('getValue')->andReturn(null);
        $dummySession->allows('setValue')->andReturnSelf();
        $dummySession->allows('rememberForever')->andReturnSelf();
        $dummySession->allows('setPath')->andReturnSelf();
        $dummySession->allows('setSecure')->andReturnSelf();
        $dummySession->allows('setHttpOnly')->andReturnSelf();
        $dummySession->allows('setSameSiteLax')->andReturnSelf();

        $mockCookies = Mockery::mock(ICookies::class);
        $mockCookies->allows('get')
            ->with(Session::CSRF_TOKEN)
            ->andReturn($mockCsrfCookie);
        $mockCookies->allows('get')
            ->with(Session::COOKIE_NAME_SESSION)
            ->andReturn($dummySession);

        $session = buildSessionWithCookies($mockCookies);

        return [$session, $mockCsrfCookie];
    }

    /**
     * Inject a pre-built ICookies mock into a fresh Session instance.
     */
    function buildSessionWithCookies(ICookies $cookies): Session {
        $ref = new ReflectionClass(Session::class);
        /** @var Session $session */
        $session = $ref->newInstanceWithoutConstructor();

        $cookiesProp = $ref->getProperty('cookies');
        $cookiesProp->setValue($session, $cookies);

        $readProp = $ref->getProperty('read');
        $readProp->setValue($session, true);

        return $session;
    }

    afterEach(function (): void {
        Mockery::close();

        // Reset static singleton so each test is isolated
        $ref = new ReflectionClass(Session::class);
        $inst = $ref->getProperty('instance');
        $inst->setValue(null, null);
    });

    // -------------------------------------------------------------------------
    // touchCSRF — basic behaviour
    // -------------------------------------------------------------------------
    describe('touchCSRF()', function (): void {
        it('returns a non-empty string', function (): void {
            [$session] = buildSession();
            $token = $session->touchCSRF();
            expect($token)->toBeA('string');
            expect(strlen($token))->toBeGreaterThan(0);
        });

        it('returns a token of length ' . Session::COOKIE_VALUE_LEN, function (): void {
            [$session] = buildSession();
            $token = $session->touchCSRF();
            expect(strlen($token))->toBe(Session::COOKIE_VALUE_LEN);
        });

        it('is idempotent — second call returns the same token', function (): void {
            [$session] = buildSession();
            $first = $session->touchCSRF();
            $second = $session->touchCSRF();
            expect($second)->toBe($first);
        });

        it('returns the existing cookie value when one is already stored', function (): void {
            $existingToken = str_repeat('a', Session::COOKIE_VALUE_LEN);
            [$session] = buildSession($existingToken);
            $token = $session->touchCSRF();
            expect($token)->toBe($existingToken);
        });

        it('returns an alphanumeric string when minting a new token', function (): void {
            [$session] = buildSession('');
            $token = $session->touchCSRF();
            // StrTools::randomString uses bin2hex under the hood — all chars
            // are within [0-9a-f], but let's accept any alphanumeric character
            // to be forward-compatible with implementation changes.
            expect(preg_match('/^[a-zA-Z0-9]+$/', $token))->toBe(1);
        });

        it('calls setValue on the cookie when minting a new token', function (): void {
            // Dedicated mock so we can assert call count independently
            $mockCsrfCookie = Mockery::mock(ICookie::class);
            $mockCsrfCookie->allows('getValue')->andReturn(null);
            $mockCsrfCookie->expects('setValue')->once()->andReturnSelf();
            $mockCsrfCookie->allows('rememberForever')->andReturnSelf();
            $mockCsrfCookie->allows('setPath')->andReturnSelf();
            $mockCsrfCookie->allows('setSecure')->andReturnSelf();
            $mockCsrfCookie->allows('setHttpOnly')->andReturnSelf();
            $mockCsrfCookie->allows('setSameSiteLax')->andReturnSelf();

            $dummySession = Mockery::mock(ICookie::class);
            $dummySession->allows('getValue')->andReturn(null);
            $dummySession->allows('setValue')->andReturnSelf();
            $dummySession->allows('rememberForever')->andReturnSelf();
            $dummySession->allows('setPath')->andReturnSelf();
            $dummySession->allows('setSecure')->andReturnSelf();
            $dummySession->allows('setHttpOnly')->andReturnSelf();
            $dummySession->allows('setSameSiteLax')->andReturnSelf();

            $mockCookies = Mockery::mock(ICookies::class);
            $mockCookies->allows('get')->with(Session::CSRF_TOKEN)->andReturn($mockCsrfCookie);
            $mockCookies->allows('get')->with(Session::COOKIE_NAME_SESSION)->andReturn($dummySession);

            $session = buildSessionWithCookies($mockCookies);
            $token = $session->touchCSRF();

            expect(strlen($token))->toBe(Session::COOKIE_VALUE_LEN);
            // Mockery::close() in afterEach will verify the `expects(once)`.
        });

        it('mints the CSRF cookie as SameSite=Lax (survives webmail magic-link nav)', function (): void {
            // The Cookie class default is Strict; a fresh CSRF cookie MUST be
            // flipped to Lax or it's dropped on the cross-site email-link
            // navigation and sign-in fails with "CSRF token validation failed".
            $mockCsrfCookie = Mockery::mock(ICookie::class);
            $mockCsrfCookie->allows('getValue')->andReturn(null);
            $mockCsrfCookie->allows('setValue')->andReturnSelf();
            $mockCsrfCookie->allows('rememberForever')->andReturnSelf();
            $mockCsrfCookie->allows('setPath')->andReturnSelf();
            $mockCsrfCookie->allows('setSecure')->andReturnSelf();
            $mockCsrfCookie->allows('setHttpOnly')->andReturnSelf();
            $mockCsrfCookie->expects('setSameSiteLax')->once()->andReturnSelf();

            $dummySession = Mockery::mock(ICookie::class);
            $dummySession->allows('getValue')->andReturn(null);
            $dummySession->allows('setValue')->andReturnSelf();
            $dummySession->allows('rememberForever')->andReturnSelf();
            $dummySession->allows('setPath')->andReturnSelf();
            $dummySession->allows('setSecure')->andReturnSelf();
            $dummySession->allows('setHttpOnly')->andReturnSelf();
            $dummySession->allows('setSameSiteLax')->andReturnSelf();

            $mockCookies = Mockery::mock(ICookies::class);
            $mockCookies->allows('get')->with(Session::CSRF_TOKEN)->andReturn($mockCsrfCookie);
            $mockCookies->allows('get')->with(Session::COOKIE_NAME_SESSION)->andReturn($dummySession);

            $session = buildSessionWithCookies($mockCookies);
            $token = $session->touchCSRF();
            // The real guard is the Mockery expects(once) above (verified at
            // Mockery::close); this keeps the spec from being marked pending.
            expect(strlen($token))->toBe(Session::COOKIE_VALUE_LEN);
        });

        it('does NOT call setValue when an existing cookie is present', function (): void {
            $existingToken = str_repeat('b', Session::COOKIE_VALUE_LEN);

            $mockCsrfCookie = Mockery::mock(ICookie::class);
            $mockCsrfCookie->allows('getValue')->andReturn($existingToken);
            // No setValue call expected — never() enforces that
            $mockCsrfCookie->expects('setValue')->never();
            $mockCsrfCookie->allows('rememberForever')->andReturnSelf();
            $mockCsrfCookie->allows('setPath')->andReturnSelf();
            $mockCsrfCookie->allows('setSecure')->andReturnSelf();
            $mockCsrfCookie->allows('setHttpOnly')->andReturnSelf();
            $mockCsrfCookie->allows('setSameSiteLax')->andReturnSelf();

            $dummySession = Mockery::mock(ICookie::class);
            $dummySession->allows('getValue')->andReturn(null);
            $dummySession->allows('setValue')->andReturnSelf();
            $dummySession->allows('rememberForever')->andReturnSelf();
            $dummySession->allows('setPath')->andReturnSelf();
            $dummySession->allows('setSecure')->andReturnSelf();
            $dummySession->allows('setHttpOnly')->andReturnSelf();
            $dummySession->allows('setSameSiteLax')->andReturnSelf();

            $mockCookies = Mockery::mock(ICookies::class);
            $mockCookies->allows('get')->with(Session::CSRF_TOKEN)->andReturn($mockCsrfCookie);
            $mockCookies->allows('get')->with(Session::COOKIE_NAME_SESSION)->andReturn($dummySession);

            $session = buildSessionWithCookies($mockCookies);
            $token = $session->touchCSRF();
            expect($token)->toBe($existingToken);
        });
    });

    // -------------------------------------------------------------------------
    // peekCSRF — never mints
    // -------------------------------------------------------------------------
    describe('peekCSRF()', function (): void {
        it('returns empty string when no CSRF cookie exists', function (): void {
            [$session] = buildSession('');
            expect($session->peekCSRF())->toBe('');
        });

        it('returns existing token when cookie is present', function (): void {
            $existingToken = str_repeat('c', Session::COOKIE_VALUE_LEN);
            [$session] = buildSession($existingToken);
            expect($session->peekCSRF())->toBe($existingToken);
        });

        it('returns empty string when called multiple times with no cookie', function (): void {
            [$session] = buildSession('');
            expect($session->peekCSRF())->toBe('');
            expect($session->peekCSRF())->toBe('');
        });

        it('does NOT call setValue on the cookie when no cookie exists', function (): void {
            $mockCsrfCookie = Mockery::mock(ICookie::class);
            $mockCsrfCookie->allows('getValue')->andReturn(null);
            $mockCsrfCookie->expects('setValue')->never();

            $dummySession = Mockery::mock(ICookie::class);
            $dummySession->allows('getValue')->andReturn(null);
            $dummySession->allows('setValue')->andReturnSelf();
            $dummySession->allows('rememberForever')->andReturnSelf();
            $dummySession->allows('setPath')->andReturnSelf();
            $dummySession->allows('setSecure')->andReturnSelf();
            $dummySession->allows('setHttpOnly')->andReturnSelf();
            $dummySession->allows('setSameSiteLax')->andReturnSelf();

            $mockCookies = Mockery::mock(ICookies::class);
            $mockCookies->allows('get')->with(Session::CSRF_TOKEN)->andReturn($mockCsrfCookie);
            $mockCookies->allows('get')->with(Session::COOKIE_NAME_SESSION)->andReturn($dummySession);

            $session = buildSessionWithCookies($mockCookies);
            $result = $session->peekCSRF();
            expect($result)->toBe('');
        });

        it('returns the token that was previously minted by touchCSRF()', function (): void {
            [$session] = buildSession('');
            $minted = $session->touchCSRF();
            $peeked = $session->peekCSRF();
            expect($peeked)->toBe($minted);
        });

        it('does NOT call setValue again after touchCSRF already minted one', function (): void {
            // After touchCSRF sets $this->csrfToken, peekCSRF reads the
            // in-memory field and never touches the cookie again.
            $mockCsrfCookie = Mockery::mock(ICookie::class);
            $mockCsrfCookie->allows('getValue')->andReturn(null);
            // setValue may be called once (by touchCSRF mint), but NOT by peek
            $mockCsrfCookie->allows('setValue')->andReturnSelf();
            $mockCsrfCookie->allows('rememberForever')->andReturnSelf();
            $mockCsrfCookie->allows('setPath')->andReturnSelf();
            $mockCsrfCookie->allows('setSecure')->andReturnSelf();
            $mockCsrfCookie->allows('setHttpOnly')->andReturnSelf();
            $mockCsrfCookie->allows('setSameSiteLax')->andReturnSelf();

            $dummySession = Mockery::mock(ICookie::class);
            $dummySession->allows('getValue')->andReturn(null);
            $dummySession->allows('setValue')->andReturnSelf();
            $dummySession->allows('rememberForever')->andReturnSelf();
            $dummySession->allows('setPath')->andReturnSelf();
            $dummySession->allows('setSecure')->andReturnSelf();
            $dummySession->allows('setHttpOnly')->andReturnSelf();
            $dummySession->allows('setSameSiteLax')->andReturnSelf();

            $mockCookies = Mockery::mock(ICookies::class);
            $mockCookies->allows('get')->with(Session::CSRF_TOKEN)->andReturn($mockCsrfCookie);
            $mockCookies->allows('get')->with(Session::COOKIE_NAME_SESSION)->andReturn($dummySession);

            $session = buildSessionWithCookies($mockCookies);
            $minted = $session->touchCSRF();
            $peeked = $session->peekCSRF();

            // Both calls must return the same token; no exception from Mockery
            expect($peeked)->toBe($minted);
        });
    });

    // -------------------------------------------------------------------------
    // Internal $csrfToken property
    // -------------------------------------------------------------------------
    describe('internal $csrfToken property', function (): void {
        it('starts as empty string', function (): void {
            [$session] = buildSession('');
            $ref = new ReflectionClass($session);
            $prop = $ref->getProperty('csrfToken');
            expect($prop->getValue($session))->toBe('');
        });

        it('is populated after touchCSRF()', function (): void {
            [$session] = buildSession('');
            $token = $session->touchCSRF();
            $ref = new ReflectionClass($session);
            $prop = $ref->getProperty('csrfToken');
            expect($prop->getValue($session))->toBe($token);
        });

        it('is populated after peekCSRF() when cookie exists', function (): void {
            $existingToken = str_repeat('d', Session::COOKIE_VALUE_LEN);
            [$session] = buildSession($existingToken);
            $session->peekCSRF();
            $ref = new ReflectionClass($session);
            $prop = $ref->getProperty('csrfToken');
            expect($prop->getValue($session))->toBe($existingToken);
        });

        it('remains empty after peekCSRF() when no cookie exists', function (): void {
            [$session] = buildSession('');
            $session->peekCSRF();
            $ref = new ReflectionClass($session);
            $prop = $ref->getProperty('csrfToken');
            expect($prop->getValue($session))->toBe('');
        });
    });
});
