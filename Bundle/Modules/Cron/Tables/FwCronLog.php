<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Cron\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Cron task run log.
     *
     * One row per task run: inserted at start with `status='running'`,
     * updated at finish with success/error + duration + captured stdio.
     *
     * Writer side: FwCronService (in Kernel) drives the runs; apps
     * extending FwCronService (e.g. MyApp's AppCronService) wrap each
     * task in a `runWithLogging($name, $callback, $stdio)` that
     * inserts here.
     *
     * Reader side: /admin/logs/?tab=cron, via the FwBundle Logging
     * module's cron-log adapter.
     */
    abstract class FwCronLog extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'task_name', type: 'VARCHAR', length: '100', null: false, default: '')
                ->addColumn(column: 'started_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'finished_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'duration_ms', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'status', type: 'ENUM', length: "'success','error','running'", null: false, default: "'running'")
                ->addColumn(column: 'output', type: 'TEXT', null: true)
                ->addColumn(column: 'error_message', type: 'VARCHAR', length: '1024', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'task_name', indexes: ['task_name'])
                ->addIndex(indexName: 'started_at', indexes: ['started_at']);
        }
    }
}
