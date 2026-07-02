<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cron {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;

    class CMDCron implements ICommand {
        protected static string $cronServiceClass = FwCronService::class;

        public static function setCronServiceClass(string $class): void {
            static::$cronServiceClass = $class;
        }

        public static function description(): string {
            return 'Run cron tasks (email queue, etc.)';
        }

        public static function help(array $args, Context $context, Stdio $stdio): void {
            $stdio->outln('Usage: php garnet cron [task-name]');
            $stdio->outln('');
            $stdio->outln('Commands:');
            $stdio->outln('  cron          Run all registered cron tasks');
            $stdio->outln('  cron list     List registered cron tasks');
            $stdio->outln('  cron <name>   Run a specific task');
            $stdio->outln('');
            $stdio->outln('Registered tasks:');
            static::listTasks($stdio);
        }

        public static function run(array $args, Context $context, Stdio $stdio): void {
            $subCommand = $args[0] ?? null;

            if ($subCommand === 'list') {
                static::listTasks($stdio);

                return;
            }

            $class = static::$cronServiceClass;

            if ($subCommand !== null) {
                $exitCode = $class::runTask($subCommand, $stdio);

                exit($exitCode);
            }

            $exitCode = $class::runAll($stdio);

            exit($exitCode);
        }

        private static function listTasks(Stdio $stdio): void {
            $class = static::$cronServiceClass;
            $class::listTasks($stdio);
        }
    }
}
