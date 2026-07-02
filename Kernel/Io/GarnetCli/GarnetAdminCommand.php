<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminAuth;

class GarnetAdminCommand {
    public static function run(string $command, array $args): void {
        match ($command) {
            'admin' => self::generateToken($args),
            'admin:build' => self::build(),
            'admin:logout' => self::logout(),
            default => self::help(),
        };

        exit(0);
    }

    private static function generateToken(array $args): void {
        $port = '8001';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--port=')) {
                $port = substr($arg, 7);
            }
        }

        $token = AdminAuth::generateToken();
        AdminAuth::saveToken($token);

        $url = "http://localhost:{$port}/__garnet/?token={$token}";

        echo PHP_EOL;
        echo '  Garnet Admin token generated.' . PHP_EOL;
        echo '  Open this URL in your browser:' . PHP_EOL;
        echo PHP_EOL;
        echo "  {$url}" . PHP_EOL;
        echo PHP_EOL;
    }

    private static function build(): void {
        // FrontBuilder ships inside the framework package, not the app dir —
        // in app-mode GARNET_ROOT is the app, so anchor to the framework dir
        // (same as GarnetBuildCommand).
        $frontendDir = GarnetRunner::$frameworkDir . DIRECTORY_SEPARATOR . 'FrontBuilder';

        echo 'Building admin panel...' . PHP_EOL;

        $cwd = getcwd();
        chdir($frontendDir);
        passthru('npx rspack build --config rspack.admin.config.ts', $code);
        chdir($cwd);

        if ($code === 0) {
            echo 'Admin panel built successfully.' . PHP_EOL;
        } else {
            echo 'Admin panel build failed.' . PHP_EOL;

            exit(1);
        }
    }

    private static function logout(): void {
        AdminAuth::deleteToken();
        echo 'Admin session cleared.' . PHP_EOL;
    }

    private static function help(): void {
        echo 'Admin commands:' . PHP_EOL;
        echo '  admin           Generate admin access token' . PHP_EOL;
        echo '  admin:build     Build admin panel assets' . PHP_EOL;
        echo '  admin:logout    Clear admin session' . PHP_EOL;
    }
}
