<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

/**
 * Combined serve + build:watch — starts rspack watcher in background,
 * then launches the PHP dev server in foreground.
 */
class GarnetServeWatchCommand {
    public static function run(array $args): void {
        GarnetEnv::requireAppName();

        // Mirror GarnetBuildCommand: COMMON_GARNET_WEB_DIR is where rspack finds
        // the local `garnet` CLI (app dir in app-mode), and FrontBuilder ships
        // inside the framework package — anchor both correctly for dual-mode.
        $appDir = GarnetRunner::$appDir !== '' ? GarnetRunner::$appDir : GARNET_ROOT;
        putenv('COMMON_GARNET_WEB_DIR=' . $appDir . DIRECTORY_SEPARATOR);

        $frontDir = GarnetRunner::$frameworkDir . DIRECTORY_SEPARATOR . 'FrontBuilder';
        $cmd = 'npx cross-env NODE_ENV=development rspack build --watch --config rspack.config.ts';

        $isWindows = DIRECTORY_SEPARATOR === '\\';

        echo 'Starting rspack watcher...' . PHP_EOL;

        if ($isWindows) {
            pclose(popen("cd /d \"{$frontDir}\" && start /B {$cmd}", 'r'));
        } else {
            $escaped = escapeshellarg($cmd);
            exec('cd ' . escapeshellarg($frontDir) . " && {$escaped} > /dev/null 2>&1 &");
        }

        echo 'rspack watcher started in background' . PHP_EOL;

        GarnetServeCommand::run($args);
    }
}
