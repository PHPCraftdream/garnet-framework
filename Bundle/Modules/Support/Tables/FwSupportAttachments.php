<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwSupportAttachments extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'message_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'original_name', type: 'VARCHAR', length: '255', null: false)
                ->addColumn(column: 'stored_name', type: 'VARCHAR', length: '255', null: false)
                ->addColumn(column: 'mime_type', type: 'VARCHAR', length: '100', null: false)
                ->addColumn(column: 'size', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'message_id', indexes: ['message_id'])
            ;
        }

        /**
         * Get all attachments for given message IDs.
         * @param int[] $messageIds
         * @return array<int, array[]> Grouped by message_id
         */
        public static function getByMessageIds(array $messageIds): array {
            if (empty($messageIds)) {
                return [];
            }

            $rows = static::get()->selectAll(function ($q) use ($messageIds): void {
                $q->where('message_id IN (?)', [array_map('intval', $messageIds)]);
                $q->orderBy(['id ASC']);
            });

            $grouped = [];

            foreach ($rows as $row) {
                $grouped[(int)$row['message_id']][] = $row;
            }

            return $grouped;
        }
    }
}
