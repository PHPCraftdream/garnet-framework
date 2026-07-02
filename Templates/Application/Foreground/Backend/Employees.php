<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground\Backend {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class Employees extends DbTable {
        public string $tableName = 'employees';

        public string $primaryKey = 'employeeNumber';

        public ?string $prefix = '';

        /**
         * @return ITableBuilderDriver
         */
        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get(), engine: 'InnoDB')
                ->addIdColumn()
                ->addColumn(column: 'email', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'name', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'secondName', type: 'VARCHAR', length: '64')
                ->addIndex(indexName: 'email', indexes: 'email', type: 'UNIQUE');
        }
    }
}
