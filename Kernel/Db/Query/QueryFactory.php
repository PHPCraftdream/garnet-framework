<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    use Aura\Sql\Exception;
    use Aura\SqlQuery\QueryFactory as AuraSqlQueryQueryFactory;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class QueryFactory extends AuraSqlQueryQueryFactory {
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
    }
}
