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

        // Mirror GarnetServeWatchCommand/GarnetBuildCommand: COMMON_GARNET_WEB_DIR
        // is where rspack finds the local `garnet` CLI (app dir in app-mode) —
        // the bare GARNET_ROOT constant is the vendored framework package dir
        // in app-mode, which has no such file.
        $appDir = GarnetRunner::$appDir !== '' ? GarnetRunner::$appDir : GARNET_ROOT;
        putenv('COMMON_GARNET_WEB_DIR=' . $appDir . DIRECTORY_SEPARATOR);

        // Same reasoning as COMMON_GARNET_WEB_DIR above: __dirname inside the
        // .mjs would resolve into vendor/ under app-mode, so pass the app's
        // own WorkDir/LogJournal/serve/ explicitly instead of letting Node
        // derive a path from its own script location.
        putenv('GARNET_SERVE_LOG_DIR=' . GarnetEnv::workDir($appName) . DIRECTORY_SEPARATOR . 'LogJournal'
            . DIRECTORY_SEPARATOR . 'serve');

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
        // our ports, so a re-`serve` starts clean. Scoped to THIS app's own
        // --public dir so a second Garnet app's dev server (or unrelated PHP
        // processes on the machine) are left alone.
        self::killStale($isWindows, $publicDir);

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

        // Hand the terminal to Node (foreground) so Ctrl-C tears the whole
        // pool down via the .mjs SIGINT handler. proc_open with an argv
        // array bypasses the shell entirely — no quoting/escaping needed,
        // and no shell-metacharacter injection risk from app paths.
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $proc = proc_open($cmdArgs, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if ($proc === false) {
            echo 'Failed to start Node serve process' . PHP_EOL;

            exit(1);
        }

        exit(proc_close($proc));
    }

    /**
     * Kill leftover php / node serve processes from a previous run. The
     * Node server respawns its own workers, so we only need to clear the
     * field before launching a fresh one.
     *
     * Scoped to THIS app: every worker/server process we spawn carries the
     * app's own --public dir on its commandline (php -S ... -t <publicDir>
     * ...; node garnet-serve.mjs ... --public=<publicDir>), so filtering on
     * that string is precise even with multiple Garnet apps / dev servers
     * running on the same machine.
     */
    private static function killStale(bool $isWindows, string $publicDir): void {
        if ($isWindows) {
            $myPid = getmypid();
            $needle = self::wmicLikeEscape($publicDir);
            // php -S workers for THIS app carry -t <publicDir> on their
            // commandline; php-cgi.exe is not spawned anywhere in this
            // architecture (php -S workers only), so there's nothing to kill.
            self::exec('wmic process where "name=\'php.exe\' and commandline like \'%' . $needle . '%\' and not ProcessId=' . $myPid . '" call terminate 2>NUL');
            self::exec('wmic process where "name=\'phpd.exe\' and commandline like \'%' . $needle . '%\' and not ProcessId=' . $myPid . '" call terminate 2>NUL');
            // Only the garnet-serve node process for THIS app — match on the
            // script name AND this app's own --public dir via WMIC so we
            // don't nuke unrelated node tooling (rspack watch) or another
            // app's dev server.
            self::exec('wmic process where "name=\'node.exe\' and commandline like \'%garnet-serve.mjs%\' and commandline like \'%' . $needle . '%\'" call terminate 2>NUL');

            return;
        }

        // Unix: pkill matches a single POSIX extended-regex pattern against
        // the full commandline, so fold both signature (script name) and
        // scope (this app's own --public dir) into one pattern, regex-escaping
        // the path since it can contain characters that are ERE metachars
        // (., +, (, ), etc. — e.g. an app dir like "R&D" is fine, but "R.D"
        // or "app(v2)" would otherwise silently under- or over-match).
        $publicDirPattern = preg_quote($publicDir, '/');
        self::exec('pkill -f ' . escapeshellarg('garnet-serve\.mjs.*' . $publicDirPattern) . ' 2>/dev/null');
        // Worker commandline is `php ... -t <publicDir> <router>` — the
        // public dir precedes the router script, not the other way round.
        self::exec('pkill -f ' . escapeshellarg($publicDirPattern . '.*php-worker-router\.php') . ' 2>/dev/null');
    }

    /** Escape a value for a WMIC `LIKE` clause (single-quoted WQL string). */
    private static function wmicLikeEscape(string $value): string {
        // `\` is WQL's own escape character, so it must be doubled first —
        // otherwise a literal `\_` or `\%` inside a Windows path (very
        // common: `_`/`%` are legal path chars) would be parsed as an
        // escaped wildcard instead of two literal characters. Also, an
        // un-doubled trailing `\` (a path ending right before our `%`
        // wildcard) breaks the WQL parser outright ("Invalid query").
        $value = str_replace('\\', '\\\\', $value);

        return str_replace(
            ['%', '_', "'"],
            ['[%]', '[_]', "''"],
            $value,
        );
    }

    private static function exec(string $cmd): void {
        @exec($cmd . ' 2>&1', $output, $code);
    }
}
