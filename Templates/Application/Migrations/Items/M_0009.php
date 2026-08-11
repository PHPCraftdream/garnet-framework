<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations\Items {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Application\Common\Tables\MagicLoginTokens;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigrationItem;

    class M_0009 implements IMigrationItem {
        public static function update(Stdio $stdio): void {
            MagicLoginTokens::get()->init()->ex();
        }
    }
}
