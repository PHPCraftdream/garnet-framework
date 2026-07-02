<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwBalanceLedger extends DbTable {
        protected string $primaryKey = 'id';

        /**
         * Override in subclass to return the matching FwAccountBalance subclass.
         */
        abstract protected static function balanceTable(): FwAccountBalance;

        /**
         * Return the ENUM values for entry_type column.
         * Override to customise for your app.
         *
         * @return string ENUM length string, e.g. "'top_up','booking_payment','manual'"
         */
        protected static function entryTypeEnum(): string {
            return "'top_up','booking_invoice','booking_payment','booking_refund','manual'";
        }

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'is_credit', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addColumn(column: 'amount', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'entry_type', type: 'ENUM', length: static::entryTypeEnum())
                ->addColumn(column: 'ref_type', type: 'VARCHAR', length: '50', null: true)
                ->addColumn(column: 'ref_id', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'note', type: 'VARCHAR', length: '255', null: true)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'account_id', indexes: ['account_id'])
                ->addIndex(indexName: 'ref', indexes: ['ref_type', 'ref_id'])
            ;
        }

        public static function addEntry(
            int $accountId,
            bool $isCredit,
            int $amount,
            string $entryType,
            string $refType = '',
            int $refId = 0,
            string $note = '',
        ): void {
            static::get()->insert([
                'account_id' => $accountId,
                'is_credit' => $isCredit ? 1 : 0,
                'amount' => $amount,
                'entry_type' => $entryType,
                'ref_type' => $refType ?: null,
                'ref_id' => $refId ?: null,
                'note' => $note ?: null,
                'created_at' => time(),
            ]);

            static::balanceTable()::recalculate($accountId);
        }
    }
}
