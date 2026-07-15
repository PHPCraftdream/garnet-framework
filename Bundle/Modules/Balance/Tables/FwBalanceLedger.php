<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use Throwable;

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
                ->addIndex(indexName: 'uq_idempotent', indexes: ['account_id', 'entry_type', 'ref_type', 'ref_id'], type: 'UNIQUE')
            ;
        }

        /**
         * Append a ledger entry. Idempotent when ref_type/ref_id are provided:
         * a duplicate (account_id, entry_type, ref_type, ref_id) is silently
         * ignored and balance is NOT re-recalculated (no-op).
         *
         * Race-safety: the UNIQUE INDEX `uq_idempotent` on the table guarantees
         * atomicity — two concurrent INSERT attempts with the same key will result
         * in exactly one row; the loser gets a duplicate-key error caught here.
         *
         * Entries without a ref (ref_type='' / ref_id=0 → stored as NULL) are
         * never deduplicated because MySQL treats each NULL as distinct in
         * unique indexes.
         */
        public static function addEntry(
            int $accountId,
            bool $isCredit,
            int $amount,
            string $entryType,
            string $refType = '',
            int $refId = 0,
            string $note = '',
        ): void {
            try {
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
            } catch (Throwable $e) {
                if (CasUpdate::isDuplicateKeyError($e)) {
                    // Idempotent no-op: entry with same (account_id, entry_type, ref_type, ref_id) already exists.
                    return;
                }

                throw $e;
            }

            static::balanceTable()::recalculate($accountId);
        }
    }
}
