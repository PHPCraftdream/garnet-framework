<?php declare(strict_types=1);

/**
 * Framework-level date helpers. Mirror of Apps/MyApp/Common/System/DateUtils,
 * exposed here so Framework code (e.g. AuthMiddleware emails) can render
 * timestamps in the recipient's timezone without depending on any single
 * application namespace.
 *
 * Every time-bearing column in the database is stored as unix-timestamp
 * (INT). Use this helper anywhere the server renders a timestamp into text
 * that a *user* will read.
 */

namespace PHPCraftdream\Garnet\Kernel\Core\Tools {
    use DateTime;
    use DateTimeZone;
    use Throwable;

    final class DateTools {
        /**
         * Format a unix timestamp in the given user's timezone.
         *
         * @param int         $ts     Unix seconds (UTC).
         * @param string|null $userTz IANA tz id (e.g. "Europe/Moscow"). Falls back to UTC when null/empty/invalid.
         * @param string      $format PHP DateTime format string (default: 'Y-m-d H:i').
         */
        public static function formatForUser(int $ts, ?string $userTz, string $format = 'Y-m-d H:i'): string {
            if ($ts <= 0) {
                return '';
            }

            try {
                $dt = (new DateTime('@' . $ts))->setTimezone(static::resolveZone($userTz));
            } catch (Throwable) {
                $dt = (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
            }

            return $dt->format($format);
        }

        /**
         * Resolve a tz id into a DateTimeZone, falling back to UTC for empty
         * or unknown values.
         */
        public static function resolveZone(?string $tz): DateTimeZone {
            if ($tz === null || $tz === '') {
                return new DateTimeZone('UTC');
            }

            try {
                return new DateTimeZone($tz);
            } catch (Throwable) {
                return new DateTimeZone('UTC');
            }
        }
    }
}
