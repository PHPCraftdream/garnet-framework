<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cron {
    use Aura\Cli\Stdio;
    use Throwable;

    abstract class FwCronService {
        private static array $tasks = [];

        public static function registerTask(string $name, callable $callback, string $description = ''): void {
            static::$tasks[$name] = [
                'callback' => $callback,
                'description' => $description,
            ];
        }

        public static function getTasks(): array {
            return static::$tasks;
        }

        abstract public static function registerTasks(): void;

        public static function runAll(Stdio $stdio): int {
            static::registerTasks();
            $tasks = static::getTasks();
            $total = count($tasks);
            $success = 0;

            $stdio->outln("Running {$total} cron task(s)...");

            foreach ($tasks as $name => $task) {
                $stdio->out("  [{$name}] ... ");

                try {
                    $result = ($task['callback'])($stdio);
                    $stdio->outln('OK' . ($result !== null ? " ({$result})" : ''));
                    $success++;
                } catch (Throwable $e) {
                    $stdio->outln('ERROR: ' . $e->getMessage());
                }
            }

            $stdio->outln("Done: {$success}/{$total} tasks completed.");

            return $total - $success;
        }

        public static function runTask(string $taskName, Stdio $stdio): int {
            static::registerTasks();
            $tasks = static::getTasks();

            if (!isset($tasks[$taskName])) {
                $stdio->outln("Unknown task: {$taskName}");
                $stdio->outln('Available tasks: ' . implode(', ', array_keys($tasks)));

                return 1;
            }

            $stdio->out("Running task [{$taskName}] ... ");

            try {
                $result = ($tasks[$taskName]['callback'])($stdio);
                $stdio->outln('OK' . ($result !== null ? " ({$result})" : ''));

                return 0;
            } catch (Throwable $e) {
                $stdio->outln('ERROR: ' . $e->getMessage());

                return 1;
            }
        }

        public static function listTasks(Stdio $stdio): void {
            static::registerTasks();
            $tasks = static::getTasks();

            if (empty($tasks)) {
                $stdio->outln('No cron tasks registered.');

                return;
            }

            $stdio->outln('Registered cron tasks:');

            foreach ($tasks as $name => $task) {
                $desc = $task['description'] ? " — {$task['description']}" : '';
                $stdio->outln("  {$name}{$desc}");
            }
        }
    }
}
