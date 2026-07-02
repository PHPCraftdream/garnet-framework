<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwImMessages extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'conversation_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'sender_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'body', type: 'TEXT', length: '', null: false)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'conversation_id', indexes: ['conversation_id'])
                ->addIndex(indexName: 'conv_created', indexes: ['conversation_id', 'created_at'])
            ;
        }
    }
}
