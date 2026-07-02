<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Set writable permissions on the directories the runtime writes into.
 *
 *   perms:fix         chmod the standard writable dirs:
 *                       - WorkDir/LogJournal/
 *                       - WorkDir/FileCache/
 *                       - WorkDir/TwigCache/
 *                       - WorkDir/Upload/         (private uploads)
 *                       - <docroot>/upload/       (public uploads)
 *
 * Default mode is 0775 (group writable). Override with --mode=0777
 * on shared hostings where the php user is not the file owner.
 *
 * No-op on Windows (chmod has limited semantics there) — prints a notice
 * and exits.
 */
class GarnetPermsCommand {
    public static function run(string $command, array $args): void {
        match ($command) {
            'perms' => self::fix($args),  // bare `perms` == fix
            'perms:fix' => self::fix($args),
            default => self::help(),
        };

        exit(0);
    }

    private static function fix(array $args): void {
        $mode = 0o775;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--mode=')) {
                $raw = substr($arg, strlen('--mode='));
                // Accept 0775, 775, etc.
                $mode = octdec(ltrim($raw, '0')) ?: 0o775;
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            echo "\033[33mWarning:\033[0m chmod has limited effect on Windows. " .
                 'Run this on the production host.' . PHP_EOL;
        }

        $appName = GarnetEnv::requireAppName();
        $appDir = GarnetEnv::getAppDir($appName);
        $workDir = $appDir . DS . 'WorkDir';
        $publicDir = GarnetEnv::getPublicDir($appName);

        $targets = [
            'logs' => $workDir . DS . 'LogJournal',
            'log errors' => $workDir . DS . 'LogJournal' . DS . 'Errors',
            'log system' => $workDir . DS . 'LogJournal' . DS . 'System',
            'log routes' => $workDir . DS . 'LogJournal' . DS . 'Routes',
            'file cache' => $workDir . DS . 'FileCache',
            'twig cache' => $workDir . DS . 'TwigCache',
            'private upload' => $workDir . DS . 'Upload',
            'public upload' => $publicDir . DS . 'upload',
        ];

        echo "\033[1m=== Garnet Perms Fix ===\033[0m" . PHP_EOL;
        echo '  mode:    0' . decoct($mode) . PHP_EOL;
        echo "  app:     {$appDir}" . PHP_EOL;
        echo "  public:  {$publicDir}" . PHP_EOL . PHP_EOL;

        $okCount = 0;
        $missCount = 0;

        foreach ($targets as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, $mode, true);

                if (!is_dir($path)) {
                    echo "  \033[31mmiss\033[0m  [{$label}] {$path}  (create failed)" . PHP_EOL;
                    $missCount++;

                    continue;
                }
                echo "  \033[36mmkdir\033[0m [{$label}] {$path}" . PHP_EOL;
            }
            [$set, $fail] = self::chmodRecursive($path, $mode);

            if ($fail > 0) {
                echo "  \033[33mwarn\033[0m  [{$label}] {$set} ok, {$fail} failed" . PHP_EOL;
            } else {
                echo "  \033[32mok\033[0m    [{$label}] {$set} entries" . PHP_EOL;
            }
            $okCount++;
        }

        echo PHP_EOL . "  {$okCount} dirs processed";

        if ($missCount > 0) {
            echo ", {$missCount} missing";
        }
        echo '.' . PHP_EOL;
    }

    /**
     * @return array{0:int,1:int}  [ok-count, fail-count]
     */
    private static function chmodRecursive(string $root, int $mode): array {
        $ok = 0;
        $fail = 0;

        if (!@chmod($root, $mode)) {
            $fail++;
        } else {
            $ok++;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $item) {
            $p = $item->getPathname();
            // Files: drop the +x for non-executables — typical UNIX rule
            // is 0664 for files, 0775 for dirs. Derive file mode from
            // the dir mode by clearing the +x bits.
            $target = $item->isDir() ? $mode : ($mode & 0o666);

            if (!@chmod($p, $target)) {
                $fail++;
            } else {
                $ok++;
            }
        }

        return [$ok, $fail];
    }

    private static function help(): void {
        echo 'Usage: php garnet perms:fix [--mode=0775]' . PHP_EOL;
        echo '  chmod the standard writable dirs (LogJournal, FileCache,' . PHP_EOL;
        echo '  TwigCache, Upload, public/upload).' . PHP_EOL;
    }
}
