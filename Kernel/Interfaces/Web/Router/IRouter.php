<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router {
    use PHPCraftdream\Garnet\Kernel\Exceptions\Web\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;
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
