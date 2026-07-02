<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class DbAccount extends DbTable {
        public const NAME_LENGTH = 32;

        protected string $tableName = 'accounts';

        protected string $primaryKey = 'id';

        public const LOGIN_TYPE_EMAIL = 'email';

        public const LOGIN_TYPE_USERNAME = 'username';

        public static function init(): ITableBuilderDriver {
            $len = static::NAME_LENGTH . '';

            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'login', type: 'VARCHAR', length: $len, null: false)
                ->addColumn(column: 'login_type', type: 'VARCHAR', length: $len, null: false)
                ->addColumn(column: 'name', type: 'VARCHAR', length: $len)
                ->addColumn(column: 'time_zone', type: 'VARCHAR', length: $len)
                ->addColumn(column: 'token16', type: 'VARCHAR', length: '16')
                ->addColumn(column: 'token32', type: 'VARCHAR', length: '32')
                ->addColumn(column: 'reg_time', type: 'int', length: '11')
                ->addColumn(column: 'last_auth_time', type: 'int', length: '11')
                ->addColumn(column: 'last_online_time', type: 'int', length: '11')
                ->addColumn(column: 'about', type: 'VARCHAR', length: '1024')
                ->addIndex(indexName: 'login', indexes: ['login'], type: 'UNIQUE')
                ->addIndex('token16', ['token16'])
                ->addIndex('token32', ['token32'])
            ;
        }
    }
}
