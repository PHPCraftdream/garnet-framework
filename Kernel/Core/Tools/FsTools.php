<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Tools {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    class FsTools {
        /**
         * @param string $source
         * @param string $destination
         * @param callable|null $afterCopy
         * @param callable|null $beforeCopy
         * @param bool|null $replace
         * @return void
         * @throws CommonException
         */
        public static function copyDirectory(
            string $source,
            string $destination,
            ?callable $afterCopy = null,
            ?callable $beforeCopy = null,
            ?bool $replace = false,
        ): void {
            if (!is_dir($destination)) {
                mkdir($destination, 0o755, true);
            }

            $dir = dir($source);

            while ($dir && (false !== ($entry = $dir->read()))) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $src = $source . '/' . $entry;
                $dest = $destination . '/' . $entry;

                if (is_dir($src)) {
                    static::copyDirectory($src, $dest, $afterCopy, $beforeCopy, $replace);

                    continue;
                }

                if (!empty($beforeCopy)) {
                    $newDest = $beforeCopy($src, $dest);

                    if (!empty($newDest)) {
                        $dest = $newDest;
                    }
                }

                $dirName = dirname($dest);

                if (!is_dir($dirName)) {
                    mkdir($dirName, 0o755, true);
                }

                if ($replace && file_exists($dest)) {
                    unlink($dest);
                }

                if (!copy($src, $dest)) {
                    throw new CommonException("Error on copy: {$src} -> {$dest}");
                }

                if (!empty($afterCopy)) {
                    $afterCopy($src, $dest);
                }
            }

            $dir && $dir->close();
        }

        public const DS = DIRECTORY_SEPARATOR;

        /**
         * @param array<string> $pathItems
         * @return string
         */
        public static function makeDirPath(array $pathItems = []): string {
            return static::makeFilePath($pathItems) . DS;
        }

        public static function unlinkFile(string $path): void {
            if (is_file($path)) {
                unlink($path);
            }
        }

        /**
         * @param array<string> $pathItems
         * @return string
         */
        public static function makeFilePath(array $pathItems = []): string {
            $items = [];

            foreach ($pathItems as $key => $item) {
                $item = rtrim($item, '/\\');
                $item = str_replace(['\\', '/'], DS, $item);
                $items[$key] = $item;
            }

            return join(DS, $items);
        }

        /**
         * @param array $data
         * @return string
         */
        public static function dumArray(array $data): string {
            $str = var_export($data, true);

            if (!$str) {
                $str = '';
            }

            $str = preg_replace('/\d+\s*=>\s*/is', '', $str);

            if (!$str) {
                $str = '';
            }

            $str = preg_replace('/array\s*\(/', '[', $str);

            if (!$str) {
                $str = '';
            }

            $str = preg_replace('/\)/', ']', $str);

            if (!$str) {
                $str = '';
            }

            $str = preg_replace('/\s+/', '', $str);

            if (!$str) {
                $str = '';
            }

            $result = preg_replace('/,]/', ']', $str);

            if (!$result) {
                $result = '';
            }

            return $result;
        }

        /**
         * @param array $var
         * @return string
         */
        public static function exportArrToFile(array $var): string {
            $res = static::dumArray($var);

            return '<?php return ' . $res . ';';
        }
    }
}
