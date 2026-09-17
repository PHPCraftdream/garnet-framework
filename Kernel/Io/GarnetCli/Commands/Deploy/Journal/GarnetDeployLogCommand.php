<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\Journal;

use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;

/**
 * Read the deploy journal: `php garnet deploy:log [--n=N] [--run=ID]`.
 *
 * The journal is only worth writing if it is cheap to read. Without this the
 * answer to "what did the last deploy actually do" means knowing the file
 * layout and opening a dated log by hand — which is exactly the friction that
 * stops anyone looking during an incident.
 */
final class GarnetDeployLogCommand {
    public static function run(array $args): void {
        $limit = 10;
        $runId = '';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--n=')) {
                $limit = max(0, (int)substr($arg, 4));

                continue;
            }

            if (str_starts_with($arg, '--run=')) {
                $runId = substr($arg, 6);

                continue;
            }

            if ($arg === 'help' || $arg === '--help') {
                self::printHelp();

                return;
            }
        }

        $dir = self::journalDir();
        $runs = DeployJournal::readRuns($dir);

        if ($runs === []) {
            echo "No deploy runs recorded yet ({$dir}).\n";

            return;
        }

        if ($runId !== '') {
            self::printOne($runs, $runId);

            return;
        }
        self::printList($limit > 0 ? array_slice($runs, -$limit) : $runs, $dir);
    }

    private static function printList(array $runs, string $dir): void {
        echo "=== deploy runs ({$dir})\n\n";

        foreach ($runs as $run) {
            $verdict = $run['ended']
                ? ($run['verdict'] === 'ok' ? 'ok' : "{$run['verdict']}")
                : 'INTERRUPTED';
            $landed = DeployJournal::landedCount($run);
            echo "  {$run['started']}  {$run['id']}  " . str_pad($run['command'], 12)
                . "  {$verdict}" . ($landed > 0 ? "  ({$landed} file(s) landed)" : '') . "\n";
        }
        echo "\nphp garnet deploy:log --run=<id> for the full record of one run\n";
    }

    private static function printOne(array $runs, string $runId): void {
        foreach ($runs as $run) {
            if ($run['id'] !== $runId) {
                continue;
            }
            echo "=== run {$run['id']} | {$run['command']} | started {$run['started']}\n";

            foreach ($run['lines'] as $line) {
                echo $line . "\n";
            }
            echo $run['ended']
                ? "=== ended: {$run['verdict']}\n"
                : "=== no end record — this run was interrupted\n";

            return;
        }
        echo "No run with id \"{$runId}\".\n";
    }

    private static function journalDir(): string {
        $appName = GarnetEnv::readAppName();

        return GarnetEnv::getAppDir($appName) . DS . 'WorkDir' . DS . 'LogJournal' . DS . 'Deploy';
    }

    private static function printHelp(): void {
        echo <<<TXT

          php garnet deploy:log [flags]

          Past deploy runs, newest last: when, which command, what it shipped,
          how it ended. A run shown as INTERRUPTED wrote no end record — it was
          killed or timed out, and the host may be holding a partial deploy.

            --n=N        show the last N runs (default 10, 0 = all)
            --run=ID     print one run in full: phases with durations,
                         files that landed, errors

          Journal location: <app>/WorkDir/LogJournal/Deploy/<date>.log
          Written by every deploy unless it was passed --no-log.


        TXT;
    }
}
