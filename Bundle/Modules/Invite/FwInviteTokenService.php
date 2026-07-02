<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Invite {
    use Aura\SqlQuery\Common\SelectInterface;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Invite\Tables\FwInviteRegistrations;
    use PHPCraftdream\Garnet\Bundle\Modules\Invite\Tables\FwInviteTokens;
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;

    /**
     * Generate, validate and consume invite tokens.
     *
     * Tables are pinned at runtime via `setTableClasses(...)` so a
     * concrete app can wire up its own subclasses of FwInviteTokens
     * / FwInviteRegistrations (which provide the actual table names).
     * Same DI shape as IdempotencyMiddleware in this bundle.
     *
     * Usage (from the app's init):
     *   FwInviteTokenService::setTableClasses(
     *       MyApp\InviteTokens::class,
     *       MyApp\InviteRegistrations::class,
     *   );
     */
    class FwInviteTokenService {
        /** @var class-string<FwInviteTokens>|null */
        private static ?string $tokensTable = null;

        /** @var class-string<FwInviteRegistrations>|null */
        private static ?string $registrationsTable = null;

        /**
         * @param class-string<FwInviteTokens>        $tokens
         * @param class-string<FwInviteRegistrations> $registrations
         */
        public static function setTableClasses(string $tokens, string $registrations): void {
            static::$tokensTable = $tokens;
            static::$registrationsTable = $registrations;
        }

        private static function tokens(): FwInviteTokens {
            if (static::$tokensTable === null) {
                throw new LogicException('FwInviteTokenService::setTableClasses() must be called before use.');
            }

            return static::$tokensTable::get();
        }

        private static function registrations(): FwInviteRegistrations {
            if (static::$registrationsTable === null) {
                throw new LogicException('FwInviteTokenService::setTableClasses() must be called before use.');
            }

            return static::$registrationsTable::get();
        }

        /**
         * Validate a token string. Returns ['valid' => true, 'token' => $row]
         * or ['valid' => false, 'reason' => '...'].
         * Reasons: 'unknown', 'expired', 'exhausted', 'disabled'
         */
        public static function validate(string $tokenString): array {
            if ($tokenString === '') {
                return ['valid' => false, 'reason' => 'unknown'];
            }

            $row = static::tokens()->selectOneByField('token', $tokenString);

            if (!$row) {
                return ['valid' => false, 'reason' => 'unknown'];
            }

            if ((int)($row['is_disabled'] ?? 0) === 1) {
                return ['valid' => false, 'reason' => 'disabled'];
            }

            $expiresAt = $row['expires_at'] ?? null;

            if ($expiresAt !== null && (int)$expiresAt > 0 && (int)$expiresAt < time()) {
                return ['valid' => false, 'reason' => 'expired'];
            }

            if ((int)($row['uses_left'] ?? 0) <= 0) {
                return ['valid' => false, 'reason' => 'exhausted'];
            }

            return ['valid' => true, 'token' => $row];
        }

        /**
         * Consume one use of a token (CAS-style atomic decrement).
         * Records the registration. Returns true if successful,
         * false if token was already exhausted (race condition).
         */
        public static function consume(int $tokenId, int $accountId, string $ip, string $userAgent): bool {
            $tableName = static::tokens()->getTableName();

            // Atomic decrement: only succeeds if uses_left > 0
            $affected = CasUpdate::exec(
                "UPDATE {$tableName} SET uses_left = uses_left - 1 WHERE id = ? AND uses_left > 0",
                [$tokenId]
            );

            if ($affected === 0) {
                return false;
            }

            static::registrations()->insert([
                'token_id' => $tokenId,
                'account_id' => $accountId,
                'registered_at' => time(),
                'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 255),
            ]);

            return true;
        }

        /**
         * Generate a new invite token. Returns the full row including
         * the generated 32-char hex token string.
         *
         * $accountType pins what the registered account's `type` will
         * be set to ('user' or 'expert'). Lets admins issue separate
         * invite links for regular users vs. experts.
         */
        public static function generate(string $label, ?int $expiresAt, int $maxUses, int $createdBy, string $accountType = 'user'): array {
            $tokenString = bin2hex(random_bytes(16));

            $data = [
                'token' => $tokenString,
                'label' => $label,
                'expires_at' => $expiresAt,
                'max_uses' => $maxUses,
                'uses_left' => $maxUses,
                'is_disabled' => 0,
                'created_at' => time(),
                'created_by' => $createdBy,
                'account_type' => $accountType,
            ];

            $id = static::tokens()->insert($data);
            $data['id'] = $id;

            return $data;
        }

        /**
         * Disable tokens that are expired or exhausted. Intended to
         * be called periodically (cron) to keep the active list clean.
         * Returns ['expired' => N, 'exhausted' => N].
         */
        public static function disableStale(int $limit = 500): array {
            $stats = ['expired' => 0, 'exhausted' => 0];
            $now = time();
            $table = static::tokens();

            $expired = $table->selectAll(function (SelectInterface $q) use ($now, $limit): void {
                $q->where('is_disabled = 0');
                $q->where('expires_at IS NOT NULL');
                $q->where('expires_at > 0');
                $q->where('expires_at < ?', [$now]);
                $q->limit($limit);
            });

            foreach ($expired as $row) {
                $table->updateById(['is_disabled' => 1], $row['id']);
            }
            $stats['expired'] = count($expired);

            $exhausted = $table->selectAll(function (SelectInterface $q) use ($limit): void {
                $q->where('is_disabled = 0');
                $q->where('uses_left <= 0');
                $q->limit($limit);
            });

            foreach ($exhausted as $row) {
                $table->updateById(['is_disabled' => 1], $row['id']);
            }
            $stats['exhausted'] = count($exhausted);

            return $stats;
        }
    }
}
