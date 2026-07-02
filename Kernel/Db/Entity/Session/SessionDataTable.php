<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class SessionDataTable extends DbTable {
        public const PARAM_LEN = 32;

        public const VALUE_LEN = 255;

        protected string $tableName = 'session_data';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get(), engine: 'InnoDB')
                ->addIdColumn()
                ->addColumn(column: 'sessionId', type: 'INT', length: '11')
                ->addColumn(column: 'param', type: 'VARCHAR', length: static::PARAM_LEN . '')
                ->addColumn(column: 'value', type: 'VARCHAR', length: static::VALUE_LEN . '')
                ->addIndex(indexName: 'session_param', indexes: ['sessionId', 'param'], type: 'UNIQUE')
            ;
        }
    }
}
