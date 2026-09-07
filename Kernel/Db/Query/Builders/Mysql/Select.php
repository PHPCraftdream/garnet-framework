<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Builders\Mysql {
    use Aura\SqlQuery\Mysql\Select as AuraMysqlSelect;
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Db\Query\Builders\PositionalBindTrait;

    /**
     * MySQL SELECT whose positional binds survive chained where()/having()
     * calls — see {@see PositionalBindTrait} for what goes wrong without it.
     */
    class Select extends AuraMysqlSelect {
        use PositionalBindTrait;

        /**
         * @param string|Closure $cond
         * @param array<array-key, mixed> $bind
         * @return $this
         */
        public function having($cond, array $bind = []) {
            [$cond, $bind] = $this->garnetNameBinds($cond, $bind);

            return parent::having($cond, $bind);
        }

        /**
         * @param string|Closure $cond
         * @param array<array-key, mixed> $bind
         * @return $this
         */
        public function orHaving($cond, array $bind = []) {
            [$cond, $bind] = $this->garnetNameBinds($cond, $bind);

            return parent::orHaving($cond, $bind);
        }
    }
}
