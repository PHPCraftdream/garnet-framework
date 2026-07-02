<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations\Items {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    // Reserved slot — kept as a no-op so the migration version sequence has
    // no gaps (the runner iterates a contiguous range and errors on a
    // missing version). Replace the body with your own schema change.
    class M_0006 implements IMigrationItem {
        public static function update(Stdio $stdio): void {
        }
    }
}
