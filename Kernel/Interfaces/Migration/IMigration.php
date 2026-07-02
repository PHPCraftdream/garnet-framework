<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Migration {
    use Aura\Cli\Stdio;

    interface IMigration {
        public function migrate(Stdio $stdio): void;

        public function getCurrentVersion(): int;
    }
}
