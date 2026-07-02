<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Tables\FwEntityHistory;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use Throwable;

    /**
     * Generic entity audit log.
     *
     * Plug-and-play: callers just need to pick (and pass on every call) a
     * concrete subclass of {@see FwEntityHistory}. The schema is created
     * lazily on first use — no migration required.
     *
     * Typical use:
     *   EntityHistoryService::record(EntityHistory::class, 'static_page', $pageId, 'update', $diff);
     *   EntityHistoryService::list(EntityHistory::class, 'static_page', $pageId);
     */
    class EntityHistoryService {
        /** @var array<class-string, true> */
        private static array $ensured = [];

        /**
         * Lazily ensure the underlying table exists. Idempotent within a
         * request — first call issues a CREATE TABLE IF NOT EXISTS, the
         * rest are no-ops.
         *
         * @param class-string<FwEntityHistory> $tableClass
         */
        public static function ensureTable(string $tableClass): void {
            if (isset(static::$ensured[$tableClass])) {
                return;
            }
            $tableClass::init()->ex();
            static::$ensured[$tableClass] = true;
        }

        /**
         * Record a single mutation.
         *
         * @param class-string<FwEntityHistory>             $tableClass
         * @param string                                    $entityType  Logical bucket name, e.g. "static_page".
         * @param int|string                                $entityId    Target record id.
         * @param string                                    $action      "create" | "update" | "delete" | custom token.
         * @param array<string, array{old: mixed, new: mixed}>  $diff   Field-level diff. Use {@see diff()} to build.
         * @param array<string, mixed>|null                 $snapshot    Optional after-snapshot of the row.
         * @param string                                    $comment     Optional human-readable note.
         */
        public static function record(
            string $tableClass,
            string $entityType,
            int|string $entityId,
            string $action,
            array $diff = [],
            ?array $snapshot = null,
            string $comment = '',
        ): void {
            static::ensureTable($tableClass);

            [$actorId, $actorLogin] = static::resolveActor();
            [$ip, $userAgent] = static::resolveRequestMeta();

            $tableClass::get()->insert([
                'entity_type' => mb_substr($entityType, 0, 64),
                'entity_id' => mb_substr((string)$entityId, 0, 128),
                'action' => mb_substr($action, 0, 32),
                'actor_id' => $actorId,
                'actor_login' => mb_substr($actorLogin, 0, 128),
                'diff_json' => $diff !== [] ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
                'snapshot_json' => $snapshot !== null ? json_encode($snapshot, JSON_UNESCAPED_UNICODE) : null,
                'comment' => mb_substr($comment, 0, 500),
                'created_at' => time(),
                'ip' => mb_substr($ip, 0, 45),
                'user_agent' => mb_substr($userAgent, 0, 255),
            ]);
        }

        /**
         * List recent history entries for a single record, newest first.
         *
         * @param class-string<FwEntityHistory> $tableClass
         * @return array<int, array<string, mixed>>
         */
        public static function list(
            string $tableClass,
            string $entityType,
            int|string $entityId,
            int $limit = 100,
            int $offset = 0,
        ): array {
            static::ensureTable($tableClass);

            $rows = $tableClass::get()->selectAll(
                static function (SelectInterface $q) use ($entityType, $entityId, $limit, $offset): void {
                    $q->where('entity_type = :t AND entity_id = :i', [
                        't' => $entityType,
                        'i' => (string)$entityId,
                    ]);
                    $q->orderBy(['id DESC']);
                    $q->limit($limit);

                    if ($offset > 0) {
                        $q->offset($offset);
                    }
                }
            );

            foreach ($rows as &$row) {
                $row['diff'] = static::decodeJson($row['diff_json'] ?? null);
                $row['snapshot'] = static::decodeJson($row['snapshot_json'] ?? null);
                unset($row['diff_json'], $row['snapshot_json']);
            }

            return $rows;
        }

        /**
         * Build a field-level diff between two associative rows. Skips fields
         * listed in $ignoredFields. Equal values produce no entry.
         *
         * @param array<string, mixed> $before
         * @param array<string, mixed> $after
         * @param array<int, string>   $ignoredFields
         * @return array<string, array{old: mixed, new: mixed}>
         */
        public static function diff(array $before, array $after, array $ignoredFields = []): array {
            $diff = [];
            $ignored = array_flip($ignoredFields);
            $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

            foreach ($keys as $k) {
                if (isset($ignored[$k])) {
                    continue;
                }
                $oldVal = $before[$k] ?? null;
                $newVal = $after[$k] ?? null;

                if (static::valuesEqual($oldVal, $newVal)) {
                    continue;
                }
                $diff[$k] = ['old' => $oldVal, 'new' => $newVal];
            }

            return $diff;
        }

        private static function valuesEqual(mixed $a, mixed $b): bool {
            if (is_scalar($a) && is_scalar($b)) {
                return (string)$a === (string)$b;
            }

            return $a === $b;
        }

        /** @return array{0:int,1:string} [id, login] */
        private static function resolveActor(): array {
            try {
                $account = Account::fromSession();

                if ($account) {
                    return [(int)$account->readParam('id'), (string)$account->readParam('login')];
                }
            } catch (Throwable) {
                // session not available (CLI/cron) — fall through
            }

            return [0, ''];
        }

        /** @return array{0:string,1:string} [ip, userAgent] */
        private static function resolveRequestMeta(): array {
            $ip = (string)($_SERVER['HTTP_X_REAL_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '');

            // X-Forwarded-For can be a comma-separated list — take the first.
            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip, 2)[0]);
            }
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

            return [$ip, $ua];
        }

        /** @return array<mixed>|null */
        private static function decodeJson(mixed $raw): ?array {
            if ($raw === null || $raw === '') {
                return null;
            }
            $decoded = json_decode((string)$raw, true);

            return is_array($decoded) ? $decoded : null;
        }
    }
}
