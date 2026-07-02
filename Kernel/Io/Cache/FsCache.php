<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cache {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CacheException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICache;
    use Throwable;

    /** @phpstan-consistent-constructor */
    class FsCache implements ICache {
        public const ENV_APP = 'ENV_APP';

        protected string $dirName;

        /**
         * @var ICache[]
         */
        protected static array $cacheInfo = [];

        /**
         * @var FileCache[]
         */
        protected static array $fileInfo = [];

        /**
         * @param string $dirName
         * @throws CacheException
         */
        protected function __construct(string $dirName) {
            if (!is_dir($dirName)) {
                throw new CacheException('Dir not found:' . $dirName);
            }

            if (!is_writable($dirName)) {
                throw new CacheException('Dir is not writable');
            }

            $this->dirName = rtrim($dirName, '\\/') . DIRECTORY_SEPARATOR;
        }

        /**
         * @param string $dirName
         * @param string $cacheName
         * @return ICache
         * @throws CacheException
         */
        public static function defineCache(string $dirName, string $cacheName = self::ENV_APP): ICache {
            if (!empty(static::$cacheInfo[$cacheName])) {
                throw new CacheException('Cache already defined: ' . $cacheName);
            }

            $obj = new static($dirName);
            static::$cacheInfo[$cacheName] = $obj;

            return $obj;
        }

        /**
         * @param string $fileName
         * @param int $ttlSeconds
         * @param callable():string $cacheBuilder
         * @return void
         * @throws CacheException
         */
        public static function defineFile(string $fileName, int $ttlSeconds, callable $cacheBuilder): void {
            $fileName = ltrim($fileName, '\\/');

            if (!empty(static::$fileInfo[$fileName])) {
                throw new CacheException('File already defined: ' . $fileName);
            }

            static::$fileInfo[$fileName] = new FileCache($fileName, $ttlSeconds, $cacheBuilder);
        }

        /**
         * @param string $cacheName
         * @return ICache
         * @throws CacheException
         */
        public static function getCache(string $cacheName = self::ENV_APP): ICache {
            if (empty(static::$cacheInfo[$cacheName])) {
                throw new CacheException('Cache not found: ' . $cacheName);
            }

            return static::$cacheInfo[$cacheName];
        }

        /**
         * @param string $fileName
         * @return string
         * @throws CacheException
         */
        public function getActualFile(string $fileName): string {
            $fileName = ltrim($fileName, '\\/');

            if (empty(static::$fileInfo[$fileName])) {
                throw new CacheException('File not defined: ' . $fileName);
            }

            $fileCache = static::$fileInfo[$fileName];

            $filePath = $this->dirName . $fileName;

            if (!is_file($filePath) || (time() - filemtime($filePath)) > $fileCache->getTtlSeconds()) {
                $data = $fileCache->build();
                file_put_contents($filePath, $data);

                return $data;
            }

            $result = file_get_contents($filePath);

            if ($result === false) {
                $error = error_get_last();
                $message = empty($error['message']) ? 'no message' : $error['message'] ;

                throw new CacheException("Error on read file {$fileName}: " . $message);
            }

            return $result;
        }

        /**
         * @param string $fileName
         * @return string
         * @throws CacheException
         * @throws Throwable
         */
        public function getExistsFile(string $fileName): string {
            $fileName = ltrim($fileName, '\\/');

            if (empty(static::$fileInfo[$fileName])) {
                throw new CacheException('File not defined: ' . $fileName);
            }

            $fileCache = static::$fileInfo[$fileName];
            $filePath = $this->dirName . $fileName;

            if (!is_file($filePath)) {
                $data = $fileCache->build();
                file_put_contents($filePath, $data);

                return $data;
            }

            $result = file_get_contents($filePath);

            if ($result === false) {
                $error = error_get_last();
                $message = empty($error['message']) ? 'no message' : $error['message'] ;

                throw new CacheException("Error on read file {$fileName}: " . $message);
            }

            return $result;
        }

        /**
         * @param string $fileName
         * @throws CacheException
         * @throws Throwable
         */
        public function refreshFile(string $fileName): void {
            $fileName = ltrim($fileName, '\\/');

            if (empty(static::$fileInfo[$fileName])) {
                throw new CacheException('File not defined: ' . $fileName);
            }

            $fileCache = static::$fileInfo[$fileName];

            $filePath = $this->dirName . $fileName;

            if (!is_file($filePath) || (time() - filemtime($filePath)) > $fileCache->getTtlSeconds()) {
                $data = $fileCache->build();
                file_put_contents($filePath, $data);
            }
        }
    }
}
