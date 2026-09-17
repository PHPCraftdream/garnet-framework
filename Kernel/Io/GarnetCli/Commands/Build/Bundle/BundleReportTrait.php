<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\Bundle {
    use FilesystemIterator;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Счёт собранного и служебные мелочи вывода.
     */
    trait BundleReportTrait {
        private static function statTree(string $dir): array {
            if (!is_dir($dir)) {
                return [0, 0];
            }
            $files = 0;
            $bytes = 0;
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $item) {
                if ($item->isFile()) {
                    $files++;
                    $bytes += $item->getSize();
                }
            }

            return [$files, $bytes];
        }

        private static function humanBytes(int $bytes): string {
            $units = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            $b = (float)$bytes;

            while ($b >= 1024 && $i < count($units) - 1) {
                $b /= 1024;
                $i++;
            }

            return sprintf('%.2f %s', $b, $units[$i]);
        }

        private static function step(string $num, string $label): void {
            echo "[{$num}] {$label}" . PHP_EOL;
        }

        private static function fail(string $msg): void {
            echo "Error: {$msg}" . PHP_EOL;

            exit(1);
        }

        /**
         * Итог: сколько файлов и сколько весит.
         */
        private static function reportSummary(array $c): void {
            ['distApp' => $distApp] = $c;

            // Summary
            [$files, $bytes] = self::statTree($distApp);
            echo '=== Bundle complete ===' . PHP_EOL;
            echo "  Path:  {$distApp}" . PHP_EOL;
            echo "  Files: {$files}" . PHP_EOL;
            echo '  Size:  ' . self::humanBytes($bytes) . PHP_EOL;
        }
    }
}
