<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwImConversations extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'participant_a', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'participant_b', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'last_message_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'pair', indexes: ['participant_a', 'participant_b'], type: 'UNIQUE')
                ->addIndex(indexName: 'participant_a', indexes: ['participant_a'])
                ->addIndex(indexName: 'participant_b', indexes: ['participant_b'])
                ->addIndex(indexName: 'last_message_at', indexes: ['last_message_at'])
            ;
        }

        /**
         * Find or create conversation between two users.
         * Always stores min(a,b) as participant_a, max(a,b) as participant_b.
         */
        public static function findOrCreate(int $userA, int $userB): int {
            $a = min($userA, $userB);
            $b = max($userA, $userB);

            $existing = static::get()->selectAll(function (SelectInterface $q) use ($a, $b): void {
                $q->where('participant_a = ? AND participant_b = ?', [$a, $b]);
            });

            if (!empty($existing)) {
                return (int)$existing[0]['id'];
            }

            return (int)static::get()->insert([
                'participant_a' => $a,
                'participant_b' => $b,
                'last_message_at' => time(),
                'created_at' => time(),
            ]);
        }

        /**
         * Check if user is participant of conversation.
         */
        public static function isParticipant(int $conversationId, int $accountId): bool {
            $conv = static::get()->selectOneByField('id', $conversationId);

            if (!$conv) {
                return false;
            }

            return (int)$conv['participant_a'] === $accountId || (int)$conv['participant_b'] === $accountId;
        }

        /**
         * Get partner account_id in a conversation.
         */
        public static function getPartnerId(array $conv, int $myId): int {
            return (int)$conv['participant_a'] === $myId ? (int)$conv['participant_b'] : (int)$conv['participant_a'];
        }
    }
}
