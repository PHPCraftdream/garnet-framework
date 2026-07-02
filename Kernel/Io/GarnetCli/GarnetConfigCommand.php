<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

/**
 * App-level config bootstrap.
 *
 *   config:init           Copy WorkDir/ConfigExample/*.ini → WorkDir/Config/
 *   config:init --dev     Copy WorkDir/ConfigExample/*.ini → WorkDir/ConfigDev/
 *   config:init --all     Both Config/ and ConfigDev/
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
        $all = in_array('--all',   $args, true);
        $force = in_array('--force', $args, true);

        // --all implies both; bare invocation writes only Config/
        $writeConfig = !$dev || $all;
        $writeConfigDev = $dev || $all;

        $appName = GarnetEnv::requireAppName();
        $appDir = GarnetEnv::getAppDir($appName);
        $exDir = $appDir . DS . 'WorkDir' . DS . 'ConfigExample';

        if (!is_dir($exDir)) {
            echo "\033[31mError:\033[0m no ConfigExample/ found at {$exDir}" . PHP_EOL;

            exit(1);
        }

        echo "\033[1m=== Garnet Config Init ===\033[0m" . PHP_EOL;
        echo "  source: {$exDir}" . PHP_EOL;

        $targets = [];

        if ($writeConfig) {
            $targets['Config'] = $appDir . DS . 'WorkDir' . DS . 'Config';
        }

        if ($writeConfigDev) {
            $targets['ConfigDev'] = $appDir . DS . 'WorkDir' . DS . 'ConfigDev';
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
        echo '  php garnet config:init           Copy ConfigExample/*.ini → Config/  (non-destructive)' . PHP_EOL;
        echo '  php garnet config:init --dev     Copy ConfigExample/*.ini → ConfigDev/  (non-destructive)' . PHP_EOL;
        echo '  php garnet config:init --all     Both Config/ and ConfigDev/' . PHP_EOL;
        echo '  php garnet config:init --force   Overwrite existing files' . PHP_EOL;
    }
}
