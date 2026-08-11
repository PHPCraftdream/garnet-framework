<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouter;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use Psr\Http\Message\ResponseInterface;

    class Router implements IRouter {
        /**
         * @var array{class-string, callable[], callable[]}[] $routes
         */
        protected array $routes = [];

        /**
         * @param Closure(IGlobalReqParams, IRouterUriParams):(ResponseInterface|string) $handlerNotFound.
         */
        public function __construct(
            protected Closure $handlerNotFound,
        ) {
        }

        /**
         * @return Closure
         */
        public function getHandlerNotFound(): Closure {
            return $this->handlerNotFound;
        }

        /**
         * @param Closure $handlerNotFound
         */
        public function setHandlerNotFound(Closure $handlerNotFound): void {
            $this->handlerNotFound = $handlerNotFound;
        }

        /**
         * @param string $uri
         * @param class-string $className
         * @param callable[] $callBefore
         * @param callable[] $callAfter
         * @return void
         * @throws RouterException
         */
        public function add(string $uri, string $className, array $callBefore = [], array $callAfter = []): void {
            $uri = '/' . trim($uri, '/');

            if (!empty($this->routes[$uri])) {
                throw new RouterException('Route already exists #A');
            }

            $this->routes[$uri] = [$className, $callBefore, $callAfter];
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $uriParams
         * @return ResponseInterface|string|null
         * @throws RouterException
         */
        public function dispatch(IGlobalReqParams $globals, IRouterUriParams $uriParams): ResponseInterface|string|null {
            $callParams = $this->routes[$uriParams->getRouteVal()] ?? null;

            if ($callParams === null) {
                return ($this->handlerNotFound)($globals, $uriParams);
            }

            $httpMethod = $uriParams->getHttpMethod();
            $methodName = $uriParams->getMethodName();
            $method = $methodName ? "{$httpMethod}__{$methodName}" : "{$httpMethod}__main";

            $methodCallStr = "{$callParams[0]}::{$method}";

            $callBefore = $callParams[1];
            $callAfter = $callParams[2];

            if (!empty($callBefore)) {
                BenchmarkLog::log('before_callBefore');

                foreach ($callBefore as $call) {
                    $before = $call($globals, $uriParams);

                    $callLog = match (true) {
                        is_array($call) => join('::', $call),
                        is_string($call) => $call,
                        default => 'closure',
                    };

                    BenchmarkLog::log('run_callBefore_' . $callLog);

                    if ($before) {
                        return $before;
                    }
                }

                BenchmarkLog::log('after_callBefore');
            }

            if (!is_callable($methodCallStr)) {
                return ($this->handlerNotFound)($globals, $uriParams);
            }

            /**
             * @phpstan-var callable-string $methodCallStr
             */
            $resultApi = $methodCallStr($globals, $uriParams);

            BenchmarkLog::log('after_controller');

            if (!empty($callAfter)) {
                foreach ($callAfter as $call) {
                    $after = $call($globals, $uriParams, $resultApi);

                    if ($after) {
                        $resultApi = $after;
                    }
                }

                BenchmarkLog::log('after_callAfter');
            }

            return $resultApi;
        }
    }
}
