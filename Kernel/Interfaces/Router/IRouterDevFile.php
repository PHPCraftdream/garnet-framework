<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Router {
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use Psr\Http\Message\ResponseInterface;

    interface IRouterDevFile {
        /**
         * @param string $name
         * @param string $dir
         * @return void
         * @throws RouterException
         */
        public function addFilesDir(string $name, string $dir): void;

        /**
         * @param IGlobalReqParams $globals
         * @return ResponseInterface|null
         * @throws RouterException
         */
        public function dispatch(IGlobalReqParams $globals): ResponseInterface|null;
    }
}
