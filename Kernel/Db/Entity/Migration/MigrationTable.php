<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Migration {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class MigrationTable extends DbTable {
        public string $tableName = 'migration';

        public string $primaryKey = 'id';

        public string $idForVersion = '1000';

        public static function init(): ITableBuilderDriver {
            $table = static::get();
            $pk = $table->getPrimaryKey();

            return DbTableBuilderFactory::newCreateTable(table: $table, engine: 'InnoDB')
                ->addIdColumn($pk)
                ->addColumn(column: 'version', type: 'VARCHAR', length: '5', null: false);
        }

        public static function afterInit(): void {
            $table = static::get();
            $pk = $table->getPrimaryKey();
            $idForVersion = $table->idForVersion;

            if (!$table->existsById($idForVersion)) {
                // Start at 0 — `migrate` then runs range(1..N), which means
                // a fresh DB picks up M_0001 (base schema: accounts, session,
                // settings, etc.) before any M_0002+ alters. The previous
                // default of '1' silently skipped M_0001 and left the DB
                // missing the base tables.
                $table->insert([$pk => $idForVersion, 'version' => '0']);
            }
        }

        public function getCurrentVersion(): ?int {
            $res = $this->selectById($this->idForVersion);
            $version = $res['version'] ?? null;

            if ($version !== null) {
                $version = intval($version);
            }

            return $version;
        }

        public function setCurrentVersion(int $version): void {
            $this->updateById(['version' => $version], $this->idForVersion);
        }
    }
}
