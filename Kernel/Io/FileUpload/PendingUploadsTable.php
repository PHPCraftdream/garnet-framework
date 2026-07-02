<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Framework-level pending uploads table.
     *
     * Tracks files uploaded in Phase 1 (temp storage) before they
     * are committed to entity-specific storage in Phase 2.
     * Expired records (24h) are cleaned by PendingUploadManager::cleanupExpired().
     */
    class PendingUploadsTable extends DbTable {
        protected string $tableName = 'pending_uploads';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'session_id', type: 'VARCHAR', length: '64', null: false)
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'stored_name', type: 'VARCHAR', length: '255', null: false)
                ->addColumn(column: 'original_name', type: 'VARCHAR', length: '255', null: false)
                ->addColumn(column: 'mime_type', type: 'VARCHAR', length: '100', null: false)
                ->addColumn(column: 'size', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'session_id', indexes: ['session_id'])
                ->addIndex(indexName: 'account_id', indexes: ['account_id'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at'])
            ;
        }
    }
}
