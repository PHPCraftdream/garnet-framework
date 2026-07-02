<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;

    class CMDHelp implements ICommand {
        /**
         * @return string
         */
        public static function description(): string {
            return 'Prints list of commands';
        }

        /**
         * @param array<array-key, string> $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         */
        public static function help(array $args, Context $context, Stdio $stdio): void {
            static::listAllCommands($args, $context, $stdio);
        }

        /**
         * @param array<array-key, string> $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         * @throws CommandException
         */
        public static function run(array $args, Context $context, Stdio $stdio): void {
            $command = $args[0] ?? null;

            if (empty($command)) {
                static::listAllCommands($args, $context, $stdio);

                return;
            }

            $commands = CommandClasses::all();

            if (empty($commands[$command])) {
                throw new CommandException('Unknown command: ' . $command);
            }

            $commandClass = $commands[$command];
            $commandRunHelp = "{$commandClass}::help";

            if (!is_callable($commandRunHelp)) {
                throw new CommandException('No method: ' . $commandRunHelp);
            }

            $commandRunHelp($args, $context, $stdio);
        }

        /**
         * @param array<array-key, string> $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         */
        protected static function listAllCommands(array $args, Context $context, Stdio $stdio): void {
            $commands = CommandClasses::all();
            $cmdStrPad = max(10, StrTools::maxKeyLen($commands) + 1);

            foreach (CommandClasses::all() as $command => $className) {
                /**
                 * @var callable():string $runDescription
                 */
                $runDescription = "{$className}::description";
                $description = $runDescription();
                $printCommand = StrTools::pad($command, $cmdStrPad);

                $stdio->outln("{$printCommand} - " . $description);
            }
        }
    }
}
