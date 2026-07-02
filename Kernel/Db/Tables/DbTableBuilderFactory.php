<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbTableBuilderException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbM2M;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class DbTableBuilderFactory {
        public static function get(string $tableName): ITableBuilderDriver {
            $config = IniConfig::get(IniConfig::ENV_DB);
            $type = $config->param('type');

            if (empty($type)) {
                throw new DbTableBuilderException('Empty type');
            }

            return match ($type) {
                ExtPDO::DB_TYPE_MYSQL => new TableBuilderMySQL($tableName),
                default => throw new DbTableBuilderException('Unknown type: ' . $type)
            };
        }

        public static function newCreateTable(
            IDbTable $table,
            bool $checkExists = true,
            ?string $collate = null,
            ?string $engine = null,
            ?int $autoIncrement = null,
        ): ITableBuilderDriver {
            $builder = static::get($table->getTableName());
            $builder->create($checkExists, $collate, $engine, $autoIncrement);

            return $builder;
        }

        public static function newAlterTable(IDbTable $table): ITableBuilderDriver {
            $builder = static::get($table->getTableName());
            $builder->alter();

            return $builder;
        }

        public static function newDropTable(IDbTable $table): ITableBuilderDriver {
            $builder = static::get($table->getTableName());
            $builder->drop();

            return $builder;
        }

        public static function initM2M(IDbM2M $table): ITableBuilderDriver {
            $builder = static::get($table->getTableName())->create();
            $key1 = $table->getKey1();
            $key2 = $table->getKey2();

            return $builder
                ->addIdColumn($table->getPrimaryKey())
                ->addColumn(column: $key1, type: 'INT', length: '11', null: false)
                ->addColumn(column: $key2, type: 'INT', length: '11', null: false)
                ->addColumn(column: 'value', type: 'INT', length: '1', default: '1', null: false)
                ->addIndex(indexName: 'link', indexes: [$key1, $key2], type: 'UNIQUE')
                ->addIndex(indexName: 'isDeleted', indexes: ['value']);
        }
    }
}
