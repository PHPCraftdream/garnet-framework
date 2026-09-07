<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Builders\Mysql {
    use Aura\SqlQuery\Mysql\Update as AuraMysqlUpdate;
    use PHPCraftdream\Garnet\Kernel\Db\Query\Builders\PositionalBindTrait;

    /**
     * MySQL UPDATE whose positional binds survive chained where() calls —
     * see {@see PositionalBindTrait}. An UPDATE with a collapsed WHERE is the
     * most damaging shape of this bug: the condition stops narrowing and the
     * statement writes to rows it was never meant to touch.
     */
    class Update extends AuraMysqlUpdate {
        use PositionalBindTrait;
    }
}
