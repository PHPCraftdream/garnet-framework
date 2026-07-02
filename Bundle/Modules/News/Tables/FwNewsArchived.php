<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\News\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwNewsArchived extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'event_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'archived_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'account_event', indexes: ['account_id', 'event_id'], type: 'UNIQUE')
                ->addIndex(indexName: 'event_id', indexes: ['event_id'])
            ;
        }
    }
}
