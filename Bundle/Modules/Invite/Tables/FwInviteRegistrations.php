<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Invite\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Audit trail of token consumption: one row per successful
     * registration via an invite token. Records account_id, IP and
     * user-agent so an admin can see who came in through which token.
     */
    abstract class FwInviteRegistrations extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'token_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'registered_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'ip', type: 'VARCHAR', length: '45', null: false, default: "''")
                ->addColumn(column: 'user_agent', type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addIndex(indexName: 'token_id', indexes: ['token_id'])
            ;
        }
    }
}
