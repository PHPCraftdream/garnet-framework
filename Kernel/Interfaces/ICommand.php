<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    use Aura\Cli\Context;
    use Aura\Cli\Stdio;

    interface ICommand {
        public static function description(): string;

        /**
         * @param array<array-key, string> $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         */
        public static function help(array $args, Context $context, Stdio $stdio): void;

        /**
         * @param list<string> $args
         * @param Context $context
         * @param Stdio $stdio
         * @return void
         */
        public static function run(array $args, Context $context, Stdio $stdio): void;
    }
}
