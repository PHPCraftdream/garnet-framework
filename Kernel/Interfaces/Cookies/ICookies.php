<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Cookies {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    interface ICookies {
        public function has(string $name): bool;

        public function get(string $name): ICookie;

        public function setItNew(): ICookies;

        /**
         * @return array<ICookie>
         */
        public function getAll(): array;

        public function add(ICookie $cookie): ICookies;

        public function delete(string $name): ICookies;

        public function toResponse(ResponseInterface $response): ResponseInterface;

        public function fromResponse(ResponseInterface $response): ICookies;

        public function toRequest(RequestInterface $request): RequestInterface;

        public function fromRequest(RequestInterface $request): ICookies;

        public function fromServer(array $_server): ICookies;

        /**
         * @param array<string> $cookieStrings
         * @return ICookies
         */
        public function fromCookieStrings(array $cookieStrings): ICookies;

        /**
         * @param IGlobalReqParams $globals
         * @return ICookies
         */
        public function fromGlobals(IGlobalReqParams $globals): ICookies;
    }
}
