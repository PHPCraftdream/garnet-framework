<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun {
    use Aura\Cli\CliFactory;
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IoException;
    use PHPCraftdream\Garnet\Kernel\Io\Command\CommandClasses;
    use ReflectionException;

    class IoRunConsole {
        /**
         * @return void
         * @throws CommandException
         * @throws IoException
         * @throws ReflectionException
         * @noinspection Annotator
         */
        public static function run(): void {
            if (!Env::isCmd()) {
                throw new IoException('Wrong environment');
            }

            $cliFactory = new CliFactory();
            $context = $cliFactory->newContext($GLOBALS);
            $stdio = $cliFactory->newStdio();

            /* @phpstan-ignore-next-line */
            $argv = $context->argv;
            $args = [];

            /* @phpstan-ignore-next-line */
            if (is_object($argv) && method_exists($argv, 'get')) {
                $argsResult = $argv->get();

                if (is_array($argsResult)) {
                    $args = $argsResult;
                }
            }

            $command = !empty($args[1]) ? $args[1] . '' : 'help';
            $cmdClass = CommandClasses::get($command);
            $cmdClassRun = "{$cmdClass}::run";
            $passArgs = array_slice($args, 2);

            if (!is_callable($cmdClassRun)) {
                throw new CommandException('Not callable: ' . $cmdClassRun);
            }

            $cmdClassRun($passArgs, $context, $stdio);
        }
    }
}
