<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Migration {
    use Aura\Cli\Stdio;

    interface IMigrationItem {
        public static function update(Stdio $stdio): void;
    }
}
