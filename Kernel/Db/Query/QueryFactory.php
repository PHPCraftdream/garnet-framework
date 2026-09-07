<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    use Aura\Sql\Exception;
    use Aura\SqlQuery\QueryFactory as AuraSqlQueryQueryFactory;
    use PHPCraftdream\Garnet\Kernel\Db\Query\Builders\PositionalBindTrait;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class QueryFactory extends AuraSqlQueryQueryFactory {
        /**
         * Query types Garnet builds itself instead of taking Aura's class.
         *
         * Only the statements that carry a WHERE are listed: they are the ones
         * whose positional binds collapse across chained where() calls (see
         * {@see PositionalBindTrait}). INSERT has no WHERE and is left alone.
         */
        private const OWN_QUERY_TYPES = ['Select', 'Update', 'Delete'];

        protected static ?QueryFactory $instance = null;

        public static function get(): QueryFactory {
            if (empty(static::$instance)) {
                $config = IniConfig::db();
                $type = $config->param('type');

                if (empty($type)) {
                    throw new Exception('Empty db type from config');
                }

                $item = new static($type);
                static::$instance = $item;
            }

            return static::$instance;
        }

        /**
         * Builds SELECT/UPDATE/DELETE from Garnet's own subclasses so that
         * chained positional binds keep their values; everything else falls
         * through to Aura unchanged.
         *
         * The substitution is driver-scoped on purpose. A subclass has to
         * extend the driver's own query class to keep its dialect, so the
         * swap only happens for a driver Garnet actually ships classes for —
         * any other driver, or the driver-agnostic "common" mode, keeps
         * Aura's original class.
         *
         * @param string $query
         * @return mixed
         */
        protected function newInstance($query) {
            if ($this->common || !in_array($query, self::OWN_QUERY_TYPES, true)) {
                return parent::newInstance($query);
            }

            $queryClass = __NAMESPACE__ . "\\Builders\\{$this->db}\\{$query}";

            if (!class_exists($queryClass)) {
                return parent::newInstance($query);
            }

            return new $queryClass($this->getQuoter(), $this->newBuilder($query));
        }
    }
}
