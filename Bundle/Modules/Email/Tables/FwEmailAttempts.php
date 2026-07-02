<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Email\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Per-attempt audit log for `FwEmailQueue` send attempts. One row
     * per `processQueue` iteration that touched a queue item — captures
     * which attempt-number this was, success or error, and the error
     * text on failure. Useful for diagnosing flaky SMTP / DNS issues
     * without losing the original queue row to status churn.
     */
    abstract class FwEmailAttempts extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'queue_id', type: 'INT', length: '11')
                ->addColumn(column: 'attempt_number', type: 'INT', length: '11')
                ->addColumn(column: 'status', type: 'ENUM', length: "'success','error'")
                ->addColumn(column: 'error_message', type: 'TEXT', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'queue_id', indexes: ['queue_id'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at'])
            ;
        }
    }
}
