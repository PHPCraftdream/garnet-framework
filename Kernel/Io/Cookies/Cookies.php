<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cookies {
    use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    class Cookies implements ICookies {
        use StringUtilTrait;

        public const SET_COOKIE_HEADER = 'Set-Cookie';

        public const COOKIE_HEADER = 'Cookie';

        /**
         * @var array<string, ICookie>
         */
        protected array $cookies = [];

        /**
         * @param array<ICookie> $cookies
         */
        public function __construct(array $cookies = []) {
            foreach ($cookies as $cookie) {
                $this->cookies[(string)$cookie->getName()] = $cookie;
            }
        }

        public function has(string $name): bool {
            return isset($this->cookies[$name]);
        }

        public function get(string $name): ICookie {
            if (!$this->has($name)) {
                $this->cookies[$name] = $this->newCookie($name)->setItNew();
            }

            return $this->cookies[$name];
        }

        /**
         * @return array<ICookie>
         */
        public function getAll(): array {
            return array_values($this->cookies);
        }

        public function setItNew(): ICookies {
            foreach ($this->cookies as $cookie) {
                $cookie->setItNew();
            }

            return $this;
        }

        public function add(ICookie $cookie): ICookies {
            $this->cookies[(string)$cookie->getName()] = $cookie;

            return $this;
        }

        public function delete(string $name): ICookies {
            if (!$this->has($name)) {
                return $this;
            }

            unset($this->cookies[$name]);

            return $this;
        }

        public function toResponse(ResponseInterface $response): ResponseInterface {
            $existing = $response->getHeader(self::SET_COOKIE_HEADER);
            $preserved = [];

            foreach ($existing as $cookieStr) {
                // Drop any pre-existing line whose name this jar manages: the
                // jar's own state (fresh value, or nothing at all) must win.
                // Lines for names we don't manage are left untouched.
                if (isset($this->cookies[$this->extractCookieName($cookieStr)])) {
                    continue;
                }

                $preserved[] = $cookieStr;
            }

            $response = $response->withoutHeader(self::SET_COOKIE_HEADER);

            foreach ($preserved as $cookieStr) {
                $response = $response->withAddedHeader(self::SET_COOKIE_HEADER, $cookieStr);
            }

            foreach ($this->cookies as $cookie) {
                $cookieStr = $cookie->__toString();

                if (empty($cookieStr)) {
                    continue;
                }

                $response = $response->withAddedHeader(self::SET_COOKIE_HEADER, $cookieStr);
            }

            return $response;
        }

        /**
         * Decode the cookie NAME (the part before the first `=`) from a raw
         * `Set-Cookie` header line, using the same splitting the framework
         * uses to parse cookies so it matches $this->cookies keys exactly.
         */
        protected function extractCookieName(string $cookieStr): string {
            $parts = $this->splitOnAttributeDelimiter($cookieStr);
            [$name] = $this->splitCookiePair($parts[0] ?? '');

            return $name;
        }

        public function fromResponse(ResponseInterface $response): ICookies {
            $cookieStrings = $response->getHeader(self::SET_COOKIE_HEADER);
            $this->fromCookieStrings($cookieStrings);

            return $this;
        }

        public function toRequest(RequestInterface $request): RequestInterface {
            $cookieString = implode('; ', $this->cookies);

            if (!empty($cookieString)) {
                $request = $request->withHeader(self::COOKIE_HEADER, $cookieString);
            }

            return $request;
        }

        public function fromRequest(RequestInterface $request): ICookies {
            $cookieString = $request->getHeaderLine(self::COOKIE_HEADER);
            $this->parseCookieString($cookieString);

            return $this;
        }

        public function fromServer(array $_server): ICookies {
            $cookieString = $_server['HTTP_COOKIE'] ?? '';
            $this->parseCookieString($cookieString);

            return $this;
        }

        protected function newCookie(?string $name = null): ICookie {
            return new Cookie($name);
        }

        protected function parseCookieString(string $string): ICookies {
            $cookiesStrArr = $this->splitOnAttributeDelimiter($string);

            foreach ($cookiesStrArr as $cookieStr) {
                $cookie = $this->newCookie()->setOld();
                $cookie->parse($cookieStr);
                $cookie->startObserveChanges();
                $this->add($cookie);
            }

            return $this;
        }

        /**
         * @param array<string> $cookieStrings
         * @return ICookies
         */
        public function fromCookieStrings(array $cookieStrings): ICookies {
            $this->cookies = [];

            foreach ($cookieStrings as $cookieStr) {
                $cookie = $this->newCookie()->setOld();
                $cookie->parse($cookieStr);
                $cookie->startObserveChanges();

                $this->cookies[(string)$cookie->getName()] = $cookie;
            }

            return $this;
        }

        /**
         * @param IGlobalReqParams $globals
         * @return ICookies
         */
        public function fromGlobals(IGlobalReqParams $globals): ICookies {
            $cookieStr = $globals->readServerValue('HTTP_COOKIE') ?? '';

            return $this->parseCookieString($cookieStr);
        }
    }
}
