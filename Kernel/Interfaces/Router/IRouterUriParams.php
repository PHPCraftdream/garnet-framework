<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Router {
    interface IRouterUriParams {
        /**
         * @return string
         */
        public function getHttpMethod(): string;

        /**
         * @return string
         */
        public function getRouteVal(): string;

        /**
         * @param string $name
         * @param ?string $defaultVal
         * @return string|null
         */
        public function getUriParam(string $name, ?string $defaultVal = null): ?string;

        /**
         * @return array
         */
        public function getUriParams(): array;

        /**
         * @return string
         */
        public function getMethodName(): string;

        /**
         * @return array
         */
        public function getMethodParams(): array;

        /**
         * @param int $name
         * @param ?string $defaultVal
         * @return string|null
         */
        public function getMethodParam(int $name, ?string $defaultVal = null): ?string;
    }
}
