<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwSupportAssignmentLog extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'ticket_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'actor_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'from_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'to_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'ticket_id', indexes: ['ticket_id'])
                ->addIndex(indexName: 'to_id', indexes: ['to_id'])
            ;
        }
    }
}
