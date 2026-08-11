<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\RateLimit {
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;

    /**
     * Sliding-window rate limiter backed by temp files.
     * Thread-safe via exclusive file locking (flock).
     *
     * Fail-open policy: when the backing store is unusable (fds exhausted,
     * lock unavailable, a symlink attack detected, …) `hit()` allows the
     * request rather than rejecting it. This is a deliberate choice for a
     * RATE LIMIT specifically — availability wins over strict limiting,
     * since the alternative (fail-closed) turns any storage hiccup into a
     * hard outage for every legitimate user sharing that limiter. Unlike
     * the old behavior, every fail-open path now logs via Logger::SYSTEM_LOGGER
     * (falling back to error_log() if that logger isn't configured yet), so
     * the fallback is visible instead of silent.
     */
    class RateLimit {
        /**
         * Attempt to consume one slot in the rate-limit window.
         *
         * Returns true  — request is allowed (slot consumed).
         * Returns false — limit exceeded; the file is NOT modified.
         *
         * @param string $key      Unique rate-limit key, e.g. 'email_auth:user@example.com'
         * @param int    $maxHits  Maximum allowed hits within $windowSec
         * @param int    $windowSec Sliding window size in seconds
         * @param string $tmpDir   Directory for state files (defaults to sys_get_temp_dir())
         */
        public static function hit(string $key, int $maxHits, int $windowSec, string $tmpDir = ''): bool {
            $file = self::filePath($key, $tmpDir);
            $now = time();

            // Opportunistic cleanup: delete a stale state file for THIS key
            // whose entire window has already expired. This only ever touches
            // the file for the key being hit right now — no directory scan,
            // no cron dependency. Note: keys that are never revisited (e.g.
            // an attacker rotating through random emails) never get cleaned up
            // by this mechanism — their inodes persist until external cleanup.
            self::cleanupIfExpired($file, $now, $windowSec);

            // Refuse to operate on a symlink: a shared temp dir is
            // world-writable, so an attacker could pre-create
            // rl_<md5(guessable-key)>.json as a symlink pointing anywhere
            // writable by this process, turning fopen('c+') into an
            // arbitrary-file-write/truncate primitive.
            if (is_link($file)) {
                self::logFailure('Refusing to use rate-limit file, symlink detected: ' . $file);

                return true; // fail-open: see class-level note on fail-open policy
            }

            $fp = @fopen($file, 'c+');

            if ($fp === false) {
                self::logFailure('Rate-limit storage unavailable (fopen failed): ' . $file);

                return true; // fail-open: storage unavailable
            }

            // Re-check after opening — TOCTOU window between is_link() and
            // fopen() could still be raced, but fstat()'ing the open handle
            // and comparing against a fresh lstat() catches a symlink that
            // was swapped in between the two calls.
            if (!self::openedPathIsSafe($fp, $file)) {
                fclose($fp);
                self::logFailure('Refusing to use rate-limit file, unsafe path after open: ' . $file);

                return true; // fail-open: see class-level note on fail-open policy
            }

            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                self::logFailure('Rate-limit lock could not be acquired: ' . $file);

                return true; // fail-open: can't lock
            }

            $timestamps = self::readTimestamps($fp, $now, $windowSec);
            $allowed = count($timestamps) < $maxHits;

            if ($allowed) {
                $timestamps[] = $now;
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($timestamps));
            }

            flock($fp, LOCK_UN);
            fclose($fp);

            return $allowed;
        }

        /**
         * Seconds remaining until the oldest slot in the window expires
         * and a new hit would be allowed. Returns 0 if not currently limited.
         */
        public static function retryAfter(string $key, int $maxHits, int $windowSec, string $tmpDir = ''): int {
            $file = self::filePath($key, $tmpDir);

            if (!file_exists($file)) {
                return 0;
            }

            $content = @file_get_contents($file);

            if ($content === false || $content === '') {
                return 0;
            }

            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                return 0;
            }

            $now = time();
            $cutoff = $now - $windowSec;
            $active = self::filterActiveTimestamps($decoded, $cutoff);

            if (count($active) < $maxHits) {
                return 0;
            }

            return max(0, min($active) + $windowSec - $now);
        }

        // -----------------------------------------------------------------------

        private static function filePath(string $key, string $tmpDir): string {
            if ($tmpDir === '') {
                $tmpDir = sys_get_temp_dir();
            }

            return rtrim($tmpDir, '/\\') . DIRECTORY_SEPARATOR . 'rl_' . md5($key) . '.json';
        }

        /** @return int[] */
        private static function readTimestamps(mixed $fp, int $now, int $windowSec): array {
            rewind($fp);
            $content = stream_get_contents($fp);

            if ($content === false || $content === '') {
                return [];
            }

            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                return [];
            }

            $cutoff = $now - $windowSec;

            return self::filterActiveTimestamps($decoded, $cutoff);
        }

        /**
         * Coerce a decoded state array into valid unix timestamps before
         * ever handing entries to a strictly-typed `int $t` comparison —
         * a corrupted/hand-edited/foreign-format state file must degrade
         * to "no prior state" (entry dropped) rather than throw a TypeError
         * under strict_types=1 and 500 every subsequent request for that key.
         *
         * @param array $decoded
         * @param int   $cutoff
         * @return int[]
         */
        private static function filterActiveTimestamps(array $decoded, int $cutoff): array {
            $active = [];

            foreach ($decoded as $entry) {
                if (!is_int($entry) && !(is_string($entry) && ctype_digit($entry))) {
                    continue;
                }

                $t = (int)$entry;

                if ($t > $cutoff) {
                    $active[] = $t;
                }
            }

            return $active;
        }

        /**
         * Delete a rate-limit state file whose entire window has already
         * elapsed, so a caller hitting the same (or a never-repeated) key
         * doesn't leave a dead inode behind indefinitely. Best-effort: any
         * failure here just leaves the stale file for the next attempt.
         */
        private static function cleanupIfExpired(string $file, int $now, int $windowSec): void {
            if (is_link($file) || !is_file($file)) {
                return;
            }

            $mtime = @filemtime($file);

            if ($mtime === false || ($now - $mtime) <= $windowSec) {
                return;
            }

            @unlink($file);
        }

        /**
         * Confirms the handle we actually opened still points at a plain
         * file at the expected path — guards the TOCTOU gap between the
         * is_link() pre-check and fopen().
         */
        private static function openedPathIsSafe(mixed $fp, string $expectedPath): bool {
            if (is_link($expectedPath)) {
                return false;
            }

            $stat = fstat($fp);

            return $stat !== false && ($stat['mode'] & 0o170000) === 0o100000; // S_IFREG
        }

        private static function logFailure(string $message): void {
            $logger = Logger::silentGet(Logger::SYSTEM_LOGGER);

            if ($logger !== null) {
                $logger->append('rate_limit', $message);

                return;
            }

            error_log('[RateLimit] ' . $message);
        }
    }
}
