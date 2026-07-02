<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Invite\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Invite-token registry — issue, validate and consume one-time
     * (or N-time) signup tokens. Schema is generic; the app that
     * uses this module extends the class and pins `$tableName` to
     * its own prefix (e.g. `Apps/MyApp/Common/Tables/InviteTokens`
     * sets `'ir_invite_tokens'`).
     *
     * Pairs with `FwInviteRegistrations` (audit trail of who
     * consumed which token) and `FwInviteTokenService` (the
     * generate/validate/consume API).
     */
    abstract class FwInviteTokens extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'token', type: 'VARCHAR', length: '64', null: false)
                ->addColumn(column: 'label', type: 'VARCHAR', length: '255', null: false, default: "''")
                ->addColumn(column: 'expires_at', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'max_uses', type: 'INT', length: '11', null: false, default: '1')
                ->addColumn(column: 'uses_left', type: 'INT', length: '11', null: false, default: '1')
                ->addColumn(column: 'is_disabled', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'created_by', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'account_type', type: 'VARCHAR', length: '16', null: false, default: "'user'")
                ->addIndex(indexName: 'token', indexes: ['token'], type: 'UNIQUE')
            ;
        }
    }
}
