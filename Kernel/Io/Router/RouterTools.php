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

            // Normalize separators BEFORE the traversal check: RouterDevFile::tryFile()
            // does the same `\`/`/` -> DS normalization further down the pipeline, so a
            // backslash-based sequence (`..\`) must be caught here too — otherwise on
            // Windows it would sail through this check and only become `../` afterwards.
            $normalized = str_replace('\\', '/', $uri);

            if (str_contains($normalized, '../')) {
                return false;
            }

            return true;
        }
    }
}
