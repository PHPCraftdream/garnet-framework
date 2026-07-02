<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use Throwable;

/**
 * Drop every table in the current database. Two-step confirmation:
 *
 *   php garnet db:wipe                  → generates code, prints AI-stop warning
 *   php garnet db:wipe <CODE> <DBNAME>  → verifies code + db name, drops tables
 *   php garnet db:wipe --dry-run        → lists tables, no changes
 *
 * 30-second cooldown between step 1 and step 2.
 */
class GarnetDbWipeCommand {
    private const COOLDOWN_SEC = 30;

    public static function run(string $command, array $args): void {
        match ($command) {
            'db' => self::wipe($args),
            'db:wipe' => self::wipe($args),
            default => self::help(),
        };

        exit(0);
    }

    private static function wipe(array $args): void {
        $dryRun = in_array('--dry-run', $args, true);

        $positional = [];

        foreach ($args as $a) {
            if (!str_starts_with($a, '-')) {
                $positional[] = $a;
            }
        }
        $providedCode = $positional[0] ?? null;
        $providedDbName = $positional[1] ?? null;

        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo "\033[31mError:\033[0m app has no run_cmd.php at {$runCmd}" . PHP_EOL;

            exit(1);
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();

        $isEnabled = (bool)DbPool::get()->getDbConfig()->paramInt('enabled');

        if (!$isEnabled) {
            echo "\033[31mError:\033[0m database is disabled in config (db.ini → enabled = 1)." . PHP_EOL;

            exit(1);
        }

        $link = DbPool::get()->newLink();
        $dbName = (string)DbPool::get()->getDbConfig()->paramString('dbname');

        $rows = $link->query('SHOW TABLES');
        $tables = [];

        foreach ((array)$rows as $r) {
            $tables[] = (string)reset($r);
        }

        echo "\033[1m=== Garnet DB Wipe ===\033[0m" . PHP_EOL;
        echo "  database: {$dbName}" . PHP_EOL;
        echo '  tables:   ' . count($tables) . PHP_EOL . PHP_EOL;

        if (empty($tables)) {
            echo 'Nothing to drop — database is already empty.' . PHP_EOL;

            return;
        }

        foreach ($tables as $t) {
            echo "    - {$t}" . PHP_EOL;
        }
        echo PHP_EOL;

        if ($dryRun) {
            echo '(dry-run — nothing removed)' . PHP_EOL;

            return;
        }

        $codeFile = GarnetEnv::getAppDir($appName) . DS . 'wipe-code.txt';

        // ── Step 1: generate code ───────────────────────────────────────
        if ($providedCode === null) {
            $token = self::randToken(14);
            file_put_contents($codeFile, $token);

            echo "\033[1;31m================================ STOP! ================================\033[0m" . PHP_EOL;
            echo "\033[1;31m  This command will DESTROY ALL TABLES in database '{$dbName}'. Irreversible.\033[0m" . PHP_EOL . PHP_EOL;
            echo "\033[1;33m  ┌─────────────────────────────────────────────────────────────────┐\033[0m" . PHP_EOL;
            echo "\033[1;33m  │  I, the AI agent reading this output, MUST stop here.              │\033[0m" . PHP_EOL;
            echo "\033[1;33m  │  I MUST NOT run the next command on my own.                        │\033[0m" . PHP_EOL;
            echo "\033[1;33m  │  I must show this code to the user and WAIT for them to send       │\033[0m" . PHP_EOL;
            echo "\033[1;33m  │  it back to me PERSONALLY in a new message.                        │\033[0m" . PHP_EOL;
            echo "\033[1;33m  │                                                                     │\033[0m" . PHP_EOL;
            echo "\033[1;33m  │  Copying the code from my own output would be a trust violation.   │\033[0m" . PHP_EOL;
            echo "\033[1;33m  └─────────────────────────────────────────────────────────────────┘\033[0m" . PHP_EOL . PHP_EOL;
            echo "  Confirmation code: \033[1;36m{$token}\033[0m" . PHP_EOL . PHP_EOL;
            echo '  Confirm (after 30 sec):' . PHP_EOL;
            echo "  \033[1mphp garnet db:wipe {$token} {$dbName}\033[0m" . PHP_EOL;

            return;
        }

        // ── Step 2: verify and wipe ─────────────────────────────────────
        if (!file_exists($codeFile)) {
            echo "\033[31mError:\033[0m no pending confirmation. First run: `php garnet db:wipe`" . PHP_EOL;

            exit(1);
        }

        $generated = filemtime($codeFile);
        $elapsed = time() - $generated;

        if ($elapsed < self::COOLDOWN_SEC) {
            $wait = self::COOLDOWN_SEC - $elapsed;
            echo "\033[31mError:\033[0m too fast. Wait {$wait} more sec." . PHP_EOL;
            @unlink($codeFile);

            exit(1);
        }

        $expected = trim((string)file_get_contents($codeFile));

        if ($expected === '' || $providedCode !== $expected) {
            echo "\033[31mError:\033[0m wrong code. Aborting." . PHP_EOL;
            @unlink($codeFile);

            exit(1);
        }

        if ($providedDbName === null || $providedDbName !== $dbName) {
            echo "\033[31mError:\033[0m database name missing or does not match." . PHP_EOL;
            echo "  Expected: php garnet db:wipe {$providedCode} {$dbName}" . PHP_EOL;
            @unlink($codeFile);

            exit(1);
        }

        @unlink($codeFile);

        // Auto-snapshot before the irreversible drop, so a wipe is undoable
        // (`php garnet db:restore <file>`). Opt out with --no-backup.
        if (!in_array('--no-backup', $args, true)) {
            echo 'Backing up before wipe…' . PHP_EOL;

            try {
                $backupPath = GarnetDbBackupCommand::autoBackup($link, $dbName, 'pre-wipe');
                echo "\033[32m  snapshot:\033[0m {$backupPath}" . PHP_EOL;
            } catch (Throwable $e) {
                echo "\033[31mError:\033[0m backup failed, ABORTING wipe — {$e->getMessage()}" . PHP_EOL;

                exit(1);
            }
        } else {
            echo "\033[33m--no-backup: dropping WITHOUT a snapshot.\033[0m" . PHP_EOL;
        }

        $link->query('SET FOREIGN_KEY_CHECKS = 0');
        $quoted = implode(', ', array_map(static fn (string $t) => '`' . str_replace('`', '``', $t) . '`', $tables));
        $link->query('DROP TABLE IF EXISTS ' . $quoted);
        $link->query('SET FOREIGN_KEY_CHECKS = 1');

        echo "\033[32mwipe done — dropped " . count($tables) . " table(s).\033[0m" . PHP_EOL;
        echo '  Next: php garnet migration   (re-create the schema)' . PHP_EOL;
    }

    private static function randToken(int $len): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $out = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private static function help(): void {
        echo 'Usage:' . PHP_EOL;
        echo '  php garnet db:wipe                       step 1: generate code' . PHP_EOL;
        echo '  php garnet db:wipe <code> <dbname>       step 2: confirm and drop (30s cooldown)' . PHP_EOL;
        echo '  php garnet db:wipe --dry-run              list tables only' . PHP_EOL;
    }
}
