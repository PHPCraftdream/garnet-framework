<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Deduplicated client-side JS error log.
     *
     * Each row is one unique error signature (sha256 of message+file+line).
     * Re-occurrence increments `count` and refreshes `last_seen_at` instead
     * of inserting a new row. Designed to survive an error storm without
     * filling the table.
     *
     * Used by the admin /admin/logs/?tab=js-errors viewer in apps that
     * expose it.
     */
    abstract class FwJsErrors extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'hash', type: 'VARCHAR', length: '64', null: false, default: '')
                ->addColumn(column: 'message', type: 'VARCHAR', length: '1024', null: false, default: '')
                ->addColumn(column: 'stack', type: 'TEXT', null: true)
                ->addColumn(column: 'file', type: 'VARCHAR', length: '512', null: true)
                ->addColumn(column: 'line', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'col', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'url', type: 'VARCHAR', length: '1024', null: true)
                ->addColumn(column: 'user_agent', type: 'VARCHAR', length: '512', null: true)
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'count', type: 'INT', length: '11', null: false, default: '1')
                ->addColumn(column: 'first_seen_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'last_seen_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'uq_hash', indexes: ['hash'], type: 'UNIQUE')
                ->addIndex(indexName: 'last_seen_at', indexes: ['last_seen_at'])
                ->addIndex(indexName: 'account_id', indexes: ['account_id']);
        }
    }
}
