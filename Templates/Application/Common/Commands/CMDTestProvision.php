<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common\Commands {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;
    use PHPCraftdream\Application\Common\Services\TestScopeDbService;
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\CMDMigration;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    /**
     * `php garnet test:provision` — server-side setup for `php garnet
     * test:remote` (see GarnetTestRemoteCommand in the framework), the
     * sanctioned way to run Playwright against a REAL remote/production box:
     * it plants the `.allow_tests` token gate and migrates the isolated
     * `test_worker_0` DB-prefix scope so the run never touches live tables.
     *
     * This scaffold only does the generic part (token + schema). Add your
     * own seed step (demo data, role/login accounts the Tests/ specs expect)
     * where marked below — see the Garnet docs / an existing Garnet app's
     * test:provision for a worked example (business-entity seeding, role
     * accounts with flags, starting balances, etc).
     */
    class CMDTestProvision implements ICommand {
        public static function description(): string {
            return 'Provision the isolated test_worker_0 scope on this host (prod UI-test pipeline)';
        }

        public static function help(array $args, Context $context, Stdio $stdio): void {
            $stdio->outln('Usage: GARNET_TEST_TOKEN=<secret> php garnet test:provision');
            $stdio->outln('');
            $stdio->outln('  Plants .allow_tests, then migrates the test_worker_0 scope.');
            $stdio->outln('  The token may also be passed as --token=<secret>.');
            $stdio->outln('  Tear down afterwards with: php garnet test:teardown');
        }

        public static function run(array $args, Context $context, Stdio $stdio): void {
            $token = self::resolveToken($args);

            if ($token === null) {
                $stdio->errln('ERROR: missing/invalid token. Set GARNET_TEST_TOKEN or pass --token=<secret>.');
                $stdio->errln('       Token must be 16-128 chars of [A-Za-z0-9_-].');

                exit(1);
            }

            $tokenFile = TestScope::tokenFilePath();

            if ($tokenFile === null) {
                $stdio->errln('ERROR: cannot resolve the app directory for the token file.');

                exit(1);
            }

            // 1. Plant the token.
            if (@file_put_contents($tokenFile, $token) === false) {
                $stdio->errln("ERROR: failed to write token file: {$tokenFile}");

                exit(1);
            }
            $stdio->outln("Token planted: {$tokenFile}");

            // 2. Pin the prefix. Self-contained — we don't depend on run_cmd's
            //    env-gated override (the token file didn't exist yet at boot).
            $prefix = TestScope::WORKER_PREFIX;
            IniConfig::db()->setRuntimeOverride('prefix', $prefix);
            $stdio->outln("DB prefix pinned: {$prefix}");

            // 3. Clean slate.
            $dropped = TestScopeDbService::dropScopeTables($prefix);
            $stdio->outln("Dropped {$dropped} leftover {$prefix}_* table(s)");

            // 4. Migrate.
            $stdio->outln('Migrating schema...');
            CMDMigration::run(['init'], $context, $stdio);
            CMDMigration::run(['migrate'], $context, $stdio);

            // 5. TODO: seed demo data + role/login accounts your Tests/
            //    specs need (mirrors this same block in test:teardown, which
            //    needs no matching step — dropScopeTables() above already
            //    clears everything on the next provision).
            // $stdio->outln('Seeding sample data...');
            // YourSeedService::seed();

            $stdio->outln('');
            $stdio->outln("Provision complete — scope `{$prefix}` is ready.");
        }

        /**
         * @param array<int, string> $args
         */
        private static function resolveToken(array $args): ?string {
            $token = (string)(getenv(TestScope::ENV_TOKEN) ?: '');

            foreach ($args as $arg) {
                if (str_starts_with($arg, '--token=')) {
                    $token = substr($arg, 8);

                    break;
                }
            }
            $token = trim($token);

            if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $token) !== 1) {
                return null;
            }

            return $token;
        }
    }
}
