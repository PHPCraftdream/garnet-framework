<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Builders\Mysql {
    use Aura\SqlQuery\Mysql\Delete as AuraMysqlDelete;
    use PHPCraftdream\Garnet\Kernel\Db\Query\Builders\PositionalBindTrait;

    /**
     * MySQL DELETE whose positional binds survive chained where() calls —
     * see {@see PositionalBindTrait}. As with UPDATE, a collapsed WHERE here
     * widens the statement instead of narrowing it.
     */
    class Delete extends AuraMysqlDelete {
        use PositionalBindTrait;
    }
}
