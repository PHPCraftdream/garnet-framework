<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use Throwable;

/**
 * Run a single SQL query against the active app's database.
 *
 *   php garnet sql "SELECT * FROM db_ir_accounts LIMIT 5"
 *   echo "SELECT 1" | php garnet sql
 *
 * Use this instead of inlining mysql client credentials in bash.
 * Minimal v1: text-only output, single statement, no formatting flags.
 */
final class GarnetSqlCommand {
    public static function run(array $args): void {
        $sub = $args[0] ?? '';

        if ($sub === 'help' || in_array('--help', $args, true) || in_array('-h', $args, true)) {
            self::help();

            exit(0);
        }

        // Bootstrap app so DbPool is configured
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            fwrite(STDERR, "\033[31mError:\033[0m app has no run_cmd.php at {$runCmd}\n");

            exit(1);
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();

        // `--json` switches output to machine-readable JSON. The Playwright
        // prod harness drives this over SSH as its DB bridge: a SELECT comes
        // back as a JSON array of row objects, a DML statement as
        // {"affected": N}. Errors go to stderr as {"error": "..."} + exit 1.
        $json = in_array('--json', $args, true);

        // Resolve SQL: first non-flag arg, or stdin if not a TTY
        $sql = '';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '-')) {
                continue;
            }
            $sql = $arg;

            break;
        }

        if ($sql === '' && !stream_isatty(STDIN)) {
            $sql = trim((string)stream_get_contents(STDIN));
        }

        if ($sql === '') {
            self::help();

            exit(1);
        }

        try {
            $link = DbPool::get()->newLink();
            $result = $link->query($sql);
        } catch (Throwable $e) {
            if ($json) {
                echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

                exit(1);
            }
            fwrite(STDERR, "\033[31m✖ MySQL error:\033[0m " . $e->getMessage() . "\n");

            exit(1);
        }

        if ($json) {
            if (is_array($result)) {
                $payload = ['rows' => $result];
            } else {
                // For an INSERT, $link->query() returns the auto-increment id
                // (int) from the SAME connection, so the bridge gets a real
                // insertId without a second round-trip. UPDATE/DELETE return a
                // non-int (bool) → insertId 0.
                $payload = [
                    'affected' => $link->getLastAffectedRows(),
                    'insertId' => is_int($result) ? $result : 0,
                ];
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

            exit(0);
        }

        if (is_array($result)) {
            // SELECT: print TSV with header
            if (empty($result)) {
                echo "(0 rows)\n";

                exit(0);
            }
            $cols = array_keys($result[0]);
            echo implode("\t", $cols) . "\n";

            foreach ($result as $row) {
                echo implode("\t", array_map(static fn ($v) => $v === null ? 'NULL' : (string)$v, $row)) . "\n";
            }
            echo '-- ' . count($result) . " row(s)\n";
        } else {
            $affected = $link->getLastAffectedRows();
            echo "Query OK, {$affected} row(s) affected\n";
        }

        exit(0);
    }

    private static function help(): void {
        echo "Usage: php garnet sql \"<SELECT ... | INSERT ... | etc>\"\n";
        echo "       echo \"<sql>\" | php garnet sql\n";
        echo "  Executes one SQL statement against the active app's DB (db.ini).\n";
        echo "  Output: TSV with header for SELECT, 'Query OK, N affected' for DML.\n";
    }
}
