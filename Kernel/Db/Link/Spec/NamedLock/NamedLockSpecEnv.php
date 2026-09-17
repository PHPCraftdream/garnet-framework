<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec\NamedLock {
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Link\NamedLock;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    /**
     * Общая обвязка интеграционных спеков NamedLock: есть ли база и уборка
     * после прогона.
     *
     * Вынесено сюда, потому что спеки NamedLock разложены по темам в
     * отдельные файлы, и тридцать строк проверки доступности базы в каждом
     * означали бы пять копий, расходящихся при первой же правке.
     *
     * Уборка обязательна и неслучайна: Kahlan гоняет все *IntegrationSpec.php
     * в ОДНОМ процессе, а DbPool — синглтон, который сам соединения не
     * закрывает. Без closeAll() в конце каждого файла прогон выедает
     * max_connections где-то на середине — и падает не там, где ошибка.
     */
    class NamedLockSpecEnv {
        /** Имена замков, которые спеки берут и которые надо отпустить. */
        public const LOCK_NAMES = ['test_named_lock_1', 'test_named_lock_2'];

        /** Доступна ли база: без неё интеграционные проверки пропускаются. */
        public static function probe(): bool {
            $dbConfigPath = self::configPath();

            if (!file_exists($dbConfigPath)) {
                echo "db.ini not found at {$dbConfigPath}\n";

                return false;
            }
            $config = parse_ini_file($dbConfigPath);

            if (!isset($config['enabled']) || $config['enabled'] !== '1') {
                echo "enabled != 1 in db.ini\n";

                return false;
            }

            IniConfig::defineDbIni($dbConfigPath);

            try {
                $link = DbPool::get()->newLink();

                if ($link->query('SELECT 1', [])) {
                    return true;
                }
            } catch (Exception $e) {
                echo 'Database connection failed: ' . $e->getMessage() . "\n";
            }

            return false;
        }

        /** Отпустить замки и закрыть соединения — см. докблок класса. */
        public static function cleanup(bool $dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            foreach (self::LOCK_NAMES as $name) {
                NamedLock::release($name);
            }
            DbPool::closeAll();
        }

        private static function configPath(): string {
            return __DIR__ . '/../../../../../TestsInit/TestConfig/db.ini';
        }
    }
}
