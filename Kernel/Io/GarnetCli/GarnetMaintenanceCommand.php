<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetMaintenanceCommand {
    public static function run(array $args): void {
        $appName = GarnetEnv::requireAppName();
        // GARNET_WORKDIR_DIR-aware (see GarnetEnv::workDir) — must match where
        // MaintenanceMiddleware reads the flag, or maintenance silently never
        // engages on a deployed box.
        $workDir = GarnetEnv::workDir($appName);
        $flagFile = $workDir . DS . 'maintenance.flag';

        $action = $args[0] ?? 'status';

        match ($action) {
            'on' => static::enable($flagFile, array_slice($args, 1)),
            'off' => static::disable($flagFile),
            'status' => static::status($flagFile),
            default => static::help(),
        };
    }

    private static function enable(string $flagFile, array $ips): void {
        if (empty($ips)) {
            $ips = ['127.0.0.1', '::1'];
            $detected = @file_get_contents('https://api.ipify.org');

            if ($detected) {
                $ips[] = trim($detected);
            }
        }

        $data = json_encode([
            'enabled_at' => date('c'),
            'allowed_ips' => $ips,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($flagFile, $data);
        echo "\033[33m⚠ Maintenance mode ON\033[0m" . PHP_EOL;
        echo '  Allowed IPs: ' . implode(', ', $ips) . PHP_EOL;
        echo "  Flag: {$flagFile}" . PHP_EOL;
    }

    private static function disable(string $flagFile): void {
        if (file_exists($flagFile)) {
            unlink($flagFile);
            echo "\033[32m✓ Maintenance mode OFF\033[0m" . PHP_EOL;
        } else {
            echo 'Maintenance mode was not active.' . PHP_EOL;
        }
    }

    private static function status(string $flagFile): void {
        if (!file_exists($flagFile)) {
            echo "\033[32m● Active\033[0m — site is running normally." . PHP_EOL;

            return;
        }

        $data = json_decode(file_get_contents($flagFile), true) ?: [];
        echo "\033[33m● Maintenance mode ON\033[0m" . PHP_EOL;
        echo '  Since: ' . ($data['enabled_at'] ?? '?') . PHP_EOL;
        echo '  Allowed IPs: ' . implode(', ', $data['allowed_ips'] ?? []) . PHP_EOL;
    }

    private static function help(): void {
        echo 'Usage: php garnet maintenance <on|off|status> [IPs...]' . PHP_EOL;
        echo '  on [IP1 IP2 ...]  Enable maintenance, whitelist IPs' . PHP_EOL;
        echo '  off               Disable maintenance' . PHP_EOL;
        echo '  status            Show current state' . PHP_EOL;
    }
}
