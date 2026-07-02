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
                $pdo = new static(
                    $config->param('dsn'),
                    $config->param('user'),
                    $config->param('password'),
                    $config->param('options', []),
                );

                $attrs = $config->param('attrs', []);

                if (!empty($attrs) && is_array($attrs)) {
                    foreach ($attrs as $key => $value) {
                        $pdo->setAttribute(intval($key), $value);
                    }
                }

                static::$instance = $pdo;
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
