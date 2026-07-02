<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Comments\Tables {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Generic comments-on-anything store.
     *
     * `entity_type` is a free-form VARCHAR — the app decides which
     * entity types it accepts (in its controller / service layer).
     * Originally an ENUM('expert') tied to MyApp expert profiles;
     * widening to VARCHAR lets any app reuse this without a schema
     * fork. Concrete subclasses pin `$tableName`.
     *
     * Indexes:
     *   entity            (entity_type, entity_id)
     *   author_id
     *   entity_created    (entity_type, entity_id, created_at)
     *   is_hidden
     */
    abstract class FwComments extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'author_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'entity_type', type: 'VARCHAR', length: '64', null: false, default: "''")
                ->addColumn(column: 'entity_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'body', type: 'TEXT', length: '', null: false)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'is_hidden', type: 'TINYINT', length: '1', null: false, default: '0')
                ->addIndex(indexName: 'entity', indexes: ['entity_type', 'entity_id'])
                ->addIndex(indexName: 'author_id', indexes: ['author_id'])
                ->addIndex(indexName: 'entity_created', indexes: ['entity_type', 'entity_id', 'created_at'])
                ->addIndex(indexName: 'is_hidden', indexes: ['is_hidden'])
            ;
        }

        public static function getForEntity(string $entityType, int $entityId, bool $includeHidden = false): array {
            return static::get()->selectAll(function (SelectInterface $q) use ($entityType, $entityId, $includeHidden): void {
                $q->where('entity_type = ? AND entity_id = ?', [$entityType, $entityId]);

                if (!$includeHidden) {
                    $q->where('is_hidden = ?', [0]);
                }
                $q->orderBy(['created_at ASC']);
            });
        }

        public static function countForEntity(string $entityType, int $entityId, bool $includeHidden = false): int {
            $rows = static::get()->selectAll(function (SelectInterface $q) use ($entityType, $entityId, $includeHidden): void {
                $q->resetCols();
                $q->cols(['COUNT(*) as cnt']);
                $q->where('entity_type = ? AND entity_id = ?', [$entityType, $entityId]);

                if (!$includeHidden) {
                    $q->where('is_hidden = ?', [0]);
                }
            });

            return (int)($rows[0]['cnt'] ?? 0);
        }
    }
}
