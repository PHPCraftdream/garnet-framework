<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations\Items {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    // Reserved slot — kept as a no-op so the migration version sequence has
    // no gaps (the runner iterates a contiguous range starting at 1 and
    // errors on a missing version). Replace the body with your own schema
    // change. The framework's own base tables (session, session_data,
    // settings, accounts, accounts_data) are already created by M_0002,
    // M_0003 and M_0008 further down the chain — this slot doesn't need to
    // duplicate them.
    class M_0001 implements IMigrationItem {
        public static function update(Stdio $stdio): void {
        }
    }
}
