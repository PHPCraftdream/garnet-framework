<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class FwImReadStatus extends DbTable {
        protected string $primaryKey = 'id';

        /**
         * Subclasses must provide the conversations table class.
         * @return class-string<FwImConversations>
         */
        protected static function conversationsTableClass(): string {
            return FwImConversations::class;
        }

        /**
         * Subclasses must provide the messages table class.
         * @return class-string<FwImMessages>
         */
        protected static function messagesTableClass(): string {
            return FwImMessages::class;
        }

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'conversation_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'account_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'last_read_message_id', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'updated_at', type: 'INT', length: '11', null: false, default: '0')
                ->addIndex(indexName: 'conv_account', indexes: ['conversation_id', 'account_id'], type: 'UNIQUE')
                ->addIndex(indexName: 'account_id', indexes: ['account_id'])
            ;
        }

        public static function markRead(int $conversationId, int $accountId, int $lastMessageId): void {
            $existing = static::get()->selectAll(function ($q) use ($conversationId, $accountId): void {
                $q->where('conversation_id = ? AND account_id = ?', [$conversationId, $accountId]);
            });

            $now = time();

            if (!empty($existing)) {
                if ((int)$existing[0]['last_read_message_id'] < $lastMessageId) {
                    static::get()->updateByField([
                        'last_read_message_id' => $lastMessageId,
                        'updated_at' => $now,
                    ], 'id', (int)$existing[0]['id']);
                }
            } else {
                static::get()->insert([
                    'conversation_id' => $conversationId,
                    'account_id' => $accountId,
                    'last_read_message_id' => $lastMessageId,
                    'updated_at' => $now,
                ]);
            }
        }

        public static function getUnreadCountForUser(int $accountId): int {
            $convsClass = static::conversationsTableClass();
            $msgsClass = static::messagesTableClass();

            $convs = $convsClass::get()->selectAll(function ($q) use ($accountId): void {
                $q->where('participant_a = ? OR participant_b = ?', [$accountId, $accountId]);
            });

            $total = 0;

            foreach ($convs as $conv) {
                $convId = (int)$conv['id'];
                $readStatus = static::get()->selectAll(function ($q) use ($convId, $accountId): void {
                    $q->where('conversation_id = ? AND account_id = ?', [$convId, $accountId]);
                });
                $lastRead = !empty($readStatus) ? (int)$readStatus[0]['last_read_message_id'] : 0;

                $unread = $msgsClass::get()->selectAll(function ($q) use ($convId, $accountId, $lastRead): void {
                    $q->resetCols();
                    $q->cols(['COUNT(*) as cnt']);
                    $q->where('conversation_id = ? AND sender_id != ? AND id > ?', [$convId, $accountId, $lastRead]);
                });
                $total += (int)($unread[0]['cnt'] ?? 0);
            }

            return $total;
        }
    }
}
