<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Email\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Outbound email send queue with backoff retries.
     *
     * Lifecycle of a row:
     *   1. `FwEmailQueueService::enqueue(...)` inserts with status='queued'.
     *   2. `processQueue(N)` (cron-driven) flips to 'sending' atomically,
     *      hands off to Mailer, flips to 'sent' on success or 'error'
     *      with an increment to `attempts` + a backed-off `next_attempt_at`
     *      on failure.
     *   3. Final attempts that hit `attempts == max_attempts` stay
     *      'error' with `next_attempt_at = NULL` — terminal, no further
     *      retries unless someone calls `retry($id)`.
     */
    abstract class FwEmailQueue extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'recipient_email', type: 'VARCHAR', length: '255')
                ->addColumn(column: 'subject', type: 'VARCHAR', length: '255')
                ->addColumn(column: 'body_html', type: 'LONGTEXT')
                ->addColumn(column: 'status', type: 'ENUM', length: "'queued','sending','sent','error'")
                ->addColumn(column: 'attempts', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'max_attempts', type: 'INT', length: '11', null: false, default: '3')
                ->addColumn(column: 'next_attempt_at', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'sent_at', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'status', indexes: ['status'])
                ->addIndex(indexName: 'account_id', indexes: ['account_id'])
                ->addIndex(indexName: 'next_attempt_at', indexes: ['next_attempt_at'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at'])
            ;
        }
    }
}
