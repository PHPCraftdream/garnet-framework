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
            self::killStaleWindows($publicDir, getmypid());

            return;
        }

        self::killStaleUnix($publicDir);
    }

    /**
     * Windows kill path.
     *
     * Microsoft REMOVED `wmic` as a default component starting with Windows 11
     * 24H2 and Windows Server 2025 (it's now an optional RSAT feature you must
     * re-enable). The previous wmic-only implementation silently did nothing on
     * those versions — a leftover worker held the port and `serve` failed with
     * no diagnostic pointing at the real cause.
     *
     * We now prefer PowerShell's `Get-CimInstance Win32_Process`, which ships
     * with every currently-supported Windows (including 24H2 / Server 2025),
     * and fall back to the legacy `wmic` WQL query when PowerShell is not on
     * the PATH or its invocation fails — keeping older systems and stripped-
     * down images working.
     */
    private static function killStaleWindows(string $publicDir, int $myPid): void {
        $wmicNeedle = self::wmicLikeEscape($publicDir);

        // php -S workers for THIS app carry -t <publicDir> on their
        // commandline; php-cgi.exe is not spawned anywhere in this
        // architecture (php -S workers only), so there's nothing to kill.
        self::execWithFallback(
            self::psKillCommand('php.exe', [$publicDir], $myPid),
            'wmic process where "name=\'php.exe\' and commandline like \'%' . $wmicNeedle
                . '%\' and not ProcessId=' . $myPid . '" call terminate 2>NUL',
        );
        // --debug workers use phpd.exe instead.
        self::execWithFallback(
            self::psKillCommand('phpd.exe', [$publicDir], $myPid),
            'wmic process where "name=\'phpd.exe\' and commandline like \'%' . $wmicNeedle
                . '%\' and not ProcessId=' . $myPid . '" call terminate 2>NUL',
        );
        // Only the garnet-serve node process for THIS app — match on the
        // script name AND this app's own --public dir via the commandline so
        // we don't nuke unrelated node tooling (rspack watch) or another
        // app's dev server.
        self::execWithFallback(
            self::psKillCommand('node.exe', ['garnet-serve.mjs', $publicDir], null),
            'wmic process where "name=\'node.exe\' and commandline like \'%garnet-serve.mjs%\''
                . ' and commandline like \'%' . $wmicNeedle . '%\'" call terminate 2>NUL',
        );
    }

    /**
     * Unix kill path.
     *
     * `pkill -f` matches a single POSIX extended regular expression (ERE)
     * against the full commandline, so both the signature (script name) and
     * the scope (this app's own --public dir) are folded into one ERE pattern.
     * The path is escaped via escapeEre() since it can contain ERE
     * metacharacters (., +, (, ), etc. — e.g. an app dir like "R&D" is fine,
     * but "R.D" or "app(v2)" would otherwise silently under- or over-match).
     */
    private static function killStaleUnix(string $publicDir): void {
        $publicDirPattern = self::escapeEre($publicDir);
        self::exec('pkill -f ' . escapeshellarg('garnet-serve\.mjs.*' . $publicDirPattern) . ' 2>/dev/null');
        // Worker commandline is `php ... -t <publicDir> <router>` — the
        // public dir precedes the router script, not the other way round.
        self::exec('pkill -f ' . escapeshellarg($publicDirPattern . '.*php-worker-router\.php') . ' 2>/dev/null');
    }

    /**
     * Build a `powershell -EncodedCommand` invocation that finds Win32_Process
     * rows matching the given image name AND every commandline LIKE pattern,
     * then force-terminates them by PID.
     *
     * Uses `-EncodedCommand` (base64 of UTF-16LE) rather than an inline
     * `-Command "..."` string: PHP exec() on Windows routes through cmd.exe,
     * which would otherwise expand `%VAR%` sequences in the pattern and require
     * fragile nested-quote escaping between cmd.exe and powershell.exe. Base64
     * sidesteps the outer shell entirely; the pattern is still -like-escaped
     * via psLikeEscape() for PowerShell's own wildcard syntax.
     *
     * @param string[] $likePatterns Commandline substrings to -like-match (paths are wildcard-escaped; literal signatures like "garnet-serve.mjs" pass through as-is).
     * @param ?int $excludePid Process ID to skip (the running `serve` command itself), or null.
     */
    private static function psKillCommand(string $name, array $likePatterns, ?int $excludePid): string {
        $conditions = ['$_.Name -eq \'' . $name . '\''];

        foreach ($likePatterns as $pattern) {
            $conditions[] = '$_.CommandLine -like \'*' . self::psLikeEscape($pattern) . '*\'';
        }

        if ($excludePid !== null) {
            $conditions[] = '$_.ProcessId -ne ' . $excludePid;
        }

        $script = 'Get-CimInstance Win32_Process | Where-Object { '
            . implode(' -and ', $conditions)
            . ' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }';

        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE'));

        return 'powershell -NoProfile -NonInteractive -EncodedCommand ' . $encoded;
    }

    /**
     * Escape a value for a PowerShell `-like` pattern inside a single-quoted
     * string literal — the form used by psKillCommand().
     *
     * Three layers, applied in order so they don't interfere:
     *  1. Double any literal backtick: backtick is `-like`'s OWN escape
     *     character, so a lone backtick would be swallowed as an escape
     *     introducer rather than matched literally.
     *  2. Escape the `-like` wildcard characters (`* ? [ ]`) by prefixing a
     *     backtick, so a literal path char can't widen or narrow the match.
     *  3. Double any single-quote: it delimits the surrounding PowerShell
     *     string literal, so `'` must become `''` to stay literal.
     *
     * `$` and `"` are NOT special inside a single-quoted PowerShell string, so
     * they need no escaping here (single-quoted is chosen over double-quoted
     * for exactly this reason).
     */
    private static function psLikeEscape(string $value): string {
        $value = str_replace('`', '``', $value);
        $value = str_replace(['*', '?', '[', ']'], ['`*', '`?', '`[', '`]'], $value);

        return str_replace("'", "''", $value);
    }

    /**
     * Escape a string for use as a literal inside a POSIX extended regular
     * expression (ERE), as matched by `pkill -f`.
     *
     * Only the characters that are ACTUAL ERE metacharacters are escaped —
     * NOT the broader set that PHP's preg_quote() escapes for PCRE. preg_quote()
     * also backslash-escapes `= ! < > : - #` (and the delimiter), which have no
     * special meaning in ERE; backslash-escaping them is formally undefined
     * behavior per POSIX (GNU grep/pkill tolerate it in practice, but BSD/macOS
     * behavior is not guaranteed). Escaping only the real metacharacters keeps
     * the pattern portable and avoids relying on undefined-but-tolerant behavior.
     */
    private static function escapeEre(string $value): string {
        // Backslash is first so the escape-backslashes added for the other
        // metacharacters below are NOT themselves re-doubled.
        return str_replace(
            ['\\', '.', '*', '+', '?', '(', ')', '{', '}', '|', '^', '$', '[', ']'],
            ['\\\\', '\\.', '\\*', '\\+', '\\?', '\\(', '\\)', '\\{', '\\}', '\\|', '\\^', '\\$', '\\[', '\\]'],
            $value,
        );
    }

    /**
     * Escape a value for a WMIC `LIKE` clause (single-quoted WQL string).
     *
     * WQL escaping rules — verified live against `Get-CimInstance -Query` and
     * the real `wmic.exe` fallback tool by spawning a process whose commandline
     * carried every special char and confirming the escaped pattern matches it:
     *   - `\` is WQL's string-literal escape character. Only `\\` and `\'` are
     *     valid escapes; every other `\X` is rejected as "Invalid query".
     *   - an embedded single-quote is escaped `\'` (backslash-quote), NOT SQL
     *     doubling — `''` is a hard syntax error in WQL.
     *   - LIKE wildcards `%` and `_` are neutralised with the `[...]` literal
     *     form (`[%]`, `[_]`); a literal `[` likewise becomes `[[]` so it can't
     *     open a character class.
     *   - `]` is deliberately NOT escaped: once every `[` is neutralised, a bare
     *     `]` is never a class terminator and is already literal. Escaping it as
     *     `[]]` actually BREAKS the match (the empty class `[]` matches nothing).
     *
     * Ordering is load-bearing: backslash is doubled first (so the backslash the
     * quote-escape introduces is not itself re-doubled); the `[`/`%`/`_` group is
     * replaced in a single `strtr()` pass (one-pass, so the brackets each
     * replacement emits are not themselves re-escaped — a sequential
     * `str_replace` for `[` would corrupt its own `[[]` output); the quote runs
     * last so its backslash survives undoubled.
     */
    private static function wmicLikeEscape(string $value): string {
        $value = str_replace('\\', '\\\\', $value);
        $value = strtr($value, ['[' => '[[]', '%' => '[%]', '_' => '[_]']);

        return str_replace("'", "\\'", $value);
    }

    /**
     * Run $primary; if it exits non-zero (PowerShell unavailable or errored),
     * fall back to $fallback. Prefers the PowerShell CIM kill path over the
     * legacy wmic one without assuming PowerShell is present.
     */
    private static function execWithFallback(string $primary, string $fallback): void {
        @exec($primary . ' 2>&1', $output, $code);

        if ($code !== 0) {
            self::exec($fallback);
        }
    }

    private static function exec(string $cmd): void {
        @exec($cmd . ' 2>&1', $output, $code);
    }
}
