<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwStaticPageBlocks extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'page_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'block_type', type: 'VARCHAR', length: '32', null: false, default: "'text'")
                ->addColumn(column: 'content', type: 'TEXT', length: '', null: false)
                ->addColumn(column: 'sort_order', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'is_hidden', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addColumn(column: 'visibility', type: 'VARCHAR', length: '16', null: false, default: "'all'")
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'page_id', indexes: ['page_id'])
                ->addIndex(indexName: 'page_order', indexes: ['page_id', 'sort_order'])
            ;
        }
    }
}
