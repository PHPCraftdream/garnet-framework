<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetServeCommand {
    /**
     * `php garnet serve` — start the cross-platform Node dev server.
     *
     * The server (tooling/server/garnet-serve.mjs) listens on the public
     * port, serves static files from the app's Public/ dir, and proxies
     * dynamic requests to a pool of N `php -S` workers it manages itself.
     * Per-worker DB isolation (Playwright) is preserved via the
     * `X-Test-Worker` header routing, exactly as the old nginx pool did.
     *
     * Node is already a prerequisite (the frontend build runs on rspack),
     * so this drops the vendored nginx binary without adding a new
     * dependency. On any OS it's the same command.
     *
     * Flags:
     *   --port=N      public listen port (default 8001)
     *   --workers=N   php -S worker pool size (default 32; min 1)
     *   --debug       use the `phpd` binary for the workers
     */
    public static function run(array $args): void {
        $appName = GarnetEnv::requireAppName();

        $publicPort = 8001;
        $poolBasePort = 8011;
        $workers = 32;
        $debug = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--port=')) {
                $publicPort = (int)substr($arg, 7);
            } elseif (str_starts_with($arg, '--workers=')) {
                $workers = max(1, (int)substr($arg, 10));
            } elseif ($arg === '--debug') {
                $debug = true;
            }
        }

        putenv('COMMON_GARNET_WEB_DIR=' . GARNET_ROOT . DIRECTORY_SEPARATOR);

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $phpBin = $debug ? 'phpd' : 'php';
        $publicDir = GarnetEnv::getPublicDir($appName);

        $serveScript = GarnetRunner::$frameworkDir . DIRECTORY_SEPARATOR . 'tooling'
            . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'garnet-serve.mjs';

        if (!is_file($serveScript)) {
            echo "Node serve script not found: {$serveScript}" . PHP_EOL;

            exit(1);
        }

        // Tear down any leftover workers / a previous Node server bound to
        // our ports, so a re-`serve` starts clean.
        self::killStale($isWindows);

        $nodeBin = getenv('GARNET_NODE') ?: 'node';

        $cmdArgs = [
            $nodeBin,
            $serveScript,
            '--port=' . $publicPort,
            '--workers=' . $workers,
            '--base-port=' . $poolBasePort,
            '--public=' . $publicDir,
            '--php-bin=' . $phpBin,
        ];

        if ($debug) {
            $cmdArgs[] = '--debug';
        }

        $cmd = implode(' ', array_map(self::quote(...), $cmdArgs));

        // Hand the terminal to Node (foreground) so Ctrl-C tears the whole
        // pool down via the .mjs SIGINT handler.
        passthru($cmd, $code);

        exit($code);
    }

    /**
     * Kill leftover php / node serve processes from a previous run. The
     * Node server respawns its own workers, so we only need to clear the
     * field before launching a fresh one.
     */
    private static function killStale(bool $isWindows): void {
        if ($isWindows) {
            $myPid = getmypid();
            self::exec("taskkill /IM php.exe     /F /FI \"PID ne {$myPid}\" 2>NUL");
            self::exec("taskkill /IM phpd.exe    /F /FI \"PID ne {$myPid}\" 2>NUL");
            self::exec('taskkill /IM php-cgi.exe /F 2>NUL');
            // Only the garnet-serve node process — match on the script name
            // via WMIC so we don't nuke unrelated node tooling (rspack watch).
            self::exec('wmic process where "name=\'node.exe\' and commandline like \'%garnet-serve.mjs%\'" call terminate 2>NUL');

            return;
        }

        // Unix: pkill by the worker-router / serve-script signatures.
        self::exec("pkill -f 'garnet-serve.mjs' 2>/dev/null");
        self::exec("pkill -f 'php-worker-router.php' 2>/dev/null");
    }

    private static function quote(string $arg): string {
        // Windows `cmd` and POSIX shells both accept double-quoted args for
        // the paths/values we pass (no embedded double quotes here).
        return str_contains($arg, ' ') ? '"' . $arg . '"' : $arg;
    }

    private static function exec(string $cmd): void {
        @exec($cmd . ' 2>&1', $output, $code);
    }
}
