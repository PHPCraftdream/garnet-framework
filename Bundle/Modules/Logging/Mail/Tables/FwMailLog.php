<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwMailLog extends DbTable {
        protected string $primaryKey = 'id';

        /**
         * Override in subclass to return the matching FwMailLogRecipients subclass.
         */
        abstract protected static function recipientsTable(): FwMailLogRecipients;

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'recipient_email', type: 'VARCHAR', length: '255')
                ->addColumn(column: 'mail_type', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'subject', type: 'VARCHAR', length: '255')
                ->addColumn(column: 'body_html', type: 'LONGTEXT', null: true)
                ->addColumn(column: 'status', type: 'VARCHAR', length: '32')
                ->addColumn(column: 'error_log', type: 'TEXT', null: true)
                ->addColumn(column: 'meta', type: 'TEXT', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'account_id', indexes: ['account_id'])
                ->addIndex(indexName: 'mail_type', indexes: ['mail_type'])
                ->addIndex(indexName: 'status', indexes: ['status'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at']);
        }
    }
}
