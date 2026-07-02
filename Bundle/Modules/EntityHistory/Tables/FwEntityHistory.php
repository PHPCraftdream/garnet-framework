<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Generic entity audit log. Each row records a single mutation
     * (create/update/delete/custom) to a record identified by entity_type +
     * entity_id, along with the actor and a JSON diff.
     *
     * Apps subclass this to set a concrete tableName (e.g. ir_entity_history).
     * The schema is created lazily at first use via EntityHistoryService —
     * no migration is required to plug in a new entity.
     */
    abstract class FwEntityHistory extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'entity_type', type: 'VARCHAR', length: '64',  null: false, default: "''")
                ->addColumn(column: 'entity_id',   type: 'VARCHAR', length: '128', null: false, default: "''")
                ->addColumn(column: 'action',      type: 'VARCHAR', length: '32',  null: false, default: "'update'")
                ->addColumn(column: 'actor_id',    type: 'INT',     length: '11',  null: false, default: '0')
                ->addColumn(column: 'actor_login', type: 'VARCHAR', length: '128', null: false, default: "''")
                ->addColumn(column: 'diff_json',     type: 'TEXT', null: true)
                ->addColumn(column: 'snapshot_json', type: 'TEXT', null: true)
                ->addColumn(column: 'comment',     type: 'VARCHAR', length: '500', null: false, default: "''")
                ->addColumn(column: 'created_at',  type: 'INT',     length: '11',  null: false, default: '0')
                ->addColumn(column: 'ip',          type: 'VARCHAR', length: '45',  null: false, default: "''")
                ->addColumn(column: 'user_agent',  type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addIndex(indexName: 'entity', indexes: ['entity_type', 'entity_id'])
                ->addIndex(indexName: 'actor_id', indexes: ['actor_id'])
                ->addIndex(indexName: 'created_at', indexes: ['created_at']);
        }
    }
}
