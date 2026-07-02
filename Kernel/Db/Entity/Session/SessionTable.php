<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class SessionTable extends DbTable {
        protected string $tableName = 'session';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get(), engine: 'InnoDB')
                ->addIdColumn()
                ->addColumn(column: 'name', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'lastUsage', type: 'INT', length: '11')
                ->addIndex(indexName: 'name', indexes: 'name', type: 'UNIQUE')
            ;
        }
    }
}
