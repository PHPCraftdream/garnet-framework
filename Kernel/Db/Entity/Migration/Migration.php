<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Migration {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\MigrationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigration;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    class Migration implements IMigration {
        protected int $currentVersion = 1;

        protected array $migrationClasses = [];

        protected static ?Migration $instance = null;

        protected function __construct() {
        }

        public static function get(): IMigration {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        public function migrate(Stdio $stdio): void {
            $fsVersion = $this->currentVersion;
            $migrationTable = MigrationTable::get();
            $dbVersion = $migrationTable->getCurrentVersion();

            if ($dbVersion >= $fsVersion) {
                $stdio->outln("Nothing to update, dbVersion = {$dbVersion}, fsVersion = {$fsVersion}");

                return;
            }

            foreach (range($dbVersion + 1, $fsVersion) as $version) {
                $migrationClass = $this->migrationClasses[$version] ?? null;

                if (empty($migrationClass)) {
                    throw new MigrationException('Empty migration class for version: ' . $version);
                }

                if (!Env::classImplements($migrationClass, IMigrationItem::class)) {
                    throw new MigrationException('Migration item class must implement IMigrationItem');
                }

                $runMigration = "{$migrationClass}::update";

                if (!is_callable($runMigration)) {
                    throw new MigrationException('Can not call: ' . $runMigration);
                }

                try {
                    $runMigration($stdio);
                    $migrationTable->setCurrentVersion($version);
                    $stdio->outln('database updated to version ' . $version);
                } catch (DbException $e) {
                    $stdio->outln('Error on execute:' . PHP_EOL . $e->getSql() . PHP_EOL . print_r($e->getArgs(), true));

                    throw $e;
                }
            }
        }

        /**
         * @return int
         */
        public function getCurrentVersion(): int {
            return $this->currentVersion;
        }
    }
}
