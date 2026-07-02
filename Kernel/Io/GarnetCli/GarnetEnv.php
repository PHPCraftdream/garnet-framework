<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetEnv {
    /**
     * Resolve the app directory for $name. In a production bundle the
     * env var GARNET_APP_DIR points directly at the app folder (which
     * may live outside the traditional Apps/ subtree). In dev mode we
     * fall back to the classic GARNET_ROOT/Apps/<name> convention.
     */
    public static function getAppDir(string $name): string {
        $override = getenv('GARNET_APP_DIR');

        if ($override !== false && $override !== '') {
            return $override;
        }

        return GARNET_ROOT . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Canonical work-dir resolver — the SINGLE source of truth for where the
     * app's WorkDir lives, resolved exactly like BaseAppInit does at runtime.
     *
     * In a deploy bundle WorkDir is relocated into the runtime folder and
     * pointed at via GARNET_WORKDIR_DIR; otherwise it's <app>/WorkDir. CLI
     * commands that touch WorkDir (cache, maintenance, …) MUST use this, or
     * they read/write a different directory than the web runtime on a
     * deployed box. Returned WITHOUT a trailing separator.
     */
    public static function workDir(?string $name = null): string {
        $env = getenv('GARNET_WORKDIR_DIR');

        if ($env !== false && $env !== '') {
            return rtrim($env, '/\\');
        }
        $name ??= self::requireAppName();

        return self::getAppDir($name) . DIRECTORY_SEPARATOR . 'WorkDir';
    }

    public static function envFile(): string {
        $override = getenv('GARNET_APP_DIR');

        if ($override !== false && $override !== '') {
            return $override . DIRECTORY_SEPARATOR . '.env';
        }

        return GARNET_ROOT . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * Resolve the public (docroot) directory for $name.
     *
     * Resolution order:
     *   1. Bundle layout: BUNDLE_PUBLIC_DIR from .env (prod deploy)
     *   2. In-app Public/: Apps/<name>/Public/ (new canonical layout)
     *   3. Legacy: public/<name>/ (for apps not yet migrated)
     */
    public static function getPublicDir(string $name): string {
        $override = getenv('GARNET_APP_DIR');

        if ($override !== false && $override !== '') {
            $publicName = self::readEnvKey($override . DIRECTORY_SEPARATOR . '.env', 'BUNDLE_PUBLIC_DIR');

            if ($publicName !== '') {
                return dirname($override) . DIRECTORY_SEPARATOR . $publicName;
            }
            // App-mode without BUNDLE_PUBLIC_DIR: docroot is <appDir>/Public.
            $appOwn = $override . DIRECTORY_SEPARATOR . 'Public';

            if (is_dir($appOwn)) {
                return $appOwn;
            }
        }
        $newPath = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'Public';

        if (is_dir($newPath)) {
            return $newPath;
        }

        return GARNET_ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Read a single KEY=VALUE line out of an .env file. Minimal parser —
     * good enough for the bundle metadata keys (APP_NAME, BUNDLE_*).
     */
    public static function readEnvKey(string $file, string $key): string {
        if (!file_exists($file)) {
            return '';
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.+)$/', $line, $m)) {
                return trim($m[1], " \t\"'");
            }
        }

        return '';
    }

    /**
     * Return the BUNDLE_RUNTIME_DIR value from the runtime folder's .env.
     * In bundle layout GARNET_RUNTIME_DIR points at the runtime folder
     * (set by the garnet CLI that lives there); falls back to the app
     * dir's .env for legacy bundles that didn't have a runtime folder.
     */
    public static function readRuntimeDir(): ?string {
        $runtimeDir = getenv('GARNET_RUNTIME_DIR');

        if ($runtimeDir !== false && $runtimeDir !== '') {
            $val = self::readEnvKey($runtimeDir . DIRECTORY_SEPARATOR . '.env', 'BUNDLE_RUNTIME_DIR');

            return $val !== '' ? $val : null;
        }
        $val = self::readEnvKey(self::envFile(), 'BUNDLE_RUNTIME_DIR');

        return $val !== '' ? $val : null;
    }

    /**
     * Return the BUNDLE_WORKDIR_DIR value from the runtime folder's .env.
     * Same GARNET_RUNTIME_DIR discovery logic as readRuntimeDir().
     */
    public static function readWorkdirDir(): ?string {
        $runtimeDir = getenv('GARNET_RUNTIME_DIR');

        if ($runtimeDir !== false && $runtimeDir !== '') {
            $val = self::readEnvKey($runtimeDir . DIRECTORY_SEPARATOR . '.env', 'BUNDLE_WORKDIR_DIR');

            return $val !== '' ? $val : null;
        }
        $val = self::readEnvKey(self::envFile(), 'BUNDLE_WORKDIR_DIR');

        return $val !== '' ? $val : null;
    }

    public static function readAppNameFromRoot(string $garnetRoot): string {
        $file = $garnetRoot . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($file)) {
            return '';
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^APP_NAME\s*=\s*(.+)$/', $line, $m)) {
                return trim($m[1]);
            }
        }

        return '';
    }

    public static function readAppName(): string {
        $override = getenv('GARNET_APP_DIR');

        if ($override !== false && $override !== '') {
            return self::readAppNameFromRoot($override);
        }

        return self::readAppNameFromRoot(GARNET_ROOT);
    }

    public static function requireAppName(): string {
        $name = self::readAppName();

        if (empty($name)) {
            echo 'No active app. Run: php garnet app:use <AppName>' . PHP_EOL;

            exit(1);
        }

        if (!preg_match('#^[A-Za-z_][A-Za-z0-9_]+$#', $name)) {
            echo "Invalid APP_NAME in .env: \"{$name}\"" . PHP_EOL;

            exit(1);
        }

        $appDir = self::getAppDir($name);

        if (!is_dir($appDir)) {
            echo "App not found: {$appDir}" . PHP_EOL;

            exit(1);
        }

        return $name;
    }

    public static function writeAppNameFromRoot(string $garnetRoot, string $name): void {
        $file = $garnetRoot . DIRECTORY_SEPARATOR . '.env';
        $lines = [];
        $found = false;

        if (file_exists($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^APP_NAME\s*=/', $line)) {
                    $lines[] = "APP_NAME={$name}";
                    $found = true;
                } else {
                    $lines[] = $line;
                }
            }
        }

        if (!$found) {
            $lines[] = "APP_NAME={$name}";
        }

        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    public static function writeAppName(string $name): void {
        self::writeAppNameFromRoot(GARNET_ROOT, $name);
    }

    public static function listAppsFromRoot(string $garnetRoot): array {
        $appsDir = $garnetRoot . DIRECTORY_SEPARATOR . 'Apps';
        $apps = [];

        foreach (scandir($appsDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $appsDir . DIRECTORY_SEPARATOR . $entry;
            $classFile = $dir . DIRECTORY_SEPARATOR . $entry . '.php';

            if (is_dir($dir) && file_exists($classFile)) {
                $apps[] = $entry;
            }
        }

        sort($apps);

        return $apps;
    }

    public static function listApps(): array {
        return self::listAppsFromRoot(GARNET_ROOT);
    }

    /**
     * Load and return the active app instance.
     */
    public static function loadApp(?string $garnetRoot = null): object {
        // In app-mode GARNET_ROOT points at the Framework package, not the app
        // dir, so we must read APP_NAME from GARNET_APP_DIR's .env (same logic
        // as readAppName()). $garnetRoot stays the explicit override.
        $ds = DIRECTORY_SEPARATOR;

        if ($garnetRoot !== null) {
            $root = $garnetRoot;
        } else {
            $override = getenv('GARNET_APP_DIR');
            $root = ($override !== false && $override !== '') ? $override : GARNET_ROOT;
        }

        $appName = self::readAppNameFromRoot($root);

        if (empty($appName) || !preg_match('#^[A-Za-z_][A-Za-z0-9_]+$#', $appName)) {
            echo "Invalid APP_NAME in .env: \"{$appName}\"" . PHP_EOL;

            exit(1);
        }

        $autoloadFile = self::getAppDir($appName) . $ds . 'autoload.php';

        if (!file_exists($autoloadFile)) {
            echo "App not found: Apps/{$appName}" . PHP_EOL;

            exit(1);
        }

        require_once $autoloadFile;

        $className = "PHPCraftdream\\{$appName}\\{$appName}";
        $className::setPublicDirInit(self::getPublicDir($appName) . $ds);

        return new $className(false);
    }
}
