<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use Throwable;

/**
 * SSH/SCP helpers driven by WorkDir/Config[Dev]/ssh.ini.
 *
 *   ssh [opts] <cmd>   Run a shell command on the remote host
 *   ssh:run            Alias for ssh
 *   ssh:put <l> [r]    Upload a file (or dir, with -r / auto-detected) via scp
 *   ssh:get <r> [l]    Download a file via scp
 *   ssh:test           Smoke-test the connection (echo ok; pwd; whoami)
 *   ssh:help           Detailed help
 */
class GarnetSshCommand {
    public static function run(string $command, array $args): void {
        match ($command) {
            'ssh', 'ssh:run' => self::execRemote($args),
            'ssh:put' => self::cmdPut($args),
            'ssh:get' => self::cmdGet($args),
            'ssh:test' => self::cmdTest($args),
            'ssh:help' => self::help(),
            default => (static function (): void {
                self::help();

                exit(1);
            })(),
        };

        exit(0);
    }

    private static function execRemote(array $args): void {
        [$flags, $positional] = self::parseArgs($args);

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();

        $remoteCmd = self::resolveCommand($flags, $positional);

        if ($remoteCmd === null) {
            self::help();

            exit(1);
        }

        self::assertNotRawMysql($remoteCmd);

        $opts = self::flagsToOpts($flags, true); // live mode for CLI

        // Auto-cd into the deploy runtime dir unless the user chose a dir
        if (!$flags['home'] && ($opts['cwd'] ?? '') === '' && empty($opts['cd_remote'])) {
            $runtime = self::runtimeDir();

            if ($runtime !== '') {
                $opts['cwd'] = $runtime;
            }
        }

        if ($flags['dry_run']) {
            $argv = $client->buildRunArgv($remoteCmd, $opts);
            self::printDryRun($argv);

            exit(0);
        }

        $result = $client->run($remoteCmd, $opts);

        exit($result->exitCode);
    }

    private static function cmdPut(array $args): void {
        [$flags, $positional] = self::parseArgs($args);

        if (empty($positional)) {
            echo "\033[31mError:\033[0m ssh:put requires a <local> path." . PHP_EOL;

            exit(1);
        }

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();

        $local = $positional[0];
        $remote = $positional[1] ?? null;
        $opts = self::flagsToOpts($flags, true);

        // Auto-detect: a directory always needs -r, whether or not the flag
        // was passed — plain scp errors on directories otherwise ("not a
        // regular file"), which is a confusing failure for something that's
        // trivially detectable up front.
        if ($flags['recursive'] || (is_dir($local) && !is_link($local))) {
            $opts['recursive'] = true;
        }

        if ($flags['dry_run']) {
            $argv = $client->buildPutArgv($local, $remote, $opts);
            self::printDryRun($argv);

            exit(0);
        }

        $result = $client->put($local, $remote, $opts);

        exit($result->exitCode);
    }

    private static function cmdGet(array $args): void {
        [$flags, $positional] = self::parseArgs($args);

        if (empty($positional)) {
            echo "\033[31mError:\033[0m ssh:get requires a <remote> path." . PHP_EOL;

            exit(1);
        }

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();

        $remote = $positional[0];
        $local = $positional[1] ?? null;
        $opts = self::flagsToOpts($flags, true);

        if ($flags['dry_run']) {
            $argv = $client->buildGetArgv($remote, $local, $opts);
            self::printDryRun($argv);

            exit(0);
        }

        $result = $client->get($remote, $local, $opts);

        exit($result->exitCode);
    }

    private static function cmdTest(array $args): void {
        [$flags] = self::parseArgs($args);

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();

        if ($flags['dry_run']) {
            $argv = $client->buildRunArgv('echo ok; pwd; whoami', ['tty' => false, 'stream' => false]);
            self::printDryRun($argv);

            exit(0);
        }

        $result = $client->test();

        echo $result->raw->stdout;

        if ($result->raw->stderr !== '') {
            fwrite(STDERR, $result->raw->stderr);
        }

        if (!$result->ok) {
            echo "\033[31mTest failed\033[0m (exit={$result->raw->exitCode})" . PHP_EOL;

            exit(1);
        }

        echo "\033[32mConnection OK\033[0m" . PHP_EOL;

        exit(0);
    }

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    private static function bootApp(): void {
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo "\033[31mError:\033[0m app has no run_cmd.php at {$runCmd}" . PHP_EOL;

            exit(1);
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();
    }

    // -------------------------------------------------------------------------
    // Arg parsing
    // -------------------------------------------------------------------------

    private static function parseArgs(array $args): array {
        $flags = [
            'tty' => false,
            'no_tty' => false,
            'verbose' => false,
            'cwd' => '',
            'cd_remote' => false,
            'home' => false,
            'env' => [],
            'file' => '',
            'dry_run' => false,
            'recursive' => false,
        ];
        $positional = [];

        foreach ($args as $arg) {
            if ($arg === '--tty' || $arg === '-t') {
                $flags['tty'] = true;

                continue;
            }

            if ($arg === '--no-tty' || $arg === '-T') {
                $flags['no_tty'] = true;

                continue;
            }

            if ($arg === '--verbose' || $arg === '-v') {
                $flags['verbose'] = true;

                continue;
            }

            if ($arg === '--cd-remote') {
                $flags['cd_remote'] = true;

                continue;
            }

            if ($arg === '--home' || $arg === '--no-cd') {
                $flags['home'] = true;

                continue;
            }

            if ($arg === '--dry-run') {
                $flags['dry_run'] = true;

                continue;
            }

            if ($arg === '--recursive' || $arg === '-r') {
                $flags['recursive'] = true;

                continue;
            }

            if (str_starts_with($arg, '--cwd=')) {
                $flags['cwd'] = substr($arg, 6);

                continue;
            }

            if (str_starts_with($arg, '--env=')) {
                $flags['env'][] = substr($arg, 6);

                continue;
            }

            if (str_starts_with($arg, '--file=')) {
                $flags['file'] = substr($arg, 7);

                continue;
            }

            if (!str_starts_with($arg, '-')) {
                $positional[] = $arg;
            }
        }

        return [$flags, $positional];
    }

    private static function flagsToOpts(array $flags, bool $stream): array {
        $opts = [
            'tty' => $flags['tty'],
            'no_tty' => $flags['no_tty'],
            'verbose' => $flags['verbose'],
            'stream' => $stream,
            'env' => $flags['env'],
        ];

        if ($flags['cwd'] !== '') {
            $opts['cwd'] = $flags['cwd'];
        }

        if ($flags['cd_remote']) {
            $opts['cd_remote'] = true;
        }

        return $opts;
    }

    private static function resolveCommand(array $flags, array $positional): ?string {
        if ($flags['file'] !== '') {
            $path = $flags['file'];

            if (!is_readable($path)) {
                echo "\033[31mError:\033[0m cannot read file: {$path}" . PHP_EOL;

                exit(1);
            }

            return (string)file_get_contents($path);
        }

        if (!empty($positional)) {
            return $positional[0];
        }
        $isTty = function_exists('stream_isatty')
            ? stream_isatty(STDIN)
            : (function_exists('posix_isatty') ? posix_isatty(STDIN) : true);

        if (!$isTty) {
            return stream_get_contents(STDIN) ?: null;
        }

        return null;
    }

    /**
     * Guard: refuse `ssh "mysql …"`. Inlining the mysql client over SSH leaks
     * credentials into shell history/logs and bypasses the framework DB layer.
     * Only the FIRST word is checked (so `cd … && php garnet sql …` is fine,
     * and `mysqldump` is not caught). Use `php garnet sql` instead.
     */
    private static function assertNotRawMysql(string $remoteCmd): void {
        if (!preg_match('/^\s*mysql(\s|$)/', $remoteCmd)) {
            return;
        }

        fwrite(STDERR, "\033[31mError:\033[0m refusing to run the \033[1mmysql\033[0m client directly over ssh.\n");
        fwrite(STDERR, "  It leaks DB credentials into shell history and bypasses the framework DB layer.\n\n");
        fwrite(STDERR, "  Use the Garnet SQL command instead:\n");
        fwrite(STDERR, "    \033[36mlocal :\033[0m php garnet sql \"SELECT … \"\n");
        fwrite(STDERR, "    \033[36mremote:\033[0m php garnet ssh \"cd <runtime-dir> && php garnet sql 'SELECT … '\"\n");
        fwrite(STDERR, "    (append \033[1m--json\033[0m for machine-readable output)\n");

        exit(1);
    }

    private static function printDryRun(array $argv): void {
        echo "\033[1m(dry-run)\033[0m" . PHP_EOL;

        foreach ($argv as $arg) {
            echo "  \033[36m\$\033[0m " . $arg . PHP_EOL;
        }
    }

    /**
     * Resolve the deploy runtime directory from deploy.ini (remote_path + runtime_dir).
     * Returns '' when either value is missing or deploy.ini cannot be read.
     */
    private static function runtimeDir(): string {
        try {
            $deploy = \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::deploy();
            $base = rtrim($deploy->paramString('remote_path', ''), '/');
            $dir = trim($deploy->paramString('runtime_dir', ''), '/');

            return ($base !== '' && $dir !== '') ? $base . '/' . $dir : '';
        } catch (Throwable) {
            return '';
        }
    }

    private static function help(): void {
        echo <<<HELP

  \033[1mUsage:\033[0m php garnet ssh [options] <command>
         php garnet ssh:run / ssh:put / ssh:get / ssh:test / ssh:help

  \033[1mConnection:\033[0m  configured in WorkDir/ConfigDev/ssh.ini (or Config/ssh.ini).
  Run \033[36mphp garnet config:init --dev\033[0m to create an ini from ConfigExample/.

  \033[1mSub-commands:\033[0m
    ssh / ssh:run  Run a shell command on the remote host.
    ssh:put        Upload a local file (or directory, with -r) via scp.
    ssh:get        Download a remote file via scp.
    ssh:test       Smoke-test connectivity (echo ok; pwd; whoami).
    ssh:help       Show this help.

  \033[1mCommand source for ssh / ssh:run (in priority order):\033[0m
    1. --file=PATH   read command from file
    2. first arg     literal shell command
    3. stdin         piped/redirected input (when stdin is not a TTY)
    4. (none)        print help and exit 1

  \033[1mFlags:\033[0m
    --tty,   -t        Force TTY allocation (-t)
    --no-tty,-T        Disable TTY allocation (-T)
    --verbose,-v       Pass -v to ssh (connection debug output)
    --cwd=PATH         Run the command inside: cd PATH && ( cmd )
    --cd-remote        Use remote_path from ini as the working dir
    --home, --no-cd    Run in the remote login/home dir (skip auto-cd)
    --env=K=V          Prepend: export K='V';  (repeatable)
    --file=PATH        Read the shell command from a local file
    --dry-run          Print the final argv without executing
    --recursive, -r    ssh:put only: pass -r to scp for a directory upload.
                       Auto-detected when <local> is a directory — you only
                       need this flag if you want to force/document intent.

  \033[1mAuto-cd:\033[0m
    By default, ssh / ssh:run cd's into the deploy runtime directory
    (remote_path/runtime_dir from deploy.ini). Use --home to stay in
    the remote home dir, or --cwd=PATH / --cd-remote to override.

  \033[1mExamples:\033[0m
    php garnet ssh "uptime"
    echo "ls -la /srv/app" | php garnet ssh
    php garnet ssh --file=scripts/deploy_hook.sh
    php garnet ssh "make build" --cd-remote --env=NODE_ENV=production
    php garnet ssh:put dist/app.tar.gz
    php garnet ssh:put dist/app.tar.gz /srv/releases/app.tar.gz
    php garnet ssh:put dist/IRabi/garnet-framework "garnet-framework-2026-05-21" --cd-remote
    php garnet ssh:get /srv/app/logs/error.log ./error.log
    php garnet ssh:test
    php garnet ssh "uptime" --dry-run

HELP;
    }
}
