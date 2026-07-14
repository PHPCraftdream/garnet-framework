<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    abstract class FwAccountBalance extends DbTable {
        protected string $primaryKey = 'id';

        /**
         * Override in subclass to return the matching FwBalanceLedger subclass.
         */
        abstract protected static function ledgerTable(): FwBalanceLedger;

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'balance', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'updated_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'account_id', indexes: ['account_id'], type: 'UNIQUE')
            ;
        }

        /**
         * Recompute the cached balance from the append-only ledger and
         * upsert it in a SINGLE atomic statement (INSERT … SELECT … ON
         * DUPLICATE KEY UPDATE) rather than a separate read-then-write.
         *
         * Two `recalculate()` calls for the same account can otherwise
         * interleave: whichever UPDATE commits last wins, even if its own
         * SELECT ran before the other call's ledger INSERT committed — a
         * classic lost-update race that silently drops an entry from the
         * cached balance until the next recalculate(). A single INSERT ON
         * DUPLICATE KEY UPDATE is executed atomically by MySQL (the unique
         * key on account_id serialises concurrent statements for the same
         * row), so the ledger SUM and the cache write can no longer be torn
         * apart by a concurrent call.
         */
        public static function recalculate(int $accountId): void {
            $ledgerTable = static::ledgerTable()->getTableName();
            $balanceTable = static::get()->getTableName();
            $now = time();

            QueryEx::get()->ex(
                "INSERT INTO `{$balanceTable}` (account_id, balance, updated_at)
                 VALUES (?, (
                     SELECT COALESCE(SUM(CASE WHEN is_credit = 1 THEN amount ELSE -amount END), 0)
                     FROM `{$ledgerTable}`
                     WHERE account_id = ?
                 ), ?)
                 ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = VALUES(updated_at)",
                [$accountId, $accountId, $now],
            );
        }

        public static function getBalance(int $accountId): int {
            $row = static::get()->selectOneByField('account_id', $accountId);

            return (int)($row['balance'] ?? 0);
        }
    }
}
