<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetBuildCheckCommand {
    public static function run(): void {
        $appName = GarnetEnv::requireAppName();
        $appDir = GarnetEnv::getAppDir($appName) . DS;
        $publicDir = GarnetEnv::getPublicDir($appName) . DS;
        // Framework *Gen.php classes live in the framework package's Bundle/
        // dir (dual-mode: GarnetRunner::$frameworkDir points at the package
        // root in app-mode, GARNET_ROOT/Framework in legacy).
        $frameworkBundleDir = GarnetRunner::$frameworkDir . DS . 'Bundle' . DS;

        echo "\033[1m=== Build Check: {$appName} ===\033[0m" . PHP_EOL . PHP_EOL;

        $allOk = true;

        // Discover Gen PHP classes in framework and app
        $genFiles = self::discoverGenFiles($frameworkBundleDir, $appDir);

        if (empty($genFiles)) {
            echo '  No *Gen.php files found.' . PHP_EOL;

            exit(0);
        }

        echo "\033[1m  Gen PHP classes:\033[0m" . PHP_EOL;

        foreach ($genFiles as $name => $path) {
            if (is_file($path)) {
                echo "    \033[32m[OK]\033[0m {$name}" . PHP_EOL;
            } else {
                echo "    \033[31m[MISSING]\033[0m {$name}" . PHP_EOL;
                echo "           \033[2m{$path}\033[0m" . PHP_EOL;
                $allOk = false;
            }
        }

        echo PHP_EOL;

        // Check referenced asset files from Gen classes
        echo "\033[1m  Referenced assets:\033[0m" . PHP_EOL;

        foreach ($genFiles as $name => $path) {
            if (!is_file($path)) {
                continue;
            }

            $assets = self::extractAssetPaths($path);

            foreach ($assets as $method => $webPath) {
                $filePath = $publicDir . ltrim(str_replace('/', DS, $webPath), DS);
                $altPath = $publicDir . ltrim(str_replace('/', DS, $webPath), DS);

                if (is_file($filePath)) {
                    $size = filesize($filePath);
                    echo "    \033[32m[OK]\033[0m {$webPath} \033[2m({$size} bytes)\033[0m" . PHP_EOL;
                } elseif (is_file($altPath)) {
                    $size = filesize($altPath);
                    echo "    \033[32m[OK]\033[0m {$webPath} \033[2m({$size} bytes)\033[0m" . PHP_EOL;
                } else {
                    echo "    \033[31m[MISSING]\033[0m {$webPath}" . PHP_EOL;
                    $allOk = false;
                }
            }
        }

        echo PHP_EOL;

        if ($allOk) {
            echo "\033[32m  [OK] All built assets verified.\033[0m" . PHP_EOL;
        } else {
            echo "\033[31m  [ERROR] Some assets are missing. Run the build and commit.\033[0m" . PHP_EOL;

            exit(1);
        }
    }

    /**
     * Find *Gen.php files in framework bundle and app directories.
     * @return array<string, string> name => path
     */
    private static function discoverGenFiles(string $frameworkBundleDir, string $appDir): array {
        $files = [];

        // Framework Gen files
        foreach (glob($frameworkBundleDir . '*Gen.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }

        // App Gen files (search Foreground, Dashboard, Common dirs)
        foreach (['Foreground', 'Dashboard', 'Common'] as $subDir) {
            $dir = $appDir . $subDir . DS;

            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '*Gen.php') ?: [] as $path) {
                $files[basename($path, '.php')] = $path;
            }
        }

        return $files;
    }

    /**
     * Parse a Gen PHP file and extract static method return values (asset paths).
     * @return array<string, string> method => path
     */
    private static function extractAssetPaths(string $filePath): array {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return [];
        }

        $assets = [];

        // Match: public static function name(): string { return '/path/to/asset'; }
        if (preg_match_all(
            "/public\s+static\s+function\s+(\w+)\(\):\s*string\s*\{\s*return\s+'([^']+)'/",
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $assets[$match[1]] = $match[2];
            }
        }

        return $assets;
    }
}
