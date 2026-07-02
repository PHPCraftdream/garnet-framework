<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwMailLogRecipients extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'mail_log_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'recipient_email', type: 'VARCHAR', length: '255')
                ->addIndex(indexName: 'mail_log_id', indexes: ['mail_log_id'])
                ->addIndex(indexName: 'account_id', indexes: ['account_id']);
        }
    }
}
