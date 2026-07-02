<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables {
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

        public static function recalculate(int $accountId): void {
            $rows = static::ledgerTable()->selectAll(function ($q) use ($accountId): void {
                $q->resetCols();
                $q->cols(['SUM(CASE WHEN is_credit = 1 THEN amount ELSE -amount END) AS bal']);
                $q->where('account_id = ?', [$accountId]);
            });
            $balance = (int)($rows[0]['bal'] ?? 0);

            $now = time();
            $existing = static::get()->selectOneByField('account_id', $accountId);

            if ($existing) {
                static::get()->updateByField(
                    ['balance' => $balance, 'updated_at' => $now],
                    'account_id', $accountId,
                );
            } else {
                static::get()->insert([
                    'account_id' => $accountId,
                    'balance' => $balance,
                    'updated_at' => $now,
                ]);
            }
        }

        public static function getBalance(int $accountId): int {
            $row = static::get()->selectOneByField('account_id', $accountId);

            return (int)($row['balance'] ?? 0);
        }
    }
}
