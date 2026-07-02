<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;

    /**
     * Do-nothing command. Used by Garnet-level commands that need to
     * boot the app (so its CommandClasses and DI bindings are wired up)
     * without actually executing any user command. The canonical caller
     * is GarnetMigrateStatusCommand, which invokes run_cmd.php with
     * `noop` just to load the app, then queries the migration tables
     * directly from its own process.
     */
    class CMDNoop implements ICommand {
        public static function description(): string {
            return 'Boot the app without running anything (internal helper)';
        }

        public static function help(array $args, Context $context, Stdio $stdio): void {
            $stdio->outln('noop — does nothing. Used by tooling to bootstrap the app.');
        }

        public static function run(array $args, Context $context, Stdio $stdio): void {
            // Intentionally empty.
        }
    }
}
