<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetServeDebugCommand {
    public static function run(array $args): void {
        $token = bin2hex(random_bytes(32));
        $tokenFile = GARNET_ROOT . DIRECTORY_SEPARATOR . '.garnet_debug_token';

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
