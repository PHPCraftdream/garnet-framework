<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory {
    use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Tables\FwEntityHistory;

    /**
     * Thin façade over EntityHistoryService for app-settings audit logs.
     *
     * Settings are key/value pairs without an entity id, so we use a
     * fixed entity_type/entity_id pair plus per-key diff entries.
     *
     * Sensitive fields (smtp_password by default) are masked before
     * any value is stored. Callers can extend the masked-field set.
     */
    class SystemSettingsHistory {
        public const ENTITY_TYPE = 'app_settings';

        public const ENTITY_ID = 'global';

        public const DEFAULT_SECRET_FIELDS = [
            'smtp_password',
            'password',
            'api_key',
            'secret',
            'token',
        ];

        /**
         * Record a settings change. The diff is computed by EntityHistoryService::diff
         * with secret-field masking applied. No history row is written when the
         * effective diff is empty.
         *
         * @param class-string<FwEntityHistory> $tableClass
         * @param array<string, mixed>          $before
         * @param array<string, mixed>          $after
         * @param array<int, string>            $extraSecretFields  Fields to mask in addition to the defaults.
         */
        public static function recordIfChanged(
            string $tableClass,
            array $before,
            array $after,
            array $extraSecretFields = [],
        ): void {
            $secretFields = array_unique(array_merge(static::DEFAULT_SECRET_FIELDS, $extraSecretFields));

            $beforeMasked = static::maskSecrets($before, $secretFields);
            $afterMasked = static::maskSecrets($after,  $secretFields);

            $diff = EntityHistoryService::diff($beforeMasked, $afterMasked);

            if ($diff === []) {
                return;
            }

            EntityHistoryService::record(
                tableClass: $tableClass,
                entityType: static::ENTITY_TYPE,
                entityId: static::ENTITY_ID,
                action: 'update',
                diff: $diff,
            );
        }

        /**
         * Return the recent settings change history (newest first).
         * Each row is enriched with actor_name and actor_login_resolved so
         * the UI doesn't need to do a separate lookup. We resolve actors in
         * a single batch query to avoid N+1.
         *
         * @param class-string<FwEntityHistory> $tableClass
         * @return array<int, array<string, mixed>>
         */
        public static function recent(string $tableClass, int $limit = 100): array {
            $rows = EntityHistoryService::list(
                tableClass: $tableClass,
                entityType: static::ENTITY_TYPE,
                entityId: static::ENTITY_ID,
                limit: $limit,
            );

            $actorIds = array_values(array_unique(array_filter(array_map(
                static fn ($r) => (int)($r['actor_id'] ?? 0),
                $rows,
            ))));
            $actorMap = [];

            if (!empty($actorIds)) {
                $accounts = \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::getAccounts(
                    selectCallback: static function (\Aura\SqlQuery\Common\SelectInterface $select) use ($actorIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $actorIds)]);
                    },
                    accountDataFields: [],
                );

                foreach ($accounts as $a) {
                    $actorMap[(int)$a['id']] = $a;
                }
            }

            foreach ($rows as &$row) {
                $aid = (int)($row['actor_id'] ?? 0);
                $row['actor_name'] = (string)($actorMap[$aid]['name'] ?? '');
                $row['actor_login_resolved'] = (string)($actorMap[$aid]['login'] ?? ($row['actor_login'] ?? ''));
            }

            return $rows;
        }

        /**
         * Replace any value whose key (case-insensitive) contains a
         * secret-field token with a fixed redacted marker so that
         * passwords and tokens never enter the audit log.
         *
         * Recursive: nested arrays (e.g. ['smtp' => ['password' => '…']])
         * are walked in full.
         *
         * @param array<string, mixed> $data
         * @param array<int, string>   $secretFields
         * @return array<string, mixed>
         */
        public static function maskSecrets(array $data, array $secretFields): array {
            $result = [];
            $marker = '***';
            $loweredSecrets = array_map('strtolower', $secretFields);

            foreach ($data as $k => $v) {
                $kLower = strtolower((string)$k);
                $isSecret = false;

                foreach ($loweredSecrets as $token) {
                    if ($token !== '' && str_contains($kLower, $token)) {
                        $isSecret = true;

                        break;
                    }
                }

                if ($isSecret) {
                    $result[$k] = $v === '' || $v === null ? $v : $marker;
                } elseif (is_array($v)) {
                    $result[$k] = static::maskSecrets($v, $secretFields);
                } else {
                    $result[$k] = $v;
                }
            }

            return $result;
        }
    }
}
