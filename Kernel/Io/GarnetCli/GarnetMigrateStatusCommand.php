<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\CMDMigration;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\MigrationTable;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;

class GarnetMigrateStatusCommand {
    public static function run(): void {
        $appName = GarnetEnv::requireAppName();

        // Bootstrap app
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo "App {$appName} has no run_cmd.php" . PHP_EOL;

            exit(1);
        }

        // Load app (initializes migration class)
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();

        // Connect DB
        $isEnabled = (bool)DbPool::get()->getDbConfig()->paramInt('enabled');

        if (!$isEnabled) {
            echo 'Database is disabled in config.' . PHP_EOL;

            exit(1);
        }

        DbPool::get()->newLink();

        MigrationTable::init()->ex();
        MigrationTable::afterInit();

        $dbVersion = MigrationTable::get()->getCurrentVersion() ?? 0;

        // Get target version from the app's migration class
        $migrationClass = CMDMigration::getMigrationClass();
        $fsVersion = $migrationClass ? $migrationClass::get()->getCurrentVersion() : 0;

        echo "  App:              {$appName}" . PHP_EOL;
        echo "  Database version: \033[1m{$dbVersion}\033[0m" . PHP_EOL;
        echo "  Target version:   \033[1m{$fsVersion}\033[0m" . PHP_EOL;

        if ($dbVersion >= $fsVersion) {
            echo "\033[32m  [OK] Database is up to date.\033[0m" . PHP_EOL;
        } else {
            $pending = $fsVersion - $dbVersion;
            echo "\033[33m  [WARN] {$pending} migration(s) pending.\033[0m" . PHP_EOL;
        }
    }
}
