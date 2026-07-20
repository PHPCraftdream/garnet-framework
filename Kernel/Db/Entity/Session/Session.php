<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session {
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\SessionException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ISession;
    use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookies;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    class Session implements ISession {
        public const COOKIE_VALUE_LEN = 32;

        public const COOKIE_NAME_SESSION = 'session';

        /**
         * Hash a raw session token for DB storage/lookup.
         * SHA256 is used because tokens are already high-entropy random strings.
         */
        public static function hashToken(string $rawToken): string {
            return hash('sha256', $rawToken);
        }

        protected static ?ISession $instance = null;

        protected ICookies $cookies;

        protected bool $read = false;

        protected bool $changedCookies = false;

        protected ?string $sessionValue = null;

        protected ?int $sessionId = null;

        protected array $sessionData = [];

        protected array $changedValues = [];

        protected array $unsetValues = [];

        protected function __construct() {
        }

        public static function get(bool $pollData = true): ISession {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            if ($pollData) {
                static::$instance->readDataAsyncPollFinishAll();
            }

            return static::$instance;
        }

        public const CSRF_TOKEN = 'CSRF_TOKEN';

        protected string $csrfToken = '';

        /**
         * Decide whether the current request reached PHP over a secure (TLS)
         * channel, so that the session and CSRF cookies can be marked `Secure`.
         *
         * HTTPS is signalled by any of four sources, checked in order:
         *   1. `$_SERVER['HTTPS']` — set directly by the PHP SAPI. The legacy
         *      CGI value `'off'` (compared case-insensitively for robustness)
         *      explicitly means "not HTTPS" and must not count.
         *   2. `X-Forwarded-Proto: https` — sent by reverse proxies / CDNs that
         *      terminate TLS in front of PHP without forwarding the `HTTPS`
         *      FastCGI param, a very common panel/CDN configuration. The
         *      header may be a comma-separated list when the request passes
         *      through multiple proxies (e.g. `"https, http"`); by de-facto
         *      convention each hop appends its value to the right, so the
         *      FIRST element reflects the protocol the client used to reach
         *      the outermost proxy and is the one we must check.
         *   3. `X-Forwarded-Ssl: on` — the older equivalent of the above.
         *   4. `SERVER_PORT === '443'` — a last-resort heuristic.
         *
         * Risk asymmetry — why no trusted-proxy IP allowlist: a false positive
         * (deciding "HTTPS" when the hop to the client is actually plaintext)
         * only marks a cookie `Secure`, and the browser itself refuses to send
         * a `Secure` cookie over a genuinely non-TLS connection, so the worst
         * outcome is a broken feature, never a leaked credential. The reverse
         * error — the previous behaviour, which consulted only HTTPS/PORT and
         * so missed every behind-proxy deployment — omits `Secure` and would
         * let the cookie travel over HTTP if such a path ever existed. We
         * therefore bias towards detecting HTTPS and do not gate the proxy
         * headers on a client-IP allowlist — same asymmetric-risk reasoning
         * as the single-value case, decided in a prior fix; parsing the
         * multi-hop list does not change that trust model.
         */
        protected function isSecureRequest(): bool {
            $https = (string)($_SERVER['HTTPS'] ?? '');

            if ($https !== '' && strcasecmp($https, 'off') !== 0) {
                return true;
            }

            $forwardedProtoHeader = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
            $forwardedProtoFirst = strtolower(trim(explode(',', $forwardedProtoHeader)[0]));

            if ($forwardedProtoFirst === 'https') {
                return true;
            }

            $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));

            if ($forwardedSsl === 'on') {
                return true;
            }

            $port = (string)($_SERVER['SERVER_PORT'] ?? '');

            if ($port === '443') {
                return true;
            }

            return false;
        }

        /**
         * @return string
         * @throws Exception
         */
        public function touchCSRF(): string {
            if (!empty($this->csrfToken)) {
                return $this->csrfToken;
            }

            $sessionCookie = $this->cookies->get(static::CSRF_TOKEN);
            $csrfTokenCookie = $sessionCookie->getValue();

            if (!empty($csrfTokenCookie)) {
                $this->csrfToken = $csrfTokenCookie;

                return $csrfTokenCookie;
            }

            $csrfToken = StrTools::randomString(static::COOKIE_VALUE_LEN);
            $this->csrfToken = $csrfToken;
            $sessionCookie->setValue($csrfToken)
                ->rememberForever()
                ->setPath('/')
                ->setSecure($this->isSecureRequest())
                ->setHttpOnly(true)
                // SameSite=Lax, mirroring the session cookie (Cookie's default
                // is Strict). Without this the CSRF cookie is dropped on the
                // cross-site top-level navigation from a webmail magic-link
                // click — the session cookie (Lax) arrives but the CSRF one
                // doesn't, so the page's token and the cookie the browser
                // later replays on the same-site POST disagree → "CSRF token
                // validation failed", and the user can't sign in from email.
                // Protection still holds: Lax keeps the cookie off cross-site
                // POST forms and the token stays HttpOnly (unreadable to JS).
                ->setSameSiteLax();

            $this->changedCookies = true;

            return $csrfToken;
        }

        /**
         * @return string
         * @throws Exception
         */
        public static function touchCSRF_(): string {
            return static::get()->touchCSRF();
        }

        public function peekCSRF(): string {
            // Like touchCSRF, but does NOT mint a new cookie if none
            // exists. Reads the existing CSRF cookie value (if any) so
            // returning users with an established session keep working
            // through layouts that use peekCSRF_, and cold first-time
            // visitors don't get a CSRF cookie until they click consent.
            if (!empty($this->csrfToken)) {
                return $this->csrfToken;
            }
            $sessionCookie = $this->cookies->get(static::CSRF_TOKEN);
            $csrfTokenCookie = (string)$sessionCookie->getValue();

            if (!empty($csrfTokenCookie)) {
                $this->csrfToken = $csrfTokenCookie;

                return $csrfTokenCookie;
            }

            return '';
        }

        public static function peekCSRF_(): string {
            return static::get()->peekCSRF();
        }

        /**
         * @param RequestInterface $request
         * @return void
         * @throws Exception
         */
        public function readFromRequest(RequestInterface $request): void {
            $this->cookies = (new Cookies())->fromRequest($request);
            $this->read = true;
            $this->touchCookie(false);
        }

        /**
         * @param array $_server
         * @return void
         * @throws Exception
         */
        public function readFromServer(array $_server): void {
            $this->cookies = (new Cookies())->fromServer($_server);
            $this->read = true;
            $this->touchCookie(false);
        }

        /**
         * @param ResponseInterface $response
         * @return ResponseInterface
         */
        public function patchResponse(ResponseInterface $response): ResponseInterface {
            if (!$this->changedCookies) {
                return $response;
            }

            return $this->cookies->toResponse($response);
        }

        /**
         * @param bool $createCookie
         * @return void
         * @throws Exception
         */
        public function touchCookie(bool $createCookie = false): void {
            if (!empty($this->sessionValue)) {
                return;
            }

            $sessionCookie = $this->cookies->get(static::COOKIE_NAME_SESSION);
            $this->sessionValue = preg_replace('#[^A-z0-9]#', '', $sessionCookie->getValue() . '') . '';

            if (strlen($this->sessionValue) === static::COOKIE_VALUE_LEN) {
                return;
            }

            if ($createCookie) {
                // Use randomUtString to embed a microsecond timestamp prefix.
                // Plain randomString showed observable collisions between
                // independent dev-server requests on Windows + PHP built-in
                // server, which let two unrelated contexts share the same
                // session row in the DB.
                $this->sessionValue = StrTools::randomUtString(static::COOKIE_VALUE_LEN);
                $sessionCookie->setValue($this->sessionValue)
                    ->rememberForever()
                    ->setPath('/')
                    ->setSecure($this->isSecureRequest())
                    ->setHttpOnly(true)
                    // SameSite=Lax (not the default Strict) so the session
                    // cookie is sent on top-level navigations coming from
                    // a different site — magic-link clicks from webmail
                    // (gmail.com, outlook.com, …) would otherwise arrive
                    // at example.com WITHOUT the session cookie and the
                    // user lands on a fresh INPUT_EMAIL phase. CSRF
                    // protection still holds: we use a separate CSRF
                    // token on every POST, and Lax keeps the cookie off
                    // cross-site POST forms.
                    ->setSameSiteLax();
                $this->changedCookies = true;
            }
        }

        protected array $readDataAsyncLinks = [];

        public function readDataAsyncPollFinishAll(): void {
            if (!empty($this->readDataAsyncLinks)) {
                BenchmarkLog::log('before_session_readDataAsyncPoll');
                DbPool::pollLinks($this->readDataAsyncLinks);
                BenchmarkLog::log('after_session_readDataAsyncPoll');
            }
        }

        protected bool $readDataAsync = false;

        /**
         * @return void
         * @throws Exception
         */
        public function readDataAsync(): void {
            if (!$this->readDataAsync && !empty($this->sessionValue)) {
                $this->readDataAsync = true;
                $sd = SessionData::get();

                $this->readDataAsyncLinks[] = $sd->getDataAsync(static::hashToken($this->sessionValue), function ($data): void {
                    if (is_array($data)) {
                        [$this->sessionId, $this->sessionData] = $data;
                        $this->readDataAsync = true;
                    }

                    if ($data instanceof IDbMySQLiLink) {
                        $this->readDataAsyncLinks[] = $data;
                    }
                });
            }
        }

        /**
         * @param string $name
         * @param string $value
         * @param bool $clearUnset
         * @return void
         * @throws SessionException
         */
        public function setValue(string $name, string $value, bool $clearUnset = true): void {
            if (empty($this->sessionValue)) {
                $this->touchCookie(true);
            }

            if (strlen($name) > SessionDataTable::PARAM_LEN) {
                throw new SessionException("Wrong length of value name '{$name}' (max=" . SessionDataTable::PARAM_LEN . ')');
            }

            if (strlen($value) > SessionDataTable::VALUE_LEN) {
                throw new SessionException("Wrong length of value '{$value}' (max=" . SessionDataTable::VALUE_LEN . ')');
            }

            if ($clearUnset && array_key_exists($name, $this->unsetValues)) {
                unset($this->unsetValues[$name]);
            }

            $this->sessionData[$name] = $value;
            $this->changedValues[$name] = $value;
            $this->changedCookies = true;
        }

        /**
         * @param string $name
         * @return void
         */
        public function unsetValue(string $name): void {
            if (array_key_exists($name, $this->sessionData)) {
                unset($this->sessionData[$name], $this->changedValues[$name]);

                $this->unsetValues[$name] = true;
            }
        }

        /**
         * @param Array<string> $names
         * @return void
         */
        public function unsetValues(array $names): void {
            foreach ($names as $name) {
                if (array_key_exists($name, $this->sessionData)) {
                    unset($this->sessionData[$name], $this->changedValues[$name]);

                    $this->unsetValues[$name] = true;
                }
            }
        }

        /**
         * @param string $name
         * @param string|null $default
         * @return string|null
         */
        public function getValue(string $name, ?string $default = null): ?string {
            if (array_key_exists($name, $this->sessionData)) {
                return $this->sessionData[$name];
            }

            return $default;
        }

        /**
         * @return array
         */
        public function getAllData(): array {
            return $this->sessionData;
        }

        /**
         * @return string
         * @throws SessionException
         */
        public function getToken(): string {
            $token = $this->getValue('token');

            if (empty($token)) {
                $token = StrTools::randomString(static::COOKIE_VALUE_LEN);
                $this->setValue('token', $token);
            }

            return $token;
        }

        /**
         * @return void
         */
        public function flush(): void {
            if (empty($this->sessionValue)) {
                return;
            }

            // Copy keys before async callback to avoid changes
            $keys = array_keys($this->unsetValues);

            if (!empty($this->changedValues)) {
                $sessionData = SessionData::get();
                $sessionData->flush(static::hashToken($this->sessionValue), $this->sessionId, $this->changedValues, function ($sessionId) use ($sessionData, $keys): void {
                    // Convert to int as it may be returned as string from database
                    $sessionId = is_numeric($sessionId) ? (int)$sessionId : $sessionId;

                    if (is_int($sessionId)) {
                        $this->sessionId = $sessionId;
                        $sessionData->flushUnset($sessionId, $keys);
                    }
                });
            } elseif (!empty($keys) && !empty($this->sessionId)) {
                // No changed values, but we have unset values, so just delete them
                $sessionData = SessionData::get();
                $sessionData->flushUnset($this->sessionId, $keys);
            }
        }

        /**
         * @return bool
         */
        public function isReadCookies(): bool {
            return $this->read;
        }
    }
}
