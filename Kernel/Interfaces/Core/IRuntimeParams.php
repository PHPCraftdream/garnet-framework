<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Core {
    interface IRuntimeParams {
        /**
         * @param string $paramsName
         * @param array|callable $params
         * @return void
         */
        public function set(
            string $paramsName,
            array|callable $params,
        ): void;

        /**
         * @param string $paramsName
         * @param array $appendToResult
         * @param bool $recursiveAppend
         * @return array
         */
        public function get(string $paramsName, array $appendToResult = [], bool $recursiveAppend = true): array;
    }
}
