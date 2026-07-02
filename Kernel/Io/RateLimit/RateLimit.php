<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\RateLimit {
    /**
     * Sliding-window rate limiter backed by temp files.
     * Thread-safe via exclusive file locking (flock).
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

            $fp = @fopen($file, 'c+');

            if ($fp === false) {
                return true; // fail-open: storage unavailable
            }

            if (!flock($fp, LOCK_EX)) {
                fclose($fp);

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
            $active = array_values(array_filter($decoded, static fn (int $t): bool => $t > $cutoff));

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

            return array_values(array_filter($decoded, static fn (int $t): bool => $t > $cutoff));
        }
    }
}
