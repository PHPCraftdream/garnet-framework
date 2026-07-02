<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Migrations {
    use PHPCraftdream\Application\Migrations\Items\M_0002;
    use PHPCraftdream\Application\Migrations\Items\M_0003;
    use PHPCraftdream\Application\Migrations\Items\M_0004;
    use PHPCraftdream\Application\Migrations\Items\M_0005;
    use PHPCraftdream\Application\Migrations\Items\M_0006;
    use PHPCraftdream\Application\Migrations\Items\M_0007;
    use PHPCraftdream\Application\Migrations\Items\M_0008;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Migration;

    class AppMigration extends Migration {
        protected int $currentVersion = 8;

        /**
         * @var array|class-string[]
         */
        protected array $migrationClasses = [
            2 => M_0002::class,
            3 => M_0003::class,
            4 => M_0004::class,
            5 => M_0005::class,
            6 => M_0006::class,
            7 => M_0007::class,
            8 => M_0008::class,
        ];
    }
}
