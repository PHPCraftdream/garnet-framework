<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ICommand;
    use ReflectionException;

    class CommandClasses {
        /**
         * @var array<string, class-string>
         */
        protected static array $classes = [];

        /**
         * @param string $command
         * @param class-string $className
         * @return void
         * @throws CommandException
         * @throws ReflectionException
         */
        public static function set(string $command, string $className): void {
            if (!class_exists($className)) {
                /** @phpstan-var string $className */
                $error = 'Unknown class (#1): ' . $className;

                throw new CommandException($error);
            }

            $interfaceName = ICommand::class;

            if (!Env::classImplements($className, $interfaceName)) {
                throw new CommandException("{$className} must implement interface `{$interfaceName}`");
            }

            static::$classes[$command] = $className;
        }

        /**
         * @param string $command
         * @return string
         * @throws CommandException
         * @throws ReflectionException
         */
        public static function get(string $command): string {
            if (empty(static::$classes[$command])) {
                throw new CommandException('Command not found: ' . $command);
            }

            $className = static::$classes[$command];

            if (!class_exists($className)) {
                /** @phpstan-var string $className */
                throw new CommandException('Unknown class (#2): ' . $className);
            }

            $interfaceName = ICommand::class;

            if (!Env::classImplements($className, $interfaceName)) {
                throw new CommandException("{$className} must implement interface `{$interfaceName}`");
            }

            return $className;
        }

        /**
         * @return array<string, class-string>
         */
        public static function all(): array {
            return static::$classes;
        }
    }
}
