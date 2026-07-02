<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Settings {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class SettingsTable extends DbTable {
        protected string $tableName = 'settings';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'param', type: 'VARCHAR', length: '16')
                ->addColumn(column: 'value', type: 'VARCHAR', length: '255')
                ->addIndex(indexName: 'param', indexes: ['param'], type: 'UNIQUE')
            ;
        }
    }
}
