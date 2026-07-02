<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use Throwable;

/**
 * `php garnet maintenance:remote on|off|status` — drive the REMOTE box's
 * maintenance mode from the local machine over SSH, mirroring the local
 * `php garnet maintenance` engine.
 *
 *   maintenance:remote on  [ip ...]   put prod into maintenance (503 page)
 *   maintenance:remote off            bring prod back online
 *   maintenance:remote status         show prod's maintenance state
 *
 * On `on` with no explicit IPs, the operator's own public IP is detected
 * locally and passed through as the allow-list — otherwise the server would
 * default to ITS public IP and lock the operator out of the box they're
 * trying to babysit. Pass IPs explicitly to override.
 *
 * The server side is the already-deployed GarnetMaintenanceCommand +
 * MaintenanceMiddleware; this command just runs `php garnet maintenance …`
 * in the remote runtime dir. SSH params come from ssh.ini (same as
 * `php garnet ssh`); the runtime dir from deploy.ini.
 */
final class GarnetMaintenanceRemoteCommand {
    public static function run(array $args): void {
        $action = $args[0] ?? 'status';

        if (!in_array($action, ['on', 'off', 'status'], true)) {
            self::help();

            exit($action === 'help' || $action === '--help' ? 0 : 1);
        }

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();
        $remoteDir = self::resolveRemoteRuntimeDir();

        $remoteCmd = match ($action) {
            'on' => 'php garnet maintenance on' . self::ipArgs(array_slice($args, 1)),
            'off' => 'php garnet maintenance off',
            default => 'php garnet maintenance status',
        };

        echo "\033[1;36m[maintenance:remote]\033[0m {$action} → " . self::host() . PHP_EOL;
        $res = $client->run($remoteCmd, ['cwd' => $remoteDir, 'stream' => true]);

        if (!$res->ok()) {
            fwrite(STDERR, "\033[31mError:\033[0m remote maintenance {$action} failed (exit {$res->exitCode}).\n");

            exit(1);
        }

        if ($action === 'on') {
            echo "\033[33mNote:\033[0m the box is now serving a 503 page to everyone except the allow-listed IP(s).\n";
            echo "       Re-open it with: \033[1mphp garnet maintenance:remote off\033[0m\n";
        }

        exit(0);
    }

    /** Resolve + validate the IPs to allow through (operator's own by default). */
    private static function ipArgs(array $ips): string {
        $ips = array_values(array_filter(array_map('trim', $ips), static fn (string $s) => $s !== ''));

        if (empty($ips)) {
            $detected = @file_get_contents('https://api.ipify.org');
            $detected = is_string($detected) ? trim($detected) : '';

            if ($detected !== '' && filter_var($detected, FILTER_VALIDATE_IP)) {
                $ips = [$detected];
                echo "  allow-listing your public IP: {$detected}\n";
            } else {
                fwrite(STDERR, "\033[33mWarning:\033[0m could not detect your public IP — the server will fall back to ITS own IP, which may lock you out. Pass an IP explicitly: maintenance:remote on <ip>\n");
            }
        }

        $out = '';

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                self::fail("not a valid IP: {$ip}");
            }
            // IPs are [0-9a-f:.] only — safe to pass bare to the remote shell.
            $out .= ' ' . $ip;
        }

        return $out;
    }

    private static function resolveRemoteRuntimeDir(): string {
        $deploy = IniConfig::deploy();
        $remotePath = rtrim($deploy->paramString('remote_path', ''), '/');
        $runtimeDir = trim($deploy->paramString('runtime_dir', ''), '/');

        if ($remotePath === '' || $runtimeDir === '') {
            self::fail('deploy.ini must define remote_path and runtime_dir.');
        }

        return $remotePath . '/' . $runtimeDir;
    }

    private static function host(): string {
        try {
            $name = IniConfig::deploy()->paramString('public_dir', '');

            return $name !== '' ? $name : 'the remote server';
        } catch (Throwable) {
            return 'the remote server';
        }
    }

    private static function bootApp(): void {
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            self::fail("app has no run_cmd.php at {$runCmd}");
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();
    }

    private static function fail(string $msg): never {
        fwrite(STDERR, "\033[31mError:\033[0m {$msg}\n");

        exit(1);
    }

    private static function help(): void {
        echo 'Usage:' . PHP_EOL;
        echo '  php garnet maintenance:remote on [ip ...]   put the prod box into maintenance (503)' . PHP_EOL;
        echo '  php garnet maintenance:remote off           bring the prod box back online' . PHP_EOL;
        echo "  php garnet maintenance:remote status        show the prod box's maintenance state" . PHP_EOL;
        echo "  (on, with no IPs, allow-lists your detected public IP so you aren't locked out)" . PHP_EOL;
    }
}
