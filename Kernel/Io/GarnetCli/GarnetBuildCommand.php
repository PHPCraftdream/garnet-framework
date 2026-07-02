<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

class GarnetBuildCommand {
    public static function run(array $args, bool $watch): void {
        GarnetEnv::requireAppName();

        // COMMON_GARNET_WEB_DIR is where rspack.config.ts expects to find the
        // local `garnet` CLI (it spawns `php <dir>/garnet prepare`). In legacy
        // mode that's the monorepo root; in app-mode it's the app dir.
        $appDir = GarnetRunner::$appDir !== '' ? GarnetRunner::$appDir : GARNET_ROOT;
        putenv('COMMON_GARNET_WEB_DIR=' . $appDir . DIRECTORY_SEPARATOR);

        $env = $watch ? 'development' : 'production';
        $watchFlag = $watch ? ' --watch' : '';

        // FrontBuilder lives inside the Framework package now, not the repo root.
        $frontDir = GarnetRunner::$frameworkDir . DIRECTORY_SEPARATOR . 'FrontBuilder';

        $cwd = getcwd();
        chdir($frontDir);

        $cmd = "npx cross-env NODE_ENV={$env} rspack build{$watchFlag} --config rspack.config.ts";
        echo "Running: {$cmd}" . PHP_EOL;
        passthru($cmd, $code);

        chdir($cwd);

        exit($code);
    }
}
