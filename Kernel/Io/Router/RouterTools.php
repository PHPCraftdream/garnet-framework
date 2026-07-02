<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    class RouterTools {
        public static function makeDirPath(array $pathItems = []): string {
            $items = [];

            foreach ($pathItems as $key => $item) {
                $item = rtrim($item . '', '/\\');
                $item = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $item);
                $items[$key] = $item;
            }

            return join(DIRECTORY_SEPARATOR, $items) . DIRECTORY_SEPARATOR;
        }

        public static function makeFilePath(array $pathItems = []): string {
            $items = [];

            foreach ($pathItems as $key => $item) {
                $item = rtrim($item . '', '/\\');
                $item = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $item);
                $items[$key] = $item;
            }

            return join(DIRECTORY_SEPARATOR, $items);
        }

        /**
         * @param string $uri
         * @return bool
         */
        public static function checkUriPathFile(string $uri): bool {
            if (str_contains($uri, "\0")) {
                return false;
            }

            if (str_contains($uri, '../')) {
                return false;
            }

            return true;
        }
    }
}
