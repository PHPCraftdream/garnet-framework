<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwSupportMessages extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'ticket_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'author_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'body', type: 'TEXT', length: '', null: false)
                ->addColumn(column: 'is_internal', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addColumn(column: 'msg_type', type: 'ENUM', length: "'user','staff','system'", null: false, default: "'user'")
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'ticket_id', indexes: ['ticket_id'])
                ->addIndex(indexName: 'ticket_internal', indexes: ['ticket_id', 'is_internal'])
            ;
        }
    }
}
