<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Migration {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\MigrationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigration;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionException;

    class CMDMigration implements ICommand {
        protected static string $migrationClass = Migration::class;

        /**
         * @return string
         */
        public static function getMigrationClass(): string {
            return self::$migrationClass;
        }

        /**
         * @param class-string $migrationClass
         * @return void
         * @throws MigrationException
         * @throws ReflectionException
         */
        public static function setMigrationClass(string $migrationClass): void {
            if (!Env::classImplements($migrationClass, IMigration::class)) {
                throw new MigrationException('Migration class must implement IMigration');
            }
            self::$migrationClass = $migrationClass;
        }

        /**
         * @return string
         */
        public static function description(): string {
            return 'Migration tool';
        }

        /**
         * @var string[]
         */
        protected static array $commands = [
            'init' => 'Init the migration tracker table',
            'version-db' => 'Migration version of database',
            'version-fs' => 'Migration version of filesystem',
            'migrate' => 'Update database (default action when no sub-command given)',
            'help' => 'Show this help',
        ];

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         */
        public static function help(array $args, Context $context, Stdio $stdio): void {
            $cmdStrPad = max(10, StrTools::maxKeyLen(static::$commands) + 1);

            foreach (static::$commands as $command => $description) {
                $printCommand = StrTools::pad($command, $cmdStrPad);
                $stdio->outln("{$printCommand} - " . $description);
            }
        }

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         * @throws IniConfigException
         * @throws MigrationException
         */
        public static function run(array $args, Context $context, Stdio $stdio): void {
            // Default action: `php garnet migration` (no sub-command)
            // runs the actual migration. Sub-commands stay for the rare
            // cases someone wants version checks or to re-init the tracker.
            $command = $args[0] ?? 'migrate';

            if (empty(static::$commands[$command])) {
                $stdio->errln("Unknown migration sub-command: {$command}");
                static::help($args, $context, $stdio);

                return;
            }

            match ($command) {
                'init' => static::init($args, $context, $stdio),
                'version-db' => static::version($args, $context, $stdio),
                'version-fs' => static::versionFs($args, $context, $stdio),
                'migrate' => static::migrate($args, $context, $stdio),
                'help' => static::help($args, $context, $stdio),
            };
        }

        /**
         * @return string
         * @throws IniConfigException
         */
        protected static function getEnv(): string {
            $config = AppConfig::get(IniConfig::ENV_APP);

            return $config->paramString('migration_db_env');
        }

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return bool
         * @throws IniConfigException
         */
        protected static function init(array $args, Context $context, Stdio $stdio): bool {
            MigrationTable::init()->ex();
            MigrationTable::afterInit();
            /* @phpstan-ignore-next-line */
            $dbVersion = MigrationTable::get(static::getEnv())->getCurrentVersion();

            $stdio->outln('Init done.');
            $stdio->outln('Database version = ' . $dbVersion);

            return true;
        }

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return bool
         * @throws IniConfigException
         */
        protected static function version(array $args, Context $context, Stdio $stdio): bool {
            /* @phpstan-ignore-next-line */
            $dbVersion = MigrationTable::get(static::getEnv())->getCurrentVersion();
            $stdio->outln('Database version = ' . $dbVersion);

            return true;
        }

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return bool
         * @throws IniConfigException
         * @throws MigrationException
         */
        protected static function versionFs(array $args, Context $context, Stdio $stdio): bool {
            $migrationClass = static::getMigrationClass();
            $migrationGet = "{$migrationClass}::get";

            if (!is_callable($migrationGet)) {
                throw new MigrationException('Can not call: ' . $migrationGet);
            }

            $migration = $migrationGet(static::getEnv());
            $fsVersion = $migration->getCurrentVersion();
            $stdio->outln('Fs version = ' . $fsVersion);

            return true;
        }

        /**
         * @param array $args
         * @param Context $context
         * @param Stdio $stdio
         * @return bool
         * @throws IniConfigException
         * @throws MigrationException
         */
        protected static function migrate(array $args, Context $context, Stdio $stdio): bool {
            $migrationClass = static::getMigrationClass();
            $migrationGet = "{$migrationClass}::get";

            if (!is_callable($migrationGet)) {
                throw new MigrationException('Can not call: ' . $migrationGet);
            }

            $migration = $migrationGet(static::getEnv());
            $migration->migrate($stdio);

            return true;
        }
    }
}
