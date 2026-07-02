<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Clear application caches.
 *
 *   php garnet cache              # clear all (twig + file + opcache)
 *   php garnet cache:twig         # clear WorkDir/TwigCache/ only
 *   php garnet cache:file         # clear WorkDir/FileCache/ only
 *   php garnet cache:opcache      # opcache_reset() if available
 *
 * Same logic the `deploy` command already runs after migrations; exposed
 * separately so a hot-patch or template edit can be flushed without going
 * through the full maintenance dance.
 */
class GarnetCacheCommand {
    public static function run(string $command, array $args): void {
        $appName = GarnetEnv::requireAppName();
        // GARNET_WORKDIR_DIR-aware — on a deployed box WorkDir lives in the
        // runtime folder, not <app>/WorkDir, so this must match the web runtime
        // or we'd wipe the wrong (empty) cache and leave the real one stale.
        $workDir = GarnetEnv::workDir($appName);

        $do = match ($command) {
            'cache', 'cache:all' => ['twig', 'file', 'opcache'],
            'cache:twig' => ['twig'],
            'cache:file' => ['file'],
            'cache:opcache' => ['opcache'],
            default => null,
        };

        if ($do === null) {
            fwrite(STDERR, "\033[31mError:\033[0m unknown cache subcommand: {$command}\n");
            fwrite(STDERR, "  Available: cache, cache:twig, cache:file, cache:opcache\n");

            exit(2);
        }

        echo "\033[1m=== Garnet Cache Clear: {$appName} ===\033[0m" . PHP_EOL;

        $cleared = 0;

        foreach ($do as $kind) {
            $cleared += match ($kind) {
                'twig' => self::clearDir($workDir . DS . 'TwigCache', 'Twig cache') ? 1 : 0,
                'file' => self::clearDir($workDir . DS . 'FileCache', 'File cache') ? 1 : 0,
                'opcache' => self::clearOpcache() ? 1 : 0,
            };
        }

        if ($cleared === 0) {
            echo '  Nothing to clear.' . PHP_EOL;
        }
    }

    private static function clearDir(string $dir, string $label): bool {
        if (!is_dir($dir)) {
            echo "  \033[33m·\033[0m {$label}: directory missing ({$dir})" . PHP_EOL;

            return false;
        }
        self::rmdirContents($dir);
        echo "  \033[32m✓\033[0m {$label} cleared." . PHP_EOL;

        return true;
    }

    private static function clearOpcache(): bool {
        if (!function_exists('opcache_reset')) {
            echo "  \033[33m·\033[0m OPcache: opcache_reset() not available (CLI opcache likely off — restart php-fpm on prod)" . PHP_EOL;

            return false;
        }
        opcache_reset();
        echo "  \033[32m✓\033[0m OPcache reset." . PHP_EOL;

        return true;
    }

    private static function rmdirContents(string $dir): void {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
}
