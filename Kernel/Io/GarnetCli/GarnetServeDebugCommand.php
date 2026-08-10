<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetServeDebugCommand {
    public static function run(array $args): void {
        $token = bin2hex(random_bytes(32));
        // In app-mode GARNET_ROOT (the constant) is the vendored framework
        // package dir — vendor/ is disposable and gets wiped by a plain
        // `composer install`, so a per-app runtime artifact must not live
        // there. $_ENV['GARNET_ROOT'] is the app-scoped override GarnetRunner
        // sets for exactly this reason (see AdminAuth::tokenFile() for the
        // same pattern); falls back to the constant for legacy-mode, where
        // it's already the monorepo root.
        $root = $_ENV['GARNET_ROOT'] ?? GARNET_ROOT;
        $tokenFile = $root . DIRECTORY_SEPARATOR . '.garnet_debug_token';

        file_put_contents($tokenFile, $token);

        register_shutdown_function(static function () use ($tokenFile): void {
            if (file_exists($tokenFile)) {
                unlink($tokenFile);
            }
        });

        putenv('GARNET_DEBUG=1');
        putenv('GARNET_DEBUG_TOKEN=' . $token);

        echo 'Debug mode enabled (token: ' . substr($token, 0, 8) . '...)' . PHP_EOL;
        echo "Token file: {$tokenFile}" . PHP_EOL;

        GarnetServeCommand::run($args);
    }
}
