<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

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
        echo "\033[1m=== deploy runs\033[0m \033[90m({$dir})\033[0m\n\n";

        foreach ($runs as $run) {
            $verdict = $run['ended']
                ? ($run['verdict'] === 'ok' ? "\033[32mok\033[0m" : "\033[31m{$run['verdict']}\033[0m")
                : "\033[33mINTERRUPTED\033[0m";
            $landed = DeployJournal::landedCount($run);
            echo "  {$run['started']}  {$run['id']}  " . str_pad($run['command'], 12)
                . "  {$verdict}" . ($landed > 0 ? "  ({$landed} file(s) landed)" : '') . "\n";
        }
        echo "\n\033[90mphp garnet deploy:log --run=<id> for the full record of one run\033[0m\n";
    }

    private static function printOne(array $runs, string $runId): void {
        foreach ($runs as $run) {
            if ($run['id'] !== $runId) {
                continue;
            }
            echo "\033[1m=== run {$run['id']}\033[0m | {$run['command']} | started {$run['started']}\n";

            foreach ($run['lines'] as $line) {
                echo $line . "\n";
            }
            echo $run['ended']
                ? "=== ended: {$run['verdict']}\n"
                : "\033[33m=== no end record — this run was interrupted\033[0m\n";

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

          \033[1mphp garnet deploy:log [flags]\033[0m

          Past deploy runs, newest last: when, which command, what it shipped,
          how it ended. A run shown as INTERRUPTED wrote no end record — it was
          killed or timed out, and the host may be holding a partial deploy.

            \033[36m--n=N\033[0m        show the last N runs (default 10, 0 = all)
            \033[36m--run=ID\033[0m     print one run in full: phases with durations,
                         files that landed, errors

          Journal location: \033[2m<app>/WorkDir/LogJournal/Deploy/<date>.log\033[0m
          Written by every deploy unless it was passed \033[36m--no-log\033[0m.


        TXT;
    }
}
