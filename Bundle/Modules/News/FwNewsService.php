<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\News {
    use Aura\SqlQuery\Common\SelectInterface;
    use Closure;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsArchived;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsEvents;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsReads;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;

    abstract class FwNewsService {
        // Audience types
        public const AUDIENCE_BROADCAST = 'broadcast';

        public const AUDIENCE_PERSONAL = 'personal';

        // Feed TTL: only show events from last 90 days
        public const FEED_TTL_SEC = 90 * 86400; // 7776000

        // Throttle for deduplication (e.g. IM notifications)
        public const MESSAGE_THROTTLE_SEC = 3600; // 1 hour

        /**
         * Return the concrete FwNewsEvents subclass instance.
         */
        abstract protected static function eventsTable(): FwNewsEvents;

        /**
         * Return the concrete FwNewsReads subclass instance.
         */
        abstract protected static function readsTable(): FwNewsReads;

        /**
         * Return the concrete FwNewsArchived subclass instance.
         */
        abstract protected static function archivedTable(): FwNewsArchived;

        /**
         * Create a broadcast event (visible to all users except actor).
         * `targetKey` is an optional indexed correlation key (e.g. "slot:42") used by
         * `deleteByTargetKey` to purge stale events when the underlying entity changes.
         */
        public static function createBroadcast(string $eventType, int $actorId, array $payload, ?string $targetKey = null): int {
            return (int)static::eventsTable()->insert([
                'event_type' => $eventType,
                'audience_type' => self::AUDIENCE_BROADCAST,
                'audience_id' => null,
                'actor_id' => $actorId,
                'target_key' => $targetKey,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => time(),
            ]);
        }

        /**
         * Create a personal event (visible only to audience_id).
         */
        public static function createPersonal(string $eventType, int $actorId, int $audienceId, array $payload, ?string $targetKey = null): int {
            return (int)static::eventsTable()->insert([
                'event_type' => $eventType,
                'audience_type' => self::AUDIENCE_PERSONAL,
                'audience_id' => $audienceId,
                'actor_id' => $actorId,
                'target_key' => $targetKey,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => time(),
            ]);
        }

        /**
         * Create a throttled event: skip if a recent event with the same type
         * already exists for the same actor->audience pair within MESSAGE_THROTTLE_SEC.
         */
        public static function createThrottledEvent(string $eventType, int $actorId, int $audienceId, array $payload): ?int {
            $cutoff = time() - static::MESSAGE_THROTTLE_SEC;

            // Check if there's a recent event for this recipient from this sender
            $recent = static::eventsTable()->selectAll(function (SelectInterface $q) use ($eventType, $actorId, $audienceId, $cutoff): void {
                $q->where('event_type = ?', [$eventType])
                    ->where('audience_type = ?', [self::AUDIENCE_PERSONAL])
                    ->where('audience_id = ?', [$audienceId])
                    ->where('actor_id = ?', [$actorId])
                    ->where('created_at > ?', [$cutoff])
                    ->limit(1);
            });

            if (!empty($recent)) {
                return null; // Throttled
            }

            return static::createPersonal($eventType, $actorId, $audienceId, $payload);
        }

        /**
         * Get news feed for a user with pagination.
         *
         * @param int $accountId
         * @param int $page
         * @param int $perPage
         * @param bool $includeArchived
         * @return array{items: array, page: int, perPage: int, total: int, totalPages: int, unreadCount: int}
         */
        public static function getFeed(int $accountId, int $page, int $perPage, bool $includeArchived = false): array {
            $whereCallback = static::feedWhereCallback($accountId, $includeArchived);

            // Count total
            $total = static::eventsTable()->getCount($whereCallback);

            $totalPages = max(1, (int)ceil($total / $perPage));

            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;

            // Fetch page
            $items = static::eventsTable()->selectAll(function (SelectInterface $q) use ($whereCallback, $perPage, $offset): void {
                $whereCallback($q);
                $q->orderBy(['created_at DESC']);
                $q->limit($perPage);
                $q->offset($offset);
            });

            // Get read status for these items
            $eventIds = array_map(fn ($e) => (int)$e['id'], $items);
            $readMap = [];

            if (!empty($eventIds)) {
                $reads = static::readsTable()->selectAll(function (SelectInterface $q) use ($accountId, $eventIds): void {
                    $q->where('account_id = ?', [$accountId])
                        ->where('event_id IN (?)', [$eventIds]);
                });

                foreach ($reads as $r) {
                    $readMap[(int)$r['event_id']] = (int)$r['read_at'];
                }
            }

            // Get archive status
            $archivedMap = [];

            if ($includeArchived && !empty($eventIds)) {
                $archived = static::archivedTable()->selectAll(function (SelectInterface $q) use ($accountId, $eventIds): void {
                    $q->where('account_id = ?', [$accountId])
                        ->where('event_id IN (?)', [$eventIds]);
                });

                foreach ($archived as $a) {
                    $archivedMap[(int)$a['event_id']] = true;
                }
            }

            // Enrich items
            $enriched = [];

            foreach ($items as $item) {
                $eid = (int)$item['id'];
                $enriched[] = [
                    'id' => $eid,
                    'event_type' => $item['event_type'],
                    'payload' => json_decode($item['payload'], true) ?: [],
                    'actor_id' => (int)$item['actor_id'],
                    'created_at' => (int)$item['created_at'],
                    'is_read' => isset($readMap[$eid]),
                    'read_at' => $readMap[$eid] ?? null,
                    'is_archived' => isset($archivedMap[$eid]),
                ];
            }

            // Let subclasses enrich/rewrite items (e.g. resolve fresh actor names).
            $enriched = static::decorateFeedItems($enriched);

            // Unread count (total, not just this page)
            $unreadCount = static::getUnreadCount($accountId);

            return [
                'items' => $enriched,
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'unreadCount' => $unreadCount,
            ];
        }

        /**
         * Count unread events for a user.
         */
        public static function getUnreadCount(int $accountId): int {
            $feedWhere = static::feedWhereCallback($accountId, false);
            $readsTable = static::readsTable()->getTableName();

            $rows = static::eventsTable()->selectAll(function (SelectInterface $q) use ($feedWhere, $accountId, $readsTable): void {
                $q->resetCols();
                $q->cols(['COUNT(*) as cnt']);
                $feedWhere($q);
                $q->where("id NOT IN (SELECT event_id FROM {$readsTable} WHERE account_id = :unread_account)", [':unread_account' => $accountId]);
            });

            return (int)($rows[0]['cnt'] ?? 0);
        }

        /**
         * Mark events as read.
         */
        public static function markRead(int $accountId, array $eventIds): void {
            if (empty($eventIds)) {
                return;
            }

            $now = time();

            foreach ($eventIds as $eid) {
                $query = static::readsTable()->newInsert();
                $query->cols([
                    'account_id' => $accountId,
                    'event_id' => (int)$eid,
                    'read_at' => $now,
                ]);
                static::readsTable()->getQueryEx()->exInsertIgnore($query);
            }
        }

        /**
         * Mark all visible events as read.
         */
        public static function markAllRead(int $accountId): void {
            $eventsTable = static::eventsTable()->getTableName();
            $readsTable = static::readsTable()->getTableName();
            $archivedTable = static::archivedTable()->getTableName();
            $now = time();
            $ttlCutoff = $now - static::FEED_TTL_SEC;

            $sql = "INSERT IGNORE INTO {$readsTable} (account_id, event_id, read_at) "
                . "SELECT ?, id, ? FROM {$eventsTable} "
                . "WHERE ((audience_type = 'broadcast' AND actor_id != ?) OR (audience_type = 'personal' AND audience_id = ?)) "
                . 'AND created_at > ? '
                . "AND id NOT IN (SELECT event_id FROM {$archivedTable} WHERE account_id = ?) "
                . "AND id NOT IN (SELECT event_id FROM {$readsTable} WHERE account_id = ?)";

            DbPool::get()->query($sql, [$accountId, $now, $accountId, $accountId, $ttlCutoff, $accountId, $accountId]);
        }

        /**
         * Archive events (hide from feed, recoverable).
         */
        public static function archive(int $accountId, array $eventIds): void {
            if (empty($eventIds)) {
                return;
            }

            $now = time();

            foreach ($eventIds as $eid) {
                $query = static::archivedTable()->newInsert();
                $query->cols([
                    'account_id' => $accountId,
                    'event_id' => (int)$eid,
                    'archived_at' => $now,
                ]);
                static::archivedTable()->getQueryEx()->exInsertIgnore($query);
            }
        }

        /**
         * Unarchive events.
         */
        public static function unarchive(int $accountId, array $eventIds): void {
            if (empty($eventIds)) {
                return;
            }

            foreach ($eventIds as $eid) {
                static::archivedTable()->deleteBy(function (SelectInterface $q) use ($accountId, $eid): void {
                    $q->where('account_id = :del_account AND event_id = :del_event', [':del_account' => $accountId, ':del_event' => (int)$eid]);
                });
            }
        }

        /**
         * Delete every event matching `target_key`, optionally constrained to a single
         * event type. Use when the underlying entity changes state and prior
         * announcements no longer apply (e.g. a slot's `new_slot` event when the slot
         * is booked/cancelled/deleted). Reads/archived rows that referenced the
         * deleted event become harmless orphans.
         */
        public static function deleteByTargetKey(string $targetKey, ?string $eventType = null): void {
            static::eventsTable()->getQueryEx()->ex(
                $eventType === null
                    ? 'DELETE FROM ' . static::eventsTable()->getTableName() . ' WHERE target_key = ?'
                    : 'DELETE FROM ' . static::eventsTable()->getTableName() . ' WHERE target_key = ? AND event_type = ?',
                $eventType === null ? [$targetKey] : [$targetKey, $eventType],
            );
        }

        /**
         * Hook: let subclasses enrich or rewrite the feed items before they are
         * returned (e.g. re-resolve the actor's current display name so stale or
         * "#id" names captured in old payloads are corrected). Default: no-op.
         *
         * @param array $items
         * @return array
         */
        protected static function decorateFeedItems(array $items): array {
            return $items;
        }

        /**
         * Build WHERE callback for user's visible feed.
         */
        protected static function feedWhereCallback(int $accountId, bool $includeArchived): Closure {
            $archivedTable = static::archivedTable()->getTableName();
            $ttlCutoff = time() - static::FEED_TTL_SEC;

            return function (SelectInterface $q) use ($accountId, $includeArchived, $archivedTable, $ttlCutoff): void {
                // Visible = (broadcast AND actor != me) OR (personal AND audience_id = me)
                $q->where(
                    "((audience_type = 'broadcast' AND actor_id != :feed_account) OR (audience_type = 'personal' AND audience_id = :feed_account))",
                    [':feed_account' => $accountId]
                );

                // TTL: only show events from last 90 days
                $q->where('created_at > :feed_ttl', [':feed_ttl' => $ttlCutoff]);

                if (!$includeArchived) {
                    $q->where(
                        "id NOT IN (SELECT event_id FROM {$archivedTable} WHERE account_id = :feed_archive_account)",
                        [':feed_archive_account' => $accountId]
                    );
                }
            };
        }
    }
}
