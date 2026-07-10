<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;

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

        public static function currentPost(): array {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                return [];
            }

            if (static::$post !== null) {
                return static::$post;
            }

            $post = $_POST;
            $jsonPost = file_get_contents('php://input');

            if (!empty($jsonPost)) {
                $postData = (array)json_decode($jsonPost);

                if (!empty($postData)) {
                    $post = [...$post, ...$postData];
                }
            }

            static::$post = $post;

            return $post;
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

        public function httpMethod(): string {
            return $this->_server['HTTP_X_HTTP_METHOD'] ?? ($this->_server['REQUEST_METHOD'] ?? 'GET');
        }

        public function isPost(): bool {
            return strtolower($this->httpMethod()) === 'post';
        }

        public function isEmptyPost(): bool {
            return empty($this->_post);
        }

        public function isGet(): bool {
            return strtolower($this->httpMethod()) === 'get';
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
            $ipForward = $this->_server['HTTP_X_FORWARDED_FOR'] ?? false;

            if (!$ipForward) {
                return $this->_server['REMOTE_ADDR'];
            }

            if (strpos($ipForward, ',') > 0) {
                $addr = explode(',', $ipForward);

                return trim($addr[0]);
            }

            return $this->_server['HTTP_X_FORWARDED_FOR'];
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
