<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Router {
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use Psr\Http\Message\ResponseInterface;

    interface IRouter {
        /**
         * @param string $uri
         * @param class-string $className
         * @return void
         * @throws RouterException
         */
        public function add(string $uri, string $className): void;

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $uriParams
         * @return ResponseInterface|string|null
         * @throws RouterException
         */
        public function dispatch(IGlobalReqParams $globals, IRouterUriParams $uriParams): ResponseInterface|string|null;
    }
}
