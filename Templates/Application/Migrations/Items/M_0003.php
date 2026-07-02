<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations\Items {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\SettingsTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    class M_0003 implements IMigrationItem {
        public static function update(Stdio $stdio): void {
            SettingsTable::get()->init()->ex();
        }
    }
}
