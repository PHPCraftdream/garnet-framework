<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwStaticPages extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'slug', type: 'VARCHAR', length: '128', null: false)
                ->addColumn(column: 'title', type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addColumn(column: 'is_published', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addColumn(column: 'visibility', type: 'VARCHAR', length: '16', null: false, default: "'all'")
                ->addColumn(column: 'meta_description', type: 'VARCHAR', length: '500', null: false, default: "''")
                ->addColumn(column: 'seo_title', type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addColumn(column: 'og_image', type: 'VARCHAR', length: '500', null: false, default: "''")
                ->addColumn(column: 'max_width', type: 'VARCHAR', length: '16', null: false, default: "'3xl'")
                ->addColumn(column: 'sort_order', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'updated_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'updated_by', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'header_snippet_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'footer_snippet_id', type: 'INT', length: '11', null: true)
                ->addIndex(indexName: 'slug', indexes: ['slug'], type: 'UNIQUE')
                ->addIndex(indexName: 'is_published', indexes: ['is_published'])
            ;
        }
    }
}
