<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\PSR4Autoload {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;

    class PSR4Autoload {
        public array $paths = [];

        /**
         * @param string $dir
         * @return string
         * @throws CommonException
         */
        public function makeDirPath(string $dir): string {
            $ds = DIRECTORY_SEPARATOR;

            $dir = str_replace(['\\', '/'], $ds, $dir);
            $dir = realpath($dir);

            if (!$dir) {
                throw new CommonException('Unknown dir: ' . $dir);
            }

            return rtrim($dir, $ds) . $ds;
        }

        /**
         * @param array $paths
         * @return void
         * @throws CommonException
         */
        public function setPaths(array $paths): void {
            foreach ($paths as $namespace => $path) {
                $this->paths[$namespace] = $this->makeDirPath($path);
            }
        }

        /**
         * @return void
         */
        public function register(): void {
            spl_autoload_register([$this, 'loadClass']);
        }

        /**
         * @param string $class
         * @return void
         */
        public function loadClass(string $class): void {
            foreach ($this->paths as $namespace => $path) {
                $found = $this->loadClassByNsAndPath($class, $namespace, $path);

                if (!$found) {
                    continue;
                }

                return;
            }
        }

        /**
         * @param string $class
         * @param string $namespace
         * @param string $path
         * @return bool
         */
        public function loadClassByNsAndPath(string $class, string $namespace, string $path): bool {
            $ds = DIRECTORY_SEPARATOR;
            $namespace = trim($namespace, '\\/');

            $len = strlen($namespace);

            if (substr($class, 0, $len) !== $namespace) {
                return false;
            }

            $className = substr($class, 1 + $len);
            $className = str_replace(['/', '\\'], $ds, $className);

            $path = $path . $className . '.php';

            if (!is_file($path)) {
                return false;
            }

            require_once $path;

            return true;
        }
    }
}
