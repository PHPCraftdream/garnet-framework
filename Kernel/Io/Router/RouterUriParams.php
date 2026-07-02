<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;

    class RouterUriParams implements IRouterUriParams {
        protected static string $routePrefix = '';

        /** @var array<int, string> List of URI prefixes that bypass the global route prefix. */
        protected static array $noPrefixPaths = [];

        /**
         * Set a global route prefix that will be stripped from incoming URIs.
         * Example: setRoutePrefix('/system') → /system/bookings dispatches as /bookings
         */
        public static function setRoutePrefix(string $prefix): void {
            static::$routePrefix = rtrim($prefix, '/');
        }

        public static function getRoutePrefix(): string {
            return static::$routePrefix;
        }

        /**
         * Register a URI prefix that should NEVER receive the global route
         * prefix. Useful for public-facing routes (landing, static pages,
         * webhooks) that must live at clean URLs even when the rest of the
         * app sits under e.g. /system.
         *
         * Example:
         *   setRoutePrefix('/system');
         *   registerNoPrefixPath('/page');
         *   // Now /page/view~slug is served as-is, /system/bookings is stripped to /bookings.
         */
        public static function registerNoPrefixPath(string $path): void {
            $path = '/' . trim($path, '/');

            if ($path === '/') {
                return;
            } // '/' would short-circuit everything; ignore.

            if (!in_array($path, static::$noPrefixPaths, true)) {
                static::$noPrefixPaths[] = $path;
            }
        }

        /** @return array<int, string> */
        public static function getNoPrefixPaths(): array {
            return static::$noPrefixPaths;
        }

        public static function isNoPrefixPath(string $uri): bool {
            $uri = '/' . trim($uri, '/');

            foreach (static::$noPrefixPaths as $p) {
                if ($uri === $p || str_starts_with($uri, $p . '/')) {
                    return true;
                }
            }

            return false;
        }

        protected array $uriParams = [];

        protected ?string $methodName = null;

        protected array $methodParams = [];

        protected string $routeVal = '/';

        protected string $httpMethod = 'GET';

        /**
         * @param array{string, string, string, string} $matches
         * @return string
         */
        protected function checkMatches(array $matches): string {
            $name = $matches[2];
            $this->uriParams[$name] = $matches[3];

            return "/{{$name}}";
        }

        /**
         * @param string $method
         * @return static
         */
        public static function makeClear(string $method): static {
            $result = new static();
            $result->httpMethod = $method;
            $result->routeVal = '/';
            $result->methodName = 'main';

            return $result;
        }

        /**
         * @param IGlobalReqParams $globals
         * @return static
         * @throws RouterException
         */
        public static function fromGlobals(IGlobalReqParams $globals): static {
            $uri = $globals->getUri();

            $pos = strpos($uri, '?');

            if ($pos !== false) {
                $uri = substr($uri, 0, $pos);
            }

            $uri = '/' . trim($uri, '/');

            // Strip the global route prefix if present. No-prefix paths
            // (e.g. /page) come in WITHOUT the prefix and bypass this
            // branch naturally; legacy URIs that still include the prefix
            // (e.g. /system/page/...) are stripped here so that both
            // shapes dispatch to the same controller.
            if (static::$routePrefix !== '' && str_starts_with($uri, static::$routePrefix)) {
                $uri = substr($uri, strlen(static::$routePrefix));

                if ($uri === '') {
                    $uri = '/';
                }
            }

            $result = new static();
            $result->httpMethod = $globals->httpMethod();

            // Find LAST /~ to separate URI params from method
            $lastTildePos = strrpos($uri, '/~');

            $explicitMethodName = false;

            if ($lastTildePos !== false) {
                $afterTilde = substr($uri, $lastTildePos + 2);

                // Check if there's a '/' after the last tilde (method params format)
                $slashPos = strpos($afterTilde, '/');

                if ($slashPos !== false) {
                    // Format: /path/~methodName/param1/param2
                    $result->methodName = substr($afterTilde, 0, $slashPos);
                    $result->methodParams = explode('/', substr($afterTilde, $slashPos + 1));
                    $explicitMethodName = true;
                    $uri = substr($uri, 0, $lastTildePos);
                } else {
                    // Format: /path/~paramName~paramValue or /path/~methodName
                    // Check if it's a URI param (~name~value) or method name (~name)
                    if (strpos($afterTilde, '~') !== false) {
                        // URI param: /path/~paramName~paramValue
                        // This will be processed by checkMatches later
                        // Don't modify uri - it already has the ~param~value pattern
                        // Just don't set a method name
                    } else {
                        // Method name without value: /path/~methodName
                        $result->methodName = $afterTilde;
                        $explicitMethodName = true;
                        $uri = substr($uri, 0, $lastTildePos);
                    }
                }
            }

            if (empty($result->methodName)) {
                $result->methodName = 'main';
            }

            // Only throw exception if user explicitly typed ~main
            if ($explicitMethodName && strtolower($result->methodName) === 'main') {
                throw new RouterException('DIRECT_CALL_MAIN_DISABLED');
            }

            $routeVal = preg_replace_callback('#(/([^~/?]+)~([^~/?]+))#', [$result, 'checkMatches'], $uri) . '';
            $result->routeVal = empty($routeVal) ? '/' : $routeVal;

            return $result;
        }

        /**
         * @return string
         */
        public function getHttpMethod(): string {
            return $this->httpMethod;
        }

        /**
         * @return string
         */
        public function getRouteVal(): string {
            return $this->routeVal;
        }

        /**
         * @return array
         */
        public function getUriParams(): array {
            return $this->uriParams;
        }

        /**
         * @param string|int $name
         * @param ?string $defaultVal
         * @return string|null
         */
        public function getUriParam(string|int $name, ?string $defaultVal = null): ?string {
            return array_key_exists($name, $this->uriParams) ? $this->uriParams[$name] : $defaultVal;
        }

        /**
         * @return string
         */
        public function getMethodName(): string {
            return $this->methodName ?? '';
        }

        /**
         * @return array
         */
        public function getMethodParams(): array {
            return $this->methodParams;
        }

        /**
         * @param string|int $name
         * @param ?string $defaultVal
         * @return string|null
         */
        public function getMethodParam(string|int $name, ?string $defaultVal = null): ?string {
            return array_key_exists($name, $this->methodParams) ? $this->methodParams[$name] : $defaultVal;
        }
    }
}
