<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Env {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use Throwable;

    /**
     * Token-file gate that authorizes a single, fully-isolated test scope
     * to run against ANY environment — including production — without the
     * dev-dir requirement that {@see Env::isDevDir()} enforces.
     *
     * The gate is OFF by default and physically impossible to trip unless
     * an operator has placed a secret token on the server:
     *
     *   1. A file `.allow_tests` exists in the active app directory and
     *      contains a non-empty secret token.
     *   2. The request proves knowledge of that token:
     *        - HTTP : header `run-test-garnet-team: <token>`
     *                 (arrives as $_SERVER['HTTP_RUN_TEST_GARNET_TEAM']).
     *        - CLI  : env var GARNET_TEST_TOKEN=<token> (used by the
     *                 server-side `garnet test:provision/teardown` run).
     *
     * Token comparison is constant-time ({@see hash_equals}).
     *
     * When active, callers swap to a SEPARATE table prefix
     * ({@see self::WORKER_PREFIX}) and a SEPARATE upload directory
     * ({@see self::UPLOAD_SUBDIR}), so the test run shares the database
     * server and the app code but never the live tables or files. Removing
     * the `.allow_tests` file instantly closes the gate again.
     *
     * Single-worker by design: production / shared hosting runs must not
     * fan out into N parallel schemas, so there is exactly one test scope.
     */
    class TestScope {
        /** HTTP header that carries the secret token. */
        public const HEADER_KEY = 'HTTP_RUN_TEST_GARNET_TEAM';

        /** CLI env var that carries the secret token. */
        public const ENV_TOKEN = 'GARNET_TEST_TOKEN';

        /** Secret-token file, looked up in the active app directory. */
        public const TOKEN_FILE = '.allow_tests';

        /** Sole test table prefix (single worker). */
        public const WORKER_PREFIX = 'test_worker_0';

        /** Upload directory name used while the gate is active. */
        public const UPLOAD_SUBDIR = 'UploadTest';

        /** Upload directory name used in normal operation. */
        public const UPLOAD_SUBDIR_LIVE = 'Upload';

        /**
         * Whether THIS request/process is an authorized test run.
         *
         * Recomputed on every call (no static cache): IniConfig and the
         * app instance are long-lived singletons in php-fpm / `php -S`,
         * and the auth inputs ($_SERVER header, env var, on-disk token)
         * change between requests. Caching would leak one request's
         * verdict into the next. The work is a single small file read plus
         * a constant-time compare — cheap enough to repeat the 3-4 times
         * per request it is consulted.
         */
        public static function isActive(): bool {
            $token = self::fileToken();

            if ($token === null) {
                return false; // no .allow_tests on disk → gate is closed
            }

            $header = (string)($_SERVER[self::HEADER_KEY] ?? '');

            if ($header !== '' && hash_equals($token, $header)) {
                return true;
            }

            if (Env::isCmd()) {
                $envToken = getenv(self::ENV_TOKEN);

                if (is_string($envToken) && $envToken !== '' && hash_equals($token, $envToken)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Upload sub-directory name for the current scope: the isolated
         * test folder when the gate is active, the live folder otherwise.
         */
        public static function uploadSubDir(): string {
            return self::isActive() ? self::UPLOAD_SUBDIR : self::UPLOAD_SUBDIR_LIVE;
        }

        /**
         * Absolute path of the token file in the active app directory, or
         * null if the app cannot be resolved. Public so tooling (the
         * provision/teardown commands) can report/inspect it.
         */
        public static function tokenFilePath(): ?string {
            try {
                $appName = GarnetEnv::readAppName();

                if ($appName === '') {
                    return null;
                }
                $appDir = GarnetEnv::getAppDir($appName);
            } catch (Throwable) {
                return null;
            }

            if ($appDir === '') {
                return null;
            }

            return rtrim($appDir, '/\\') . DIRECTORY_SEPARATOR . self::TOKEN_FILE;
        }

        /**
         * Read and trim the on-disk secret token, or null when the file is
         * absent / empty / unreadable.
         */
        private static function fileToken(): ?string {
            $file = self::tokenFilePath();

            if ($file === null || !is_file($file)) {
                return null;
            }
            $raw = @file_get_contents($file);

            if (!is_string($raw)) {
                return null;
            }
            $raw = trim($raw);

            return $raw !== '' ? $raw : null;
        }
    }
}
