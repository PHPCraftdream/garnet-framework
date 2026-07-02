<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwAdminActionLog extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'actor_id', type: 'INT', length: '11')
                ->addColumn(column: 'actor_login', type: 'VARCHAR', length: '128')
                ->addColumn(column: 'target_id', type: 'INT', length: '11')
                ->addColumn(column: 'target_login', type: 'VARCHAR', length: '128')
                ->addColumn(column: 'action', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'old_value', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'new_value', type: 'VARCHAR', length: '64')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'target_id', indexes: ['target_id'])
                ->addIndex(indexName: 'actor_id', indexes: ['actor_id'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at']);
        }

        public function writeLog(
            int $actorId,
            string $actorLogin,
            int $targetId,
            string $targetLogin,
            string $action,
            string $oldValue,
            string $newValue,
        ): void {
            $this->insert([
                'actor_id' => $actorId,
                'actor_login' => $actorLogin,
                'target_id' => $targetId,
                'target_login' => $targetLogin,
                'action' => $action,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'created_at' => time(),
            ]);
        }
    }
}
