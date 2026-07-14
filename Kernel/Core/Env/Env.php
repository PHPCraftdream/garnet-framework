<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Env {
    use ReflectionClass;
    use ReflectionException;

    class Env {
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
            $dirItems = explode(DIRECTORY_SEPARATOR, self::resolveAnchorDir());
            $dirStr = '';
            $dirs = [];

            foreach ($dirItems as $ind => $item) {
                $dirStr .= $item . DIRECTORY_SEPARATOR;
                $dirs[] = $dirStr;
            }

            $dirs = array_reverse($dirs);
            $dirs = array_slice($dirs, 0, 6);

            $devFiles = ['.idea' => true, '.vs' => true, '.xcodeproj' => true, '.vscode' => true, '.atom' => true];

            foreach ($dirs as $dir) {
                $files = glob($dir . '/\.*');

                if (empty($files)) {
                    return false;
                }

                foreach ($files as $file) {
                    $fileBase = basename($file);

                    if (!empty($devFiles[$fileBase])) {
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
