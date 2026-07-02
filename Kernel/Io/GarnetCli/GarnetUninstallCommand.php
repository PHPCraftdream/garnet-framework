<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Remove a deployed bundle from a host.
 *
 * Only meaningful in a bundle layout (where GARNET_APP_DIR is set). The
 * three sibling dirs are discovered from .env (BUNDLE_PUBLIC_DIR,
 * BUNDLE_FRAMEWORK_DIR) — written there at bundle time.
 *
 * Usage:
 *   php garnet uninstall              # show what would be removed, ask for typed-token confirmation
 *   php garnet uninstall --dry-run    # print the plan, don't touch anything
 *
 * Safety:
 *   - Refuses to run outside a bundle layout (no GARNET_APP_DIR override).
 *   - Refuses if BUNDLE_PUBLIC_DIR / BUNDLE_FRAMEWORK_DIR are missing or
 *     point at suspicious paths (absolute, contain '..', or resolve outside
 *     GARNET_ROOT).
 *   - Random 4-letter typed-token confirmation. There is intentionally NO
 *     bypass flag — the whole point is that an interactive human eyeballs
 *     the dry-run plan and types the freshly-printed token by hand.
 */
class GarnetUninstallCommand {
    public static function run(array $args): void {
        if (in_array('--yes', $args, true) || in_array('-y', $args, true)) {
            fwrite(STDERR, "\033[31mError:\033[0m --yes / -y is no longer supported. The typed-token confirmation is mandatory by design.\n");

            exit(2);
        }
        $dryRun = in_array('--dry-run', $args, true);

        $appDir = getenv('GARNET_APP_DIR');

        if ($appDir === false || $appDir === '') {
            echo "\033[31mError:\033[0m `uninstall` only runs in a bundle layout " .
                '(GARNET_APP_DIR not set). In dev, just delete the repo.' . PHP_EOL;

            exit(1);
        }
        $appDir = rtrim($appDir, DIRECTORY_SEPARATOR . '/');

        $envFile = $appDir . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($envFile)) {
            echo "\033[31mError:\033[0m .env not found at {$envFile}" . PHP_EOL;

            exit(1);
        }
        $env = self::parseEnv($envFile);

        $publicDirName = $env['BUNDLE_PUBLIC_DIR'] ?? '';
        $fwDirName = $env['BUNDLE_FRAMEWORK_DIR'] ?? '';
        $runtimeDirName = $env['BUNDLE_RUNTIME_DIR'] ?? '';

        if ($publicDirName === '' || $fwDirName === '') {
            echo "\033[31mError:\033[0m .env is missing BUNDLE_PUBLIC_DIR or BUNDLE_FRAMEWORK_DIR. " .
                'Re-bundle with current Garnet to get the metadata, or delete the install manually.' . PHP_EOL;

            exit(1);
        }

        $bundleRoot = dirname($appDir);
        $publicDir = $bundleRoot . DIRECTORY_SEPARATOR . $publicDirName;
        $fwDir = $bundleRoot . DIRECTORY_SEPARATOR . $fwDirName;

        // Sanity: refuse anything that escapes the bundle root.
        $toCheck = ['public' => $publicDirName, 'framework' => $fwDirName];

        if ($runtimeDirName !== '') {
            $toCheck['runtime'] = $runtimeDirName;
        }

        foreach ($toCheck as $label => $name) {
            if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
                echo "\033[31mError:\033[0m suspicious {$label} dir name in .env: {$name}" . PHP_EOL;

                exit(1);
            }
        }

        if ($runtimeDirName === '') {
            echo "\033[33mNote:\033[0m BUNDLE_RUNTIME_DIR not found in .env (legacy bundle). " .
                'The runtime folder will not be removed — delete it manually if needed.' . PHP_EOL;
        }

        echo "\033[1m=== Garnet Uninstall ===\033[0m" . PHP_EOL;
        echo "  bundle root: {$bundleRoot}" . PHP_EOL;
        echo '  will remove:' . PHP_EOL;
        $targets = [
            'docroot' => $publicDir,
            'framework' => $fwDir,
            'app' => $appDir,
        ];

        if ($runtimeDirName !== '') {
            $targets['runtime'] = $bundleRoot . DIRECTORY_SEPARATOR . $runtimeDirName;
        }

        foreach ($targets as $label => $path) {
            $exists = is_dir($path);
            $size = $exists ? self::humanBytes(self::sizeOf($path)) : '(missing)';
            echo "    - [{$label}] {$path}  {$size}" . PHP_EOL;
        }
        echo PHP_EOL;

        if ($dryRun) {
            echo '  (dry-run — nothing removed)' . PHP_EOL;

            return;
        }

        $token = self::randToken(4);
        echo "  Type \033[1;36m{$token}\033[0m to confirm: ";
        $line = trim((string)fgets(STDIN));

        if ($line !== $token) {
            echo '  Aborted.' . PHP_EOL;

            exit(1);
        }

        // Order: docroot first (lowest risk if it fails), framework, then
        // the app dir last — the running script itself lives inside the app
        // dir, but on Linux unlink on an open file just frees the inode at
        // close-time, so this works.
        foreach ($targets as $label => $path) {
            if (!is_dir($path)) {
                continue;
            }
            echo "  removing {$label}: {$path}" . PHP_EOL;
            self::rmrf($path);
        }

        echo PHP_EOL . "\033[32mUninstall complete.\033[0m" . PHP_EOL;
        echo '  You can now `rm` any leftover archive (e.g. MyApp.tar.gz) by hand.' . PHP_EOL;
    }

    /**
     * Random N-letter token for the typed-confirmation prompt. Uppercase
     * letters only, no easily-confused chars (I, O).
     */
    private static function randToken(int $len): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';

        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /**
     * Minimal .env parser — KEY=VALUE per line, # comments, ignores quotes.
     * Good enough for the two keys we write at bundle time.
     */
    private static function parseEnv(string $file): array {
        $out = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (!preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $out[$m[1]] = trim($m[2], " \t\"'");
        }

        return $out;
    }

    private static function rmrf(string $dir): void {
        if (!is_dir($dir)) {
            if (is_file($dir)) {
                @chmod($dir, 0o777);
                @unlink($dir);
            }

            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $p = $item->getPathname();
            @chmod($p, 0o777);
            $item->isDir() ? @rmdir($p) : @unlink($p);
        }
        @chmod($dir, 0o777);
        @rmdir($dir);
    }

    private static function sizeOf(string $dir): int {
        $bytes = 0;

        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $item) {
                if ($item->isFile()) {
                    $bytes += $item->getSize();
                }
            }
        } catch (Throwable) {
            return 0;
        }

        return $bytes;
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
}
