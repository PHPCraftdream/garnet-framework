<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cache {
    use Closure;

    class FileCache {
        /**
         * @var Closure(): string
         */
        protected Closure $cacheBuilder;

        /**
         * @param string $fileName
         * @param int $ttlSeconds
         * @param callable(): string $cacheBuilder
         */
        public function __construct(protected string $fileName, protected int $ttlSeconds, callable $cacheBuilder) {
            $this->cacheBuilder = $cacheBuilder(...);
        }

        /**
         * @return string
         */
        public function getFileName(): string {
            return $this->fileName;
        }

        /**
         * @return int
         */
        public function getTtlSeconds(): int {
            return $this->ttlSeconds;
        }

        /**
         * @return string
         */
        public function build(): string {
            $cacheBuilder = $this->cacheBuilder;

            return $cacheBuilder();
        }
    }
}
