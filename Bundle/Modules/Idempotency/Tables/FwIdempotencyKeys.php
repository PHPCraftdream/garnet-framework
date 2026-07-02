<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Idempotency\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Stores idempotency-key receipts for client retries.
     *
     * Row lifecycle:
     *   1. Middleware reserves a row with http_status=0 (in-flight) on first
     *      hit of a (account_id, idem_key, route_path) triple.
     *   2. After the controller completes, the same middleware updates the
     *      row with the response status + body and finalized_at.
     *   3. Repeat hits with the same triple short-circuit and replay the row.
     *
     * UNIQUE on the triple makes the reservation race-free: parallel POSTs
     * with identical key get one INSERT winner, the other catches the
     * duplicate-key exception and falls back to replay.
     */
    abstract class FwIdempotencyKeys extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id',    type: 'INT',        length: '11',  null: false, default: '0')
                ->addColumn(column: 'idem_key',      type: 'VARCHAR',    length: '64',  null: false)
                ->addColumn(column: 'route_path',    type: 'VARCHAR',    length: '255', null: false)
                ->addColumn(column: 'http_status',   type: 'INT',        length: '11',  null: false, default: '0')
                ->addColumn(column: 'content_type',  type: 'VARCHAR',    length: '128', null: true)
                ->addColumn(column: 'response_body', type: 'MEDIUMTEXT', null: true)
                ->addColumn(column: 'created_at',    type: 'INT',        length: '11',  null: false, default: '0')
                ->addColumn(column: 'finalized_at',  type: 'INT',        length: '11',  null: false, default: '0')
                ->addIndex(indexName: 'uniq_triple', indexes: ['account_id', 'idem_key', 'route_path'], type: 'UNIQUE')
                ->addIndex(indexName: 'created_at',  indexes: ['created_at'])
            ;
        }
    }
}
