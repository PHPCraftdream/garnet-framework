<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Idempotency {
    use GuzzleHttp\Psr7\Response;
    use PHPCraftdream\Garnet\Bundle\Modules\Idempotency\Tables\FwIdempotencyKeys;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use Psr\Http\Message\ResponseInterface;
    use Throwable;

    /**
     * Server-side idempotency for client retries on flaky networks.
     *
     * The client tags any mutating POST with a unique `X-Idempotency-Key`
     * header (UUID-style, 16-64 chars). On the first hit we reserve the
     * key in db_*_idempotency_keys and let the controller run; on every
     * subsequent hit with the same (account_id, key, route) triple we
     * short-circuit and replay the stored response. Double-clicks,
     * network retries and refresh-after-submit all collapse into one
     * controller execution.
     *
     * Wiring (per-app, in the bootstrap):
     *   IdempotencyMiddleware::setTableClass(IdempotencyKeys::class);
     *
     * Pipeline:
     *   - Add `[IdempotencyMiddleware::class, 'before']` to every route's
     *     pre-controller chain (after auth so account_id is known).
     *   - Call `IdempotencyMiddleware::finalize($response)` once after
     *     the router dispatches, so the captured response is written
     *     back to the row that 'before' reserved.
     *
     * GET / non-POST requests are passed through untouched. Requests
     * without the header are also untouched (back-compat).
     */
    class IdempotencyMiddleware {
        public const HEADER_NAME = 'X-Idempotency-Key';

        public const HEADER_SERVER_KEY = 'HTTP_X_IDEMPOTENCY_KEY';

        /** Soft TTL — rows older than this are considered replayable but eligible for cleanup. */
        public const TTL_SECONDS = 86400; // 24h

        /** @var class-string<FwIdempotencyKeys>|null */
        private static ?string $tableClass = null;

        /** Per-request context populated by before(); consumed by finalize(). */
        private static ?int $rowId = null;

        /**
         * Bind the concrete app-level table class. Call once at boot.
         *
         * @param class-string<FwIdempotencyKeys> $tableClass
         */
        public static function setTableClass(string $tableClass): void {
            self::$tableClass = $tableClass;
        }

        public static function before(IGlobalReqParams $globals, IRouterUriParams $params): ?ResponseInterface {
            if (!$globals->isPost()) {
                return null;
            }

            if (self::$tableClass === null) {
                return null;
            }

            $key = self::readKey($globals);

            if ($key === null) {
                return null;
            }

            $accountId = self::resolveAccountId();
            $routePath = self::normaliseRoutePath($globals->getUri());

            $tableClass = self::$tableClass;
            $table = $tableClass::get();

            // Replay path: existing finalized row → return cached response.
            // Existing in-flight row → another request is still mid-execution,
            // tell the client to retry shortly. UNIQUE on the triple makes
            // both the "exists" check and the reservation race-free.
            $existing = self::loadRow($table, $accountId, $key, $routePath);

            if ($existing) {
                if ((int)$existing['http_status'] > 0) {
                    return self::buildReplayResponse($existing);
                }

                return self::inFlightResponse();
            }

            $now = time();

            try {
                $rowId = (int)$table->insert([
                    'account_id' => $accountId,
                    'idem_key' => $key,
                    'route_path' => $routePath,
                    'http_status' => 0,
                    'content_type' => null,
                    'response_body' => null,
                    'created_at' => $now,
                    'finalized_at' => 0,
                ]);
            } catch (DbException $e) {
                if (!CasUpdate::isDuplicateKeyError($e)) {
                    throw $e;
                }

                // Lost the reservation race — re-read and replay (or 409 if still in flight).
                $existing = self::loadRow($table, $accountId, $key, $routePath);

                if ($existing && (int)$existing['http_status'] > 0) {
                    return self::buildReplayResponse($existing);
                }

                return self::inFlightResponse();
            }

            self::$rowId = $rowId;

            return null;
        }

        /**
         * Persist the response captured from the dispatched controller.
         * Call once after Router::dispatch(); pass-through-safe (no-op
         * when before() didn't reserve a row).
         */
        public static function finalize(mixed $response): mixed {
            if (self::$rowId === null) {
                return $response;
            }

            if (self::$tableClass === null) {
                return $response;
            }

            $rowId = self::$rowId;
            self::$rowId = null;

            [$status, $contentType, $body] = self::extractResponseParts($response);
            $tableClass = self::$tableClass;

            try {
                $tableClass::get()->updateById([
                    'http_status' => $status,
                    'content_type' => $contentType,
                    'response_body' => $body,
                    'finalized_at' => time(),
                ], $rowId);
            } catch (Throwable) {
                // Capture failure must never break the user's response.
            }

            return $response;
        }

        /** Best-effort delete of receipts older than TTL_SECONDS. Wire to cron. */
        public static function gc(int $beforeTs): int {
            if (self::$tableClass === null) {
                return 0;
            }
            $tableClass = self::$tableClass;
            $table = $tableClass::get();
            $name = $table->getTableName();
            $table->getQueryEx()->ex("DELETE FROM `{$name}` WHERE created_at < ?", [$beforeTs]);

            return 0;
        }

        // ── Internals ───────────────────────────────────────────────

        private static function loadRow(FwIdempotencyKeys $table, int $accountId, string $key, string $routePath): ?array {
            return $table->selectOneByField('idem_key', $key, function ($q) use ($accountId, $routePath): void {
                $q->where('account_id = :aid', ['aid' => $accountId]);
                $q->where('route_path = :path', ['path' => $routePath]);
            });
        }

        private static function readKey(IGlobalReqParams $globals): ?string {
            $raw = (string)$globals->readServerValue(self::HEADER_SERVER_KEY, '');
            $raw = trim($raw);

            if ($raw === '') {
                return null;
            }

            // Cap length and restrict charset — UUIDs, base62 nonces etc.
            if (strlen($raw) < 16 || strlen($raw) > 64) {
                return null;
            }

            if (!preg_match('/^[A-Za-z0-9_\-]+$/', $raw)) {
                return null;
            }

            return $raw;
        }

        private static function resolveAccountId(): int {
            try {
                $account = Account::fromSession();

                return $account ? (int)$account->id() : 0;
            } catch (Throwable) {
                return 0;
            }
        }

        private static function normaliseRoutePath(string $uri): string {
            // Drop query string and trim trailing slashes; cap to fit column.
            $path = (string)parse_url($uri, PHP_URL_PATH);
            $path = '/' . trim($path, '/');

            if (strlen($path) > 255) {
                $path = substr($path, 0, 255);
            }

            return $path;
        }

        private static function buildReplayResponse(array $row): ResponseInterface {
            $status = (int)$row['http_status'];
            $body = (string)($row['response_body'] ?? '');
            $contentType = (string)($row['content_type'] ?? '');

            $response = (new Response())->withStatus($status);

            if ($contentType !== '') {
                $response = $response->withHeader('Content-Type', $contentType);
            }
            $response->getBody()->write($body);

            return $response->withHeader('X-Idempotent-Replay', '1');
        }

        private static function inFlightResponse(): ResponseInterface {
            return ControllerTools::JSON(
                ['error' => 'Operation in progress, retry shortly'],
                status: 409,
            );
        }

        /**
         * @return array{0:int,1:?string,2:?string}
         */
        private static function extractResponseParts(mixed $response): array {
            if ($response instanceof ResponseInterface) {
                $status = $response->getStatusCode();
                $contentType = $response->getHeaderLine('Content-Type') ?: null;
                $body = (string)$response->getBody();
                $response->getBody()->rewind();

                return [$status, $contentType, $body];
            }

            if (is_string($response)) {
                return [200, 'text/html; charset=UTF-8', $response];
            }

            // null / other — nothing meaningful to replay; mark as 204.
            return [204, null, null];
        }
    }
}
