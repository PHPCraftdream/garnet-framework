<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use Composer\InstalledVersions;
use ReflectionClass;
use Throwable;

/**
 * Central CLI entry point for Garnet commands.
 *
 * Supports two modes:
 *
 * **App-mode** (invoked from `Apps/<App>/garnet`):
 *   - `$appDir` points at the app directory (e.g. `Apps/MyApp/`).
 *   - Framework-dir is resolved via Composer `InstalledVersions` (the
 *     `phpcraftdream/garnet-framework` package).
 *   - `GARNET_APP_DIR` env is set so `GarnetEnv::getAppDir()` picks it up.
 *   - Commands like `app:list` that scan `GARNET_ROOT/Apps/` will only see
 *     the current app (no sibling `Apps/` directory exists in package mode).
 *     If `GARNET_TEAM_DIR` env is set, `app:list` can scan there instead.
 *
 * **Legacy-mode** (invoked from the monorepo root `garnet`):
 *   - `GARNET_ROOT` is already defined as the repo root.
 *   - `$appDir` is resolved from `.env` `APP_NAME` via `GarnetEnv`.
 *   - All commands work as before.
 *
 * @param string $appDir  Absolute path to the app directory, or repo root
 *                         when no specific app is active.
 * @param array  $argv    The global `$argv` array.
 */
class GarnetRunner {
    /** Absolute path to the active app directory (legacy: Apps/<Name>, app-mode: __DIR__ of local garnet wrapper). */
    public static string $appDir = '';

    /** Absolute path to the Framework package root (legacy: GARNET_ROOT/Framework, app-mode: vendor/.../garnet-framework). */
    public static string $frameworkDir = '';

    public static function main(string $appDir, array $argv): void {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        // --- Resolve Framework directory (dual-mode) ---
        $frameworkDir = self::resolveFrameworkDir();

        if (!defined('GARNET_ROOT')) {
            define('GARNET_ROOT', $frameworkDir);
        }

        // In legacy-mode GARNET_ROOT is the monorepo root; Framework lives in
        // GARNET_ROOT/Framework. In app-mode resolveFrameworkDir() already
        // points at the package root (vendor/.../garnet-framework/).
        $legacyFrameworkInRoot = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Framework';
        $isLegacy = is_dir($legacyFrameworkInRoot . DIRECTORY_SEPARATOR . 'FrontBuilder');
        self::$frameworkDir = $isLegacy
            ? $legacyFrameworkInRoot
            : $frameworkDir;
        self::$appDir = $appDir;

        // App-mode admin root: the /__garnet/ panel (AdminAuth token file +
        // `garnet` exec for app-switching) keys off $_ENV['GARNET_ROOT'].
        // In app-mode that is the APP dir — the docroot index.php sets the
        // same on the web side, so a CLI-issued admin token and the web
        // panel's read agree on one location. (Legacy keeps GARNET_ROOT =
        // monorepo root, set by the constant above.)
        if (!$isLegacy && !isset($_ENV['GARNET_ROOT'])) {
            $_ENV['GARNET_ROOT'] = $appDir;
        }

        if (!defined('GARNET_VERSION')) {
            $version = 'dev';

            try {
                if (InstalledVersions::isInstalled('phpcraftdream/garnet-framework')) {
                    $version = InstalledVersions::getPrettyVersion('phpcraftdream/garnet-framework') ?? 'dev';
                }
            } catch (Throwable) {
                // Composer runtime unavailable (e.g. dev checkout without vendor/)
            }
            define('GARNET_VERSION', $version);
        }

        // GARNET_APP_DIR is set by the caller (the local `garnet` wrapper in
        // app-mode), NOT by the runner. In legacy-mode the root `garnet`
        // intentionally leaves it unset so GarnetEnv reads APP_NAME from the
        // monorepo .env. Setting it here would force GarnetEnv to look for a
        // .env inside `$appDir`, which doesn't exist for legacy callers.

        // --- Exception handler ---
        set_exception_handler(static function (Throwable $e): void {
            global $argv;
            $debug = is_array($argv) && (in_array('--debug', $argv, true) || in_array('-v', $argv, true));
            $type = (new ReflectionClass($e))->getShortName();
            $msg = $e->getMessage();

            fwrite(STDERR, "\n\033[31m\xe2\x9c\x96 {$type}:\033[0m {$msg}\n");

            if (stripos($msg, 'command not found') !== false || stripos($msg, 'unknown command') !== false) {
                fwrite(STDERR, "  Run \033[36mphp garnet help\033[0m to see available commands.\n");
            }

            if ($debug) {
                fwrite(STDERR, "\n" . $e->getTraceAsString() . "\n");
            } else {
                fwrite(STDERR, "  (re-run with \033[36m--debug\033[0m for full stack trace)\n");
            }
            fwrite(STDERR, "\n");

            exit(1);
        });

        // --- Route command ---
        $command = $argv[1] ?? '';
        $args = array_slice($argv, 2);

        if (empty($command) || $command === 'help') {
            self::showHelp();

            exit(0);
        }

        // --- Garnet-level commands ---
        match (true) {
            $command === 'setup' => (static function () use ($args): void {
                GarnetSetupCommand::run($args);

                exit(0);
            })(),
            $command === 'app' || str_starts_with($command, 'app:') => GarnetAppCommand::run($command, $args),
            $command === 'admin' || str_starts_with($command, 'admin:') => GarnetAdminCommand::run($command, $args),
            $command === 'serve' => GarnetServeCommand::run($args),
            $command === 'serve:watch' => GarnetServeWatchCommand::run($args),
            $command === 'serve:debug' => GarnetServeDebugCommand::run($args),
            $command === 'build' => GarnetBuildCommand::run($args, false),
            $command === 'build:watch' => GarnetBuildCommand::run($args, true),
            $command === 'prepare' => GarnetPrepareCommand::run($args),
            $command === 'maintenance:remote' => (static function () use ($args): void {
                GarnetMaintenanceRemoteCommand::run($args);

                exit(0);
            })(),
            $command === 'maintenance' => (static function () use ($args): void {
                GarnetMaintenanceCommand::run($args);

                exit(0);
            })(),
            $command === 'deploy' => (static function () use ($args): void {
                GarnetDeployCommand::run($args);

                exit(0);
            })(),
            $command === 'bundle' => (static function () use ($args): void {
                GarnetBundleCommand::run($args);

                exit(0);
            })(),
            $command === 'uninstall' => (static function () use ($args): void {
                GarnetUninstallCommand::run($args);

                exit(0);
            })(),
            $command === 'config' || str_starts_with($command, 'config:') => GarnetConfigCommand::run($command, $args),
            $command === 'perms' || str_starts_with($command, 'perms:') => GarnetPermsCommand::run($command, $args),
            $command === 'db:backup' || $command === 'db:restore' => (static function () use ($command, $args): void {
                GarnetDbBackupCommand::run($command, $args);

                exit(0);
            })(),
            $command === 'db' || str_starts_with($command, 'db:') => GarnetDbWipeCommand::run($command, $args),
            $command === 'ssh' || str_starts_with($command, 'ssh:') => GarnetSshCommand::run($command, $args),
            $command === 'deploy:diff' || $command === 'deploy:diff:help' => GarnetDeployDiffCommand::run($command === 'deploy:diff:help' ? ['help'] : $args),
            $command === 'cache' || str_starts_with($command, 'cache:') => (static function () use ($command, $args): void {
                GarnetCacheCommand::run($command, $args);

                exit(0);
            })(),
            $command === 'sql' || $command === 'sql:help' => GarnetSqlCommand::run($command === 'sql:help' ? ['help'] : $args),
            $command === 'test:remote' => (static function () use ($args): void {
                GarnetTestRemoteCommand::run($args);

                exit(0);
            })(),
            str_starts_with($command, 'snapshot:') => (static function () use ($command, $args): void {
                GarnetSnapshotCommand::run($command, $args);

                exit(0);
            })(),
            $command === 'build:check' => (static function (): void {
                GarnetBuildCheckCommand::run();

                exit(0);
            })(),
            $command === 'migrate:status' => (static function (): void {
                GarnetMigrateStatusCommand::run();

                exit(0);
            })(),
            default => null, // fall through to app-level delegation
        };

        // --- App-level delegation (migration, custom commands, etc.) ---
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo "Unknown command: {$command}" . PHP_EOL;
            echo "Run 'php garnet help' for available commands." . PHP_EOL;

            exit(1);
        }

        // Rewrite $argv so IoRunConsole sees: [script, command, ...args]
        $GLOBALS['argv'] = array_merge([$runCmd, $command], $args);
        $GLOBALS['argc'] = count($GLOBALS['argv']);

        require $runCmd;

        exit(0);
    }

    /**
     * Resolve the Framework directory using dual-mode detection.
     *
     * 1. If the `phpcraftdream/garnet-framework` Composer package is installed,
     *    use its install path (works via junction/symlink in dev, real path in prod).
     * 2. Otherwise fall back to `__DIR__/../../../../` — i.e. from
     *    `Framework/Kernel/Io/GarnetCli/` up 4 levels to the monorepo root.
     *    In legacy mode GARNET_ROOT is already set by the root `garnet` script
     *    so this fallback is only for edge cases.
     */
    public static function resolveFrameworkDir(): string {
        // If GARNET_ROOT is already defined (legacy root garnet), honour it.
        if (defined('GARNET_ROOT')) {
            return GARNET_ROOT;
        }

        // Composer package mode
        if (class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('phpcraftdream/garnet-framework')) {
            $path = InstalledVersions::getInstallPath('phpcraftdream/garnet-framework');

            if ($path !== null && is_dir($path)) {
                return rtrim(str_replace('\\', '/', realpath($path)), '/');
            }
        }

        // Fallback: __DIR__ is Framework/Kernel/Io/GarnetCli → go up 4 levels
        $fallback = realpath(__DIR__ . '/../../../../');

        if ($fallback !== false) {
            return rtrim(str_replace('\\', '/', $fallback), '/');
        }

        // Last resort — use __DIR__ parent chain without realpath
        return dirname(__DIR__, 4);
    }

    public static function showHelp(): void {
        $appName = GarnetEnv::readAppName();
        $appLabel = $appName ?: '(none)';
        $version = defined('GARNET_VERSION') ? GARNET_VERSION : 'dev';

        echo <<<HELP

  Garnet CLI v{$version}
  Active app: {$appLabel}

  Usage: php garnet <command> [args]

  Garnet commands:
    help              Show this help message
    setup             Install the framework (composer + npm + node_modules junction)
    app               Show current active app
    app:list          List available apps
    app:use <Name>    Switch active app (+ run prepare)
    app:create <Name> Create a new app from template
    admin             Generate admin panel access token
    admin:build       Build admin panel assets
    admin:logout      Clear admin session
    serve             Start the Node front-server + PHP worker pool
    serve:watch       Start server + rspack watcher (dev mode)
    serve:debug       Start server with Browser MCP debug token
    build             Production build (rspack)
    build:watch       Development build with watch (rspack)
    prepare           Materialise runtime dirs + assets + app-info JSON
    maintenance       Maintenance mode (on/off/status)
    maintenance:remote Drive the prod box's maintenance mode over SSH (on/off/status)
    deploy            Full deploy (maintenance -> migrate -> cache -> off)
    bundle            Build production deploy bundle (public + framework + app)
    uninstall         Remove a deployed bundle from host (bundle-only)
    config:init       Seed WorkDir/Config/ from ConfigExample (non-destructive)
    perms:fix         chmod writable dirs (logs, caches, uploads)
    db:wipe           Drop every table in the DB (typed-token confirmation)
    db:backup         Dump the whole DB to WorkDir/Backups/ (auto before wipe)
    db:restore        Restore a dump (auto-backs-up the current DB first)
    deploy:diff       Push file changes from a git commit range via SSH
    cache             Clear all caches (twig + file + opcache)
    cache:twig        Clear WorkDir/TwigCache/ only
    cache:file        Clear WorkDir/FileCache/ only
    cache:opcache     opcache_reset() if available
    ssh               Run remote shell command (host from ssh.ini)
    ssh:put/get/test  Transfer files / verify connectivity
    build:check       Verify built frontend assets exist
    migrate:status    Show DB version vs target migration version
    test:remote       Run UI tests against a remote box (provision->playwright->teardown)
    snapshot:pull     Download a full prod data snapshot (db+config+logs+uploads)
    snapshot:collect  (server) gather snapshot data into a staging dir
    snapshot:pack     (server) tar+gzip a collected snapshot dir

  App commands (delegated to {$appLabel}):
    migration         Database migrations
    cron              Run cron tasks (email queue, etc.)
    help              App-level command help
    (any command registered by the active app)

  Serve options:
    --port=8001       Server port (default: 8001)
    --debug           Use phpd (Xdebug) instead of php
    --no-nginx        Skip nginx, PHP server only


HELP;
    }
}
