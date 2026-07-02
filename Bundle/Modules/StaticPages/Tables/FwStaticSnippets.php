<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwStaticSnippets extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'slug', type: 'VARCHAR', length: '128', null: false)
                ->addColumn(column: 'name', type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addColumn(column: 'snippet_type', type: 'VARCHAR', length: '32', null: false, default: "'block'")
                ->addColumn(column: 'content', type: 'TEXT', length: '', null: false)
                ->addColumn(column: 'is_active', type: 'TINYINT', length: '1', null: false, default: '1')
                ->addColumn(column: 'sort_order', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'updated_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'slug', indexes: ['slug'], type: 'UNIQUE')
            ;
        }
    }
}
