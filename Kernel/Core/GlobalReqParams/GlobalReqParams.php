<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    class GlobalReqParams implements IGlobalReqParams {
        protected function __construct(
            protected array $_server,
            protected array $_get,
            protected array $_post,
            protected array $_cookie,
            protected array $_files,
        ) {
        }

        /**
         * @param array $_server
         * @param array $_get
         * @param array $_post
         * @param array $_cookie
         * @param array $_files
         * @return IGlobalReqParams
         */
        public static function from(
            array $_server,
            array $_get,
            array $_post,
            array $_cookie,
            array $_files
        ): IGlobalReqParams {
            return new static($_server, $_get, $_post, $_cookie, $_files);
        }

        // --------------------------------------------------------------------------------------------------------------

        protected static ?array $post = null;

        /**
         * Clears the cached `$post` snapshot built by {@see currentPost()}.
         *
         * Harmless no-op requirement under traditional php-fpm / `php -S`
         * process models: each request runs in a fresh process (or the
         * static is naturally reset between worker cycles as this
         * framework uses them today), so nothing currently calls this.
         *
         * It exists for persistent-runtime integrations (RoadRunner,
         * Swoole, FrankenPHP, …) where the PHP process — and therefore
         * this class's statics — survives across multiple requests. Such
         * an integration MUST call `GlobalReqParams::reset()` at the start
         * of every request it handles, or a later request on the same
         * worker will silently reuse the previous request's POST body.
         */
        public static function reset(): void {
            static::$post = null;
        }

        /**
         * Builds the effective POST body for the current request, merging
         * in a JSON request body when present (see {@see mergeJsonBody()}).
         *
         * Not called anywhere inside the framework itself — this is part
         * of the app-contract API, meant to be called once from the app's
         * own `run_web.php` bootstrap entry point to build the `$_post`
         * array passed into {@see from()}:
         *   GlobalReqParams::from($_SERVER, $_GET, GlobalReqParams::currentPost(), $_COOKIE, $_FILES)
         *
         * Caches its result per request; on a persistent runtime
         * (RoadRunner, Swoole, FrankenPHP, …) the bootstrap MUST call
         * {@see reset()} before this on every request.
         */
        public static function currentPost(): array {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                return [];
            }

            if (static::$post !== null) {
                return static::$post;
            }

            $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $post = static::mergeJsonBody($_POST, $contentType, (string)file_get_contents('php://input'));

            static::$post = $post;

            return $post;
        }

        /**
         * Merges a JSON request body into $post — only when `$contentType`
         * actually declares `application/json`. `text/plain` is a CORS
         * "simple request" content type that skips preflight, so trusting
         * it here would let a cross-origin page smuggle a JSON body past
         * CSRF/origin checks that assume preflight already ran. A top-level
         * JSON array is dropped rather than merged: it can't be reshaped
         * into the associative $post shape without silently mangling it
         * (renumbered/flattened keys).
         *
         * @param array $post
         * @param string $contentType
         * @param string $rawBody
         * @return array
         */
        protected static function mergeJsonBody(array $post, string $contentType, string $rawBody): array {
            if (stripos($contentType, 'application/json') !== 0 || $rawBody === '') {
                return $post;
            }

            $postData = json_decode($rawBody, true);

            if (!is_array($postData) || array_is_list($postData)) {
                return $post;
            }

            return [...$post, ...$postData];
        }

        // --------------------------------------------------------------------------------------------------------------

        public function readServerValue(string $name, mixed $default = null): string|null {
            return array_key_exists($name, $this->_server) ? $this->_server[$name] : $default;
        }

        public function readServerAll(): array {
            return $this->_server;
        }

        // --------------------------------------------------------------------------------------------------------------

        public function readGetValue(string $name, mixed $default = null): mixed {
            return array_key_exists($name, $this->_get) ? $this->_get[$name] : $default;
        }

        public function readGetAll(): array {
            return $this->_get;
        }

        // --------------------------------------------------------------------------------------------------------------

        public function readPostValue(string $name, mixed $default = null): mixed {
            return array_key_exists($name, $this->_post) ? $this->_post[$name] : $default;
        }

        public function readPostAll(): array {
            return $this->_post;
        }

        // --------------------------------------------------------------------------------------------------------------

        public function readCookieValue(string $name, mixed $default = null): string|null {
            return array_key_exists($name, $this->_cookie) ? $this->_cookie[$name] : $default;
        }

        public function readCookieAll(): array {
            return $this->_cookie;
        }

        // --------------------------------------------------------------------------------------------------------------
        public function readFilesValue(string $name, mixed $default = null): mixed {
            return array_key_exists($name, $this->_files) ? $this->_files[$name] : $default;
        }

        public function readFilesAll(): array {
            return $this->_files;
        }

        // --------------------------------------------------------------------------------------------------------------

        public function getUri(): string {
            $uri = $this->_server['REQUEST_URI'] ?? '/';

            return $uri;
        }

        /** Method-override targets a real POST may escalate to via X-Http-Method. Never includes GET/POST. */
        private const OVERRIDABLE_METHODS = ['PUT', 'PATCH', 'DELETE'];

        public function httpMethod(): string {
            $realMethod = strtoupper((string)($this->_server['REQUEST_METHOD'] ?? 'GET'));
            $override = $this->_server['HTTP_X_HTTP_METHOD'] ?? null;

            // Only a genuine POST may be escalated to PUT/PATCH/DELETE (the
            // standard method-override use case for clients that can't send
            // those verbs directly). This can never be used to downgrade a
            // real POST into something CSRF/origin checks would skip.
            if ($realMethod === 'POST' && $override !== null && in_array(strtoupper($override), static::OVERRIDABLE_METHODS, true)) {
                return strtoupper($override);
            }

            return $realMethod;
        }

        public function isPost(): bool {
            return strtolower((string)($this->_server['REQUEST_METHOD'] ?? 'GET')) === 'post';
        }

        public function isEmptyPost(): bool {
            return empty($this->_post);
        }

        public function isGet(): bool {
            return strtolower((string)($this->_server['REQUEST_METHOD'] ?? 'GET')) === 'get';
        }

        public function isLocalhost(): bool {
            $serverName = $this->_server['SERVER_NAME'] ?? '';

            return $serverName === 'localhost' || $serverName === '127.0.0.1' || $serverName === '0.0.0.0';
        }

        public function isPhpServer(): bool {
            $serverSoft = $this->_server['SERVER_SOFTWARE'] ?? '';

            // PHP's built-in server reports "PHP/8.3.32 (Development Server)"
            // (slash-separated) in practice, not "PHP 8.3.32 ..." — accept
            // both since the exact wording isn't documented as stable.
            return !empty($serverSoft)
                && (str_starts_with($serverSoft, 'PHP/') || str_starts_with($serverSoft, 'PHP '));
        }

        public function isDev(): bool {
            // Optional explicit override: anything may set GARNET_DEV=1 to
            // force dev mode. Otherwise we fall back to the heuristic below.
            // The Node dev server proxies to a pool of `php -S` workers, each
            // of which reports SERVER_SOFTWARE "PHP … Development Server", so
            // the isPhpServer() check identifies a local dev request.
            if ((string)($this->_server['GARNET_DEV'] ?? '') === '1') {
                return true;
            }

            return $this->isLocalhost() && $this->isPhpServer();
        }

        public function ip(): string {
            $remoteAddr = $this->_server['REMOTE_ADDR'] ?? '';
            $ipForward = $this->_server['HTTP_X_FORWARDED_FOR'] ?? '';

            if ($ipForward === '' || !static::isTrustedProxy($remoteAddr)) {
                return $remoteAddr;
            }

            // X-Forwarded-For is a comma-separated chain that each proxy
            // appends to the RIGHT (nginx's $proxy_add_x_forwarded_for is
            // append, not overwrite), so the LEFTMOST element is whatever
            // the client sent — fully spoofable. Taking explode()[0] here
            // would let an attacker forge the IP shown in a "successful
            // login from IP ..." security email, defeating the very
            // trusted_proxies gate above. Walk the chain RIGHT to LEFT and
            // return the first entry that is NOT itself a trusted proxy:
            // that is the address that entered the trusted network, i.e.
            // the real client IP as observed by the outermost trusted hop.
            $parts = explode(',', $ipForward);
            $realClientIp = '';

            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $addr = trim($parts[$i]);

                if ($addr === '' || static::isTrustedProxy($addr)) {
                    continue;
                }

                $realClientIp = $addr;

                break;
            }

            // Every entry was a trusted proxy or empty (a malformed or
            // attacker-only chain): fall back to the TCP peer we actually
            // observed rather than returning an empty string or trusting
            // an attacker-controlled value. (explode() inherently handles
            // leading/trailing/multiple commas, so the old `strpos` gate
            // — which mis-parsed a comma-leading header via `> 0` — is no
            // longer needed.)
            return $realClientIp !== '' ? $realClientIp : $remoteAddr;
        }

        /**
         * X-Forwarded-For is attacker-controlled unless it was set (or
         * overwritten) by a proxy we actually run in front of the app —
         * otherwise any client can spoof it (e.g. to fake the IP shown in
         * a "successful login from IP ..." security email). Only honor it
         * when REMOTE_ADDR — the actual TCP peer — is in app.ini's
         * `trusted_proxies[]` allowlist. No config / no match => REMOTE_ADDR only.
         */
        protected static function isTrustedProxy(string $remoteAddr): bool {
            if ($remoteAddr === '') {
                return false;
            }

            try {
                $trusted = IniConfig::app()->paramArray('trusted_proxies', []);
            } catch (Throwable) {
                return false;
            }

            foreach ($trusted as $entry) {
                if ((string)$entry === $remoteAddr) {
                    return true;
                }
            }

            return false;
        }

        // --------------------------------------------------------------------------------------------------------------

        public static function makeGet4Tests(string $uri): IGlobalReqParams {
            $_server = $_SERVER;
            $_server['REQUEST_METHOD'] = 'GET';
            $_server['REQUEST_URI'] = $uri;

            return GlobalReqParams::from($_server, [], [], [], []);
        }

        public static function makePost4Tests(string $uri): IGlobalReqParams {
            $_server = $_SERVER;
            $_server['REQUEST_METHOD'] = 'POST';
            $_server['REQUEST_URI'] = $uri;

            return GlobalReqParams::from($_server, [], [], [], []);
        }
    }
}
