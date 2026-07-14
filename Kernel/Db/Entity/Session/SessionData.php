<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session {
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;

    class SessionData {
        protected static ?SessionData $instance = null;

        protected function __construct() {
        }

        public static function get(): SessionData {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        public function getDataAsync(string $sessionName, ?callable $callback = null): IDbMySQLiLink {
            $sessionTable = SessionTable::get();

            $onSelectSession = function ($sessionRow) use ($callback): void {
                $sessionId = intval($sessionRow['id'] ?? 0);
                $lastUsage = intval($sessionRow['lastUsage'] ?? 0);

                if (empty($sessionId)) {
                    $callback([$sessionId, []]);

                    return;
                }

                $sessionDataTable = SessionDataTable::get();

                $wait = $sessionDataTable->simpleSelectByFieldAsync(
                    'sessionId',
                    $sessionId,
                    callback: function (array $sessionDataRows) use ($lastUsage, $sessionId, $callback): void {
                        $sessionData = [];

                        foreach ($sessionDataRows as $item) {
                            $param = $item['param'] ?? null;
                            $value = $item['value'] ?? null;

                            if (!empty($param) && !empty($value)) {
                                $sessionData[$param] = $value;
                            }

                            if ($param === Account::SESSION_AUTH_LOGIN) {
                                Account::get($value)->readDbAsync();
                            }
                        }

                        if (abs(time() - $lastUsage) > 86400) {
                            $sessionTable = SessionTable::get();
                            $sessionTable->updateByFieldAsync(['lastUsage' => time()], 'id', $sessionId);
                        }

                        $callback([$sessionId, $sessionData]);
                    }
                );

                $callback($wait);
            };

            return $sessionTable->simpleSelectOneByFieldAsync('name', $sessionName, callback: $onSelectSession);
        }

        public function touchSessionAsync(string $sessionValue, ?callable $callback = null): void {
            [$sql, $params] = QueryTools::makeInsertBatchNamed(
                SessionTable::get()->getTableName(),
                [['name' => $sessionValue, 'lastUsage' => time()]],
                'lastUsage = ' . time()
            );

            $queryEx = SessionTable::get()->getQueryEx();
            $queryEx->exAsync($sql, $params, $callback);
        }

        public function flush(string $sessionValue, int|null $sessionId, array $data, ?callable $callback = null): void {
            // The outer $sessionId is the caller's authoritative id (null on
            // first flush of a brand-new session, an int on every later one).
            // The inner closure receives a value derived from mysqli's
            // insert_id, which is the row id for a NEW row but `true` when
            // the INSERT ... ON DUPLICATE KEY UPDATE hits an existing row
            // (UPDATE does not bump insert_id). Coercing `true` to an int
            // via the bind layer would silently write sessionId=1 for every
            // data row, scattering values across the wrong session. Prefer
            // the caller's id when we have it; fall back to the inner
            // result only on the first-write path.
            $callerSessionId = $sessionId;
            static::touchSessionAsync($sessionValue, function ($sessionIdFromTouch) use ($callerSessionId, $data, $callback): void {
                $sessionId = $callerSessionId ?? (is_int($sessionIdFromTouch) ? $sessionIdFromTouch : null);

                if (empty($data)) {
                    $callback($sessionId ?? $sessionIdFromTouch);

                    return;
                }

                if ($sessionId === null) {
                    // No usable session id (neither cached nor returned). Don't
                    // attempt the data INSERT with `true` masquerading as an id.
                    $callback($sessionIdFromTouch);

                    return;
                }

                $queryData = [];

                foreach ($data as $param => $value) {
                    $queryData[] = [
                        'sessionId' => $sessionId,
                        'param' => $param,
                        'value' => $value,
                    ];
                }

                [$sql, $params] = QueryTools::makeInsertBatchNamed(
                    SessionDataTable::get()->getTableName(),
                    $queryData,
                    'value = VALUES(value)',
                    true,
                );

                $queryEx = SessionDataTable::get()->getQueryEx();
                $queryEx->exAsync($sql, $params);

                $callback($sessionId);
            });
        }

        public function flushUnset(int|null $sessionId, array $unsetValues): void {
            if (empty($unsetValues) || empty($sessionId)) {
                return;
            }

            $delete = SessionDataTable::get()->newDelete();
            $delete->where('`sessionId`=:sessionId AND `param` IN (:params)', [
                'sessionId' => $sessionId,
                'params' => $unsetValues,
            ]);
            $queryEx = SessionDataTable::get()->getQueryEx();
            $queryEx->exDeleteAsync($delete);
        }

        public function init(): void {
            SessionTable::init()->ex();
            SessionDataTable::init()->ex();
        }
    }
}
