<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Middlewares {
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Psr\Http\Message\ResponseInterface;
    use Throwable;

    /**
     * Per-request DB-prefix override for parallel test workers.
     *
     * When Playwright (or any test runner) sends `X-Test-Worker: N`
     * with a request, the matching `db_ir_*` tables get rewritten to
     * `test_worker_N_ir_*` for the duration of that request — every
     * DbTable instance reads the prefix from `IniConfig::db()` at
     * resolution time, so the swap is automatic and total.
     *
     * Two authorization paths, each with its own safety gates:
     *   A. Dev context — app.ini env=dev AND a dev directory. Honors the
     *      full per-worker fan-out (`template`, `0`..MAX_WORKER) used by
     *      the local Playwright isolation pipeline.
     *   B. Token context — {@see TestScope::isActive()} (a `.allow_tests`
     *      token file on disk AND a matching `run-test-garnet-team`
     *      header). This is the ONLY way the prefix ever flips outside a
     *      dev directory — i.e. on production. It pins to the single
     *      `test_worker_0` scope; no per-worker fan-out, no `template`.
     *
     * In neither path can a leaked header alone flip the prefix: dev
     * requires a dev directory, token requires the secret file on the
     * server. Anything else clears the override (safe default).
     *
     * Whitelisted format — only `\d+` worker indices, capped at
     * MAX_WORKER; the token path additionally pins to index 0.
     *
     * Lifecycle: IniConfig is a long-lived singleton in single-process
     * servers (`php -S`, php-fpm worker), so the override map carries
     * over between requests inside one process. Every call to process()
     * either SETS a fresh override or CLEARS it — there is no path that
     * leaves a stale value from a prior request.
     */
    class WorkerScopeMiddleware {
        public const HEADER_KEY = 'HTTP_X_TEST_WORKER';

        public const PREFIX_PARAM = 'prefix';

        public const PREFIX_TEMPLATE = 'test_worker_%d';

        public const MAX_WORKER = 64;

        public static function process(IGlobalReqParams $globals, IRouterUriParams $params): ?ResponseInterface {
            $iniDb = IniConfig::db();

            // Always clear first — guarantees no leftover override from
            // a previous request bleeds into one that has no header.
            $iniDb->clearRuntimeOverride(self::PREFIX_PARAM);

            $devContext = self::isDevContext($globals);
            $tokenContext = TestScope::isActive();

            if (!$devContext && !$tokenContext) {
                return null;
            }

            $server = $globals->readServerAll();
            $raw = (string)($server[self::HEADER_KEY] ?? '');

            // Token (production) context: a single isolated scope, no
            // per-worker fan-out and no `template`. The X-Test-Worker
            // header is honored only as a numeric index in range, and
            // defaults to 0 — the prefix can NEVER become anything other
            // than `test_worker_<n>` here.
            if (!$devContext) {
                $n = ($raw !== '' && ctype_digit($raw) && (int)$raw >= 0 && (int)$raw <= self::MAX_WORKER)
                    ? (int)$raw
                    : 0;
                $iniDb->setRuntimeOverride(self::PREFIX_PARAM, sprintf(self::PREFIX_TEMPLATE, $n));

                return null;
            }

            // Dev context: full per-worker fan-out for the local pipeline.
            if ($raw === '') {
                return null;
            }

            // Special "template" scope used by globalSetup to populate the
            // template tables once before cloning into per-worker scopes.
            if ($raw === 'template') {
                $iniDb->setRuntimeOverride(self::PREFIX_PARAM, 'test_worker_template');

                return null;
            }

            if (!ctype_digit($raw)) {
                return null;
            }

            $n = (int)$raw;

            if ($n < 0 || $n > self::MAX_WORKER) {
                return null;
            }

            $iniDb->setRuntimeOverride(
                self::PREFIX_PARAM,
                sprintf(self::PREFIX_TEMPLATE, $n)
            );

            return null;
        }

        /**
         * Two-fold dev gate: app.ini explicitly says env=dev AND the
         * runtime is sitting in a dev directory. Either alone is not
         * enough — protects against an accidental misconfigured prod
         * server inheriting a dev app.ini.
         */
        private static function isDevContext(IGlobalReqParams $globals): bool {
            try {
                if (!$globals->isDev()) {
                    return false;
                }

                if (!Env::isDevDir()) {
                    return false;
                }

                return true;
            } catch (Throwable) {
                return false;
            }
        }
    }
}
