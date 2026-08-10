<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

/**
 * App-level config bootstrap.
 *
 *   config:init           Copy WorkDir/ConfigExample/*.ini → both WorkDir/Config/ and WorkDir/ConfigDev/
 *   config:init --dev     Copy WorkDir/ConfigExample/*.ini → WorkDir/ConfigDev/ only
 *   config:init --prod    Copy WorkDir/ConfigExample/*.ini → WorkDir/Config/ only
 *
 * `app.ini`/`db.ini`/`email.ini` already ship with usable dev-default
 * placeholders directly in Config/ and ConfigDev/ (see the app template) —
 * this command is mainly for seeding the deploy-only `ssh.ini`/`deploy.ini`
 * that aren't part of that default scaffolding.
 *
 * Non-destructive by default: skips files that already exist.
 * Add --force to overwrite existing files.
 */
class GarnetConfigCommand {
    public static function run(string $command, array $args): void {
        match ($command) {
            'config' => self::init($args),  // bare `config` == init
            'config:init' => self::init($args),
            default => self::help(),
        };

        exit(0);
    }

    private static function init(array $args): void {
        $dev = in_array('--dev',   $args, true);
        $prod = in_array('--prod', $args, true);
        $force = in_array('--force', $args, true);

        // Bare invocation seeds both; --dev/--prod narrow to just one.
        $writeConfig = $prod || !$dev;
        $writeConfigDev = $dev || !$prod;

        $appName = GarnetEnv::requireAppName();
        $appDir = GarnetEnv::getAppDir($appName);
        $exDir = $appDir . DS . 'WorkDir' . DS . 'ConfigExample';

        if (!is_dir($exDir)) {
            echo "\033[31mError:\033[0m no ConfigExample/ found at {$exDir}" . PHP_EOL;

            exit(1);
        }

        echo "\033[1m=== Garnet Config Init ===\033[0m" . PHP_EOL;
        echo "  source: {$exDir}" . PHP_EOL;

        // ConfigExample/ (the source template above) always lives under the
        // app's own WorkDir — it's a dev-scaffold artifact, never relocated.
        // The WRITE targets are different: on a deployed bundle WorkDir
        // itself is relocated into the runtime folder via GARNET_WORKDIR_DIR,
        // and the web runtime reads Config/ConfigDev from THAT location —
        // GarnetEnv::workDir() is the documented single source of truth for
        // it, not a raw $appDir-relative guess.
        $workDir = GarnetEnv::workDir($appName);
        $targets = [];

        if ($writeConfig) {
            $targets['Config'] = $workDir . DS . 'Config';
        }

        if ($writeConfigDev) {
            $targets['ConfigDev'] = $workDir . DS . 'ConfigDev';
        }

        foreach ($targets as $label => $cfgDir) {
            if (!is_dir($cfgDir)) {
                @mkdir($cfgDir, 0o775, true);
                echo "  created dir: {$cfgDir}" . PHP_EOL;
            }

            echo PHP_EOL . "  \033[1m→ {$label}/\033[0m  ({$cfgDir})" . PHP_EOL;

            $created = 0;
            $skipped = 0;

            foreach (scandir($exDir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (!str_ends_with($entry, '.ini')) {
                    continue;
                }

                $src = $exDir . DS . $entry;
                $dst = $cfgDir . DS . $entry;

                if (!$force && file_exists($dst)) {
                    echo "  \033[33mskip\033[0m   {$entry}  (already exists)" . PHP_EOL;
                    $skipped++;

                    continue;
                }

                if (!copy($src, $dst)) {
                    echo "  \033[31mfail\033[0m   {$entry}  (copy failed)" . PHP_EOL;

                    continue;
                }
                echo "  \033[32mcreate\033[0m {$entry}" . PHP_EOL;
                $created++;
            }

            echo "  {$created} created, {$skipped} skipped." . PHP_EOL;

            if ($created > 0 && $label === 'ConfigDev') {
                echo "  \033[33mNext:\033[0m edit {$cfgDir}/*.ini with real dev credentials." . PHP_EOL;
            } elseif ($created > 0) {
                echo "  \033[33mNext:\033[0m edit the new .ini files with production values." . PHP_EOL;
            }
        }
    }

    private static function help(): void {
        echo 'Usage:' . PHP_EOL;
        echo '  php garnet config:init           Copy ConfigExample/*.ini → Config/ AND ConfigDev/  (non-destructive)' . PHP_EOL;
        echo '  php garnet config:init --dev     Copy ConfigExample/*.ini → ConfigDev/ only' . PHP_EOL;
        echo '  php garnet config:init --prod    Copy ConfigExample/*.ini → Config/ only' . PHP_EOL;
        echo '  php garnet config:init --force   Overwrite existing files' . PHP_EOL;
    }
}
