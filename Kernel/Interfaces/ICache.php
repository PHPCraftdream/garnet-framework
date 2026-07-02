<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CacheException;
    use Throwable;

    interface ICache {
        /**
         * @param string $fileName
         * @return string
         * @throws CacheException
         */
        public function getActualFile(string $fileName): string;

        /**
         * @param string $fileName
         * @return string
         * @throws CacheException
         * @throws Throwable
         */
        public function getExistsFile(string $fileName): string;

        /**
         * @param string $fileName
         * @throws CacheException
         * @throws Throwable
         */
        public function refreshFile(string $fileName): void;
    }
}
