<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link {
    use Aura\Sql\ExtendedPdo;
    use PDOStatement;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class ExtPDO extends ExtendedPdo {
        public const DB_TYPE_MYSQL = 'mysql';

        /**
         * @var static
         */
        protected static ?ExtPDO $instance = null;

        public static function get(): static {
            if (empty(static::$instance)) {
                $config = IniConfig::get(IniConfig::ENV_DB);
                $options = (array)$config->param('options', []);

                // Merge `attrs` into the constructor's $options instead of
                // calling setAttribute() afterward. Aura SQL's ExtendedPdo
                // is lazy by design — the real PDO connection isn't opened
                // until the first actual query — but setAttribute() forces
                // an immediate connect(), which defeats that laziness for
                // any code path that merely instantiates ExtPDO without
                // ever querying (e.g. this framework's own kahlan test
                // bootstrap, shared by both the DB-free Kernel suite and
                // the DB-backed Bundle suite — the eager connect() here
                // made every "no DB" Kernel test run crash when no MySQL
                // was reachable).
                $attrs = $config->param('attrs', []);

                if (!empty($attrs) && is_array($attrs)) {
                    foreach ($attrs as $key => $value) {
                        $options[intval($key)] = $value;
                    }
                }

                static::$instance = new static(
                    $config->param('dsn'),
                    $config->param('user'),
                    $config->param('password'),
                    $options,
                );
            }

            return static::$instance;
        }

        /**
         * @param string $statement
         * @param array $options
         * @return PDOStatement
         * @throws DbException
         */
        public function prepare($statement, $options = []): PDOStatement {
            $result = parent::prepare($statement, $options);

            if (!$result) {
                throw new DbException('Cannot prepare: ' . $statement);
            }

            return $result;
        }
    }
}
