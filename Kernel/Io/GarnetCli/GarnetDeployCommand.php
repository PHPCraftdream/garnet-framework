<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use RuntimeException;
use Throwable;

class GarnetDeployCommand {
    /**
     * Deploy under maintenance.
     *
     * Policy (important): maintenance is lifted ONLY after every step below
     * succeeds. If the DB backup or the migration fails the site is left in
     * maintenance ON ON PURPOSE — so the operator can inspect, restore the
     * pre-migration backup, and re-run, instead of the site coming back up on
     * a half-applied schema. The previous version lifted maintenance in a
     * `finally`, which defeated that.
     *
     * Order: Maintenance ON → DB backup → migrations → caches → Maintenance OFF.
     *
     * Flags:
     *   --skip-migrate   don't run migrations (also skips the pre-migration backup)
     *   --skip-backup    run migrations WITHOUT the pre-migration backup (discouraged)
     */
    public static function run(array $args): void {
        if (in_array('--help', $args, true) || in_array('-h', $args, true) || ($args[0] ?? '') === 'help') {
            self::help();

            return;
        }

        $appName = GarnetEnv::requireAppName();
        $skipMigrate = in_array('--skip-migrate', $args, true);
        $skipBackup = in_array('--skip-backup', $args, true);

        echo "=== Garnet Deploy: {$appName} ===" . PHP_EOL . PHP_EOL;

        // Step 1: Maintenance ON — stays on until everything below succeeds.
        static::step('1/5', 'Maintenance mode ON');
        GarnetMaintenanceCommand::run(['on']);
        echo PHP_EOL;

        try {
            if (!$skipMigrate) {
                // Step 2: DB backup BEFORE migrations — a failed migration must
                // be recoverable. Skipped only on explicit --skip-backup.
                if (!$skipBackup) {
                    static::step('2/5', 'Database backup (pre-migration)');
                    GarnetDbBackupCommand::createBackup('pre-deploy');
                } else {
                    static::step('2/5', 'Database backup SKIPPED (--skip-backup)');
                }
                echo PHP_EOL;

                // Step 3: Migrations. A throw here propagates to the catch below
                // and DELIBERATELY leaves the site in maintenance.
                static::step('3/5', 'Running migrations');
                static::runMigrations($appName);
                echo PHP_EOL;
            } else {
                static::step('2/5', 'Database backup SKIPPED (migrations skipped)');
                echo PHP_EOL;
                static::step('3/5', 'Migrations SKIPPED (--skip-migrate)');
                echo PHP_EOL;
            }

            // Step 4: Clear caches (delegate to cache command)
            static::step('4/5', 'Clearing caches');
            GarnetCacheCommand::run('cache', []);
            echo PHP_EOL;
        } catch (Throwable $e) {
            // Leave maintenance ON on purpose — surface what happened and how
            // to recover, then re-throw so the deploy exits non-zero.
            echo PHP_EOL . "=== Deploy FAILED: {$e->getMessage()}" . PHP_EOL;
            echo '  The site is STILL in maintenance (intentionally).' . PHP_EOL;
            echo '  Investigate, restore the pre-migration backup if needed:' . PHP_EOL;
            echo '    php garnet db:restore <WorkDir/Backups/...pre-deploy.sql.gz>' . PHP_EOL;
            echo '  then re-run deploy, or lift manually once fixed:' . PHP_EOL;
            echo '    php garnet maintenance off' . PHP_EOL;

            throw $e;
        }

        // Step 5: Maintenance OFF — reached ONLY when every step above succeeded.
        static::step('5/5', 'Maintenance mode OFF');
        GarnetMaintenanceCommand::run(['off']);
        echo PHP_EOL;

        echo '=== Deploy complete ===' . PHP_EOL;
    }

    /**
     * Run the app's migrations as a SUBPROCESS via its run_cmd.php entrypoint.
     *
     * In-process `require run_cmd.php` can't work: run_cmd re-bootstraps the
     * app and blows up on the already-initialised process ("Cache already
     * defined: ENV_APP"). A child `php run_cmd.php migration` gets a clean
     * bootstrap, inherits this process's environment (so GARNET_WORKDIR_DIR &
     * friends carry over on deployed boxes) and reports a real exit code —
     * non-zero throws, which leaves the site in maintenance for the operator.
     */
    private static function runMigrations(string $appName): void {
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo '  No run_cmd.php found, skipping migrations.' . PHP_EOL;

            return;
        }

        $php = PHP_BINARY;
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($runCmd) . ' migration';

        $exitCode = 0;
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("Migration subprocess failed (exit {$exitCode}).");
        }
    }

    private static function step(string $num, string $label): void {
        echo "[{$num}] {$label}" . PHP_EOL;
    }

    private static function help(): void {
        echo <<<HELP

  php garnet deploy [flags]

  WHAT IT DOES
  ────────────────────────────────────────────────────────────────────────
  Runs the current app's pending DB migrations under a maintenance-mode
  wrapper, safe by design: Maintenance ON → DB backup → migrations →
  cache clear → Maintenance OFF. If the backup or a migration fails,
  maintenance is left ON on purpose so a half-migrated site never goes
  live — investigate or restore the backup, then re-run.

  This command does NOT ship code — it operates on whatever is already
  on disk in the CURRENT working directory's app context (its own
  WorkDir/Config/db.ini). On a production host that means running it
  from inside the runtime dir over SSH, after the code itself has
  already been shipped (php garnet deploy:full does both in one
  call; php garnet deploy:diff --apply ships code only, then this
  command applies any pending migration).

  FLAGS
  ────────────────────────────────────────────────────────────────────────
    --skip-migrate   Don't run migrations (also skips the pre-migration
                      backup, since there's nothing to back up for).
    --skip-backup    Run migrations WITHOUT the pre-migration backup.
                      Discouraged — migrations are forward-only, a
                      backup is the only rollback path.

  --help / -h / help     this message
HELP;
        echo PHP_EOL;
    }
}
