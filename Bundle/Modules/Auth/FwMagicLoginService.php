<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth {
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Tables\FwMagicLoginTokens;
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;

    /**
     * Generate, validate and consume magic-login tokens.
     *
     * Table is pinned at runtime via `setTableClasses(...)` so a
     * concrete app can wire up its own subclass of FwMagicLoginTokens
     * (which provides the actual table name). Same DI shape as
     * FwInviteTokenService in this bundle.
     *
     * Usage (from the app's init):
     *   FwMagicLoginService::setTableClasses(
     *       MyApp\MagicLoginTokens::class,
     *   );
     */
    class FwMagicLoginService {
        /** @var class-string<FwMagicLoginTokens>|null */
        private static ?string $tokensTable = null;

        /**
         * @param class-string<FwMagicLoginTokens> $tokens
         */
        public static function setTableClasses(string $tokens): void {
            static::$tokensTable = $tokens;
        }

        private static function tokens(): FwMagicLoginTokens {
            if (static::$tokensTable === null) {
                throw new LogicException('FwMagicLoginService::setTableClasses() must be called before use.');
            }

            return static::$tokensTable::get();
        }

        /**
         * Generate a new magic-login token, valid for 5 minutes.
         * Returns the full row including the generated 32-char hex
         * token string.
         */
        public static function generate(string $email, string $returnUri, string $consentPdAt, string $consentMarketingAt): array {
            $tokenString = bin2hex(random_bytes(16));

            $data = [
                'token' => $tokenString,
                'email' => $email,
                'created_at' => time(),
                'expires_at' => time() + 300,
                'used_at' => null,
                'return_uri' => $returnUri,
                'consent_pd_at' => $consentPdAt,
                'consent_marketing_at' => $consentMarketingAt,
            ];

            $id = static::tokens()->insert($data);
            $data['id'] = $id;

            return $data;
        }

        /**
         * Validate a token string. Returns ['valid' => true, 'token' => $row]
         * or ['valid' => false, 'reason' => '...'].
         * Reasons: 'unknown', 'expired', 'used'
         */
        public static function validate(string $tokenString): array {
            if ($tokenString === '') {
                return ['valid' => false, 'reason' => 'unknown'];
            }

            $row = static::tokens()->selectOneByField('token', $tokenString);

            if (!$row) {
                return ['valid' => false, 'reason' => 'unknown'];
            }

            if (!empty($row['used_at'])) {
                return ['valid' => false, 'reason' => 'used'];
            }

            if ((int)($row['expires_at'] ?? 0) < time()) {
                return ['valid' => false, 'reason' => 'expired'];
            }

            return ['valid' => true, 'token' => $row];
        }

        /**
         * Consume a magic-login token (CAS-style atomic single-use).
         * Returns true if successful, false if the token was already
         * used (race condition) — this is the ONLY guarantee of
         * one-time use.
         */
        public static function consume(int $tokenId): bool {
            $tableName = static::tokens()->getTableName();

            $affected = CasUpdate::exec(
                "UPDATE {$tableName} SET used_at = ? WHERE id = ? AND used_at IS NULL",
                [time(), $tokenId]
            );

            if ($affected === 0) {
                return false;
            }

            return true;
        }
    }
}
