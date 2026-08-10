<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Env {
    use ReflectionClass;
    use ReflectionException;

    class Env {
        /**
         * Explicit override for {@see self::isDevDir()}: set to "dev" or
         * "prod" to short-circuit the IDE-marker-directory heuristic
         * entirely. Distinct from GlobalReqParams's per-request GARNET_DEV
         * (an HTTP/CLI request flag) — this one governs process-wide
         * "which checkout is this" detection, so it is read via getenv()
         * rather than $_SERVER.
         */
        public const ENV_MODE = 'GARNET_ENV';

        public static function isCmd(): bool {
            return php_sapi_name() === 'cli';
        }

        private static function resolveAnchorDir(): string {
            if (class_exists(\Composer\InstalledVersions::class)
                && \Composer\InstalledVersions::isInstalled('phpcraftdream/garnet-framework')
            ) {
                $root = \Composer\InstalledVersions::getRootPackage();
                $isRoot = $root['name'] === 'phpcraftdream/garnet-framework';

                if (!$isRoot) {
                    $installPath = \Composer\InstalledVersions::getInstallPath('phpcraftdream/garnet-framework');

                    if ($installPath !== null) {
                        return dirname($installPath, 3);
                    }
                }
            }

            return __DIR__;
        }

        /**
         * @return bool
         */
        public static function isDevDir(): bool {
            $mode = getenv(self::ENV_MODE);

            if ($mode === 'dev') {
                return true;
            }

            if ($mode === 'prod') {
                return false;
            }

            return self::hasDevMarkerAbove(self::resolveAnchorDir());
        }

        /**
         * Walks up from $anchorDir (inclusive), up to 6 levels, looking for
         * an IDE-marker directory in each ancestor. Extracted from
         * {@see self::isDevDir()} so the ancestor-walk itself is testable
         * against an arbitrary directory tree, independent of the
         * process's real install location and of GARNET_ENV.
         */
        public static function hasDevMarkerAbove(string $anchorDir): bool {
            $dirItems = explode(DIRECTORY_SEPARATOR, $anchorDir);
            $dirStr = '';
            $dirs = [];

            foreach ($dirItems as $item) {
                $dirStr .= $item . DIRECTORY_SEPARATOR;
                $dirs[] = $dirStr;
            }

            $dirs = array_reverse($dirs);
            $dirs = array_slice($dirs, 0, 6);

            $devDirNames = ['.idea', '.vs', '.xcodeproj', '.vscode', '.atom'];

            foreach ($dirs as $dir) {
                foreach ($devDirNames as $devDirName) {
                    if (is_dir($dir . $devDirName)) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * @var array<class-string, ReflectionClass<object>>
         */
        protected static array $reflections = [];

        /**
         * @template T of object
         * @param class-string<T> $className
         * @return ReflectionClass<T>
         * @throws ReflectionException
         */
        public static function getClassReflection(string $className): ReflectionClass {
            if (empty(static::$reflections[$className])) {
                static::$reflections[$className] = new ReflectionClass($className);
            }

            /**
             * @phpstan-var ReflectionClass<T> $result.
             */
            $result = static::$reflections[$className];

            return $result;
        }

        /**
         * @param class-string $className
         * @param class-string $interfaceName
         * @return bool
         * @throws ReflectionException
         */
        public static function classImplements(string $className, string $interfaceName): bool {
            $class = static::getClassReflection($className);

            return $class->implementsInterface($interfaceName);
        }
    }
}
