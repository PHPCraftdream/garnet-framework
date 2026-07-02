<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\AppInit {
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\BundleException;

    class InitTools {
        /**
         * @param string $method
         * @param string $file
         * @return string
         */
        public static function makeMethod(string $method, string $file): string {
            return "        public static function {$method}(): string {
            return '{$file}';
        }
";
        }

        /**
         * @param array $files
         * @param string $assetsDir
         * @param string $assetsWebPath
         * @return array
         */
        public static function makeMethodsFromFiles(array $files, string $assetsDir, string $assetsWebPath): array {
            $map = fn ($dest) => trim(StrTools::removePrefix($dest, $assetsDir), '/');
            $files = array_map($map, $files);
            $files = array_map(fn ($dest) => str_replace('\/', '/', $dest), $files);
            $files = array_filter($files, fn ($dest) => !str_ends_with(mb_strtolower($dest), '.keep'));

            $buildInfo = [];
            $methods = [];

            foreach ($files as $resultFile) {
                $method = preg_replace('#[^A-Za-z0-9]#', '_', $resultFile) . '';
                $method = preg_replace('#_{2,}#', '_', $method) . '';

                $buildInfo[$method] = $assetsWebPath . $resultFile;
            }

            ksort($buildInfo);

            foreach ($buildInfo as $method => $file) {
                $methods[] = InitTools::makeMethod($method, $file);
            }

            return $methods;
        }

        /**
         * @param array $methods
         * @param string $dir
         * @param string $name
         * @param string $namespace
         * @return void
         * @throws BundleException
         */
        public static function saveAssetsClass(array $methods, string $dir, string $name, string $namespace): void {
            $classTemplate = file_get_contents(dirname(__DIR__, 3) . '/Templates/CodeFiles/Class.template');

            if ($classTemplate === false) {
                throw new BundleException('Error on read Class.template');
            }

            $className = "{$name}AssetsGen";
            $fileDest = $dir . "{$className}.php";

            if (empty($methods)) {
                is_file($fileDest) && unlink($fileDest);

                return;
            }

            $result = join("\r\n", $methods);
            $result = rtrim($result);

            $write = str_replace('[[methods]]', $result, $classTemplate);
            $write = str_replace('[[namespace]]', $namespace, $write);
            $write = str_replace('[[className]]', "{$className}", $write);
            file_put_contents($fileDest, $write);
        }
    }
}
