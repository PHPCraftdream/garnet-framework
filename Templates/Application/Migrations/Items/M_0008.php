<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations\Items {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccountData;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    class M_0008 implements IMigrationItem {
        public static function update(Stdio $stdio): void {
            DbAccount::get()->init()->ex();
            DbAccountData::get()->init()->ex();
        }
    }
}
