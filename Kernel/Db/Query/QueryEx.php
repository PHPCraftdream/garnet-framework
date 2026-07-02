<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    use Aura\SqlQuery\Common\DeleteInterface;
    use Aura\SqlQuery\Common\InsertInterface;
    use Aura\SqlQuery\Common\SelectInterface;
    use Aura\SqlQuery\Common\UpdateInterface;
    use Aura\SqlQuery\QueryInterface;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;

    class QueryEx {
        protected static ?QueryEx $instance = null;

        /**
         * @param IDbPool $dbPool
         */
        public function __construct(protected IDbPool $dbPool) {
        }

        /**
         * @return QueryEx
         */
        public static function get(): QueryEx {
            if (empty(static::$instance)) {
                $pool = DbPool::get();
                $item = new static($pool);
                static::$instance = $item;
            }

            return static::$instance;
        }

        protected function getSqlItems(QueryInterface $query): array {
            return QueryTools::patchArgsIndexed($query->getStatement(), $query->getBindValues());
        }

        // #############################################################################################################

        /**
         * @param QueryInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        protected function processQueryAsync(QueryInterface $query, callable $callback = null): IDbMySQLiLink {
            [$sql, $values] = $this->getSqlItems($query);

            return $this->dbPool->queryAsync($sql, $values, $callback);
        }

        /**
         * @param QueryInterface $query
         * @return array|int|string|bool
         * @throws DbException
         */
        protected function processQuery(QueryInterface $query): array|int|string|bool {
            [$sql, $values] = $this->getSqlItems($query);

            return $this->dbPool->query($sql, $values);
        }

        // #############################################################################################################

        /**
         * @param SelectInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exSelectAsync(SelectInterface $query, callable $callback = null): IDbMySQLiLink {
            return $this->processQueryAsync($query, $callback);
        }

        /**
         * @param SelectInterface $query
         * @return array
         * @throws DbException
         */
        public function exSelect(SelectInterface $query): array {
            return $this->processQuery($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param SelectInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function selectCountAsync(SelectInterface $query, callable $callback = null): IDbMySQLiLink {
            $query->cols(["count(*) as '__cnt__'"]);
            [$sql, $values] = $this->getSqlItems($query);

            return $this->dbPool->queryAsync($sql, $values, fn ($rows) => $callback(intval($rows[0]['__cnt__'] ?? 0)));
        }

        /**
         * @param SelectInterface $query
         * @return int
         * @throws DbException
         */
        public function selectCount(SelectInterface $query): int {
            $query->cols(["count(*) as '__cnt__'"]);
            [$sql, $values] = $this->getSqlItems($query);
            $rows = $this->dbPool->query($sql, $values);

            return intval($rows[0]['__cnt__'] ?? 0);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param InsertInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exInsertAsync(InsertInterface $query, callable $callback = null): IDbMySQLiLink {
            return $this->processQueryAsync($query, $callback);
        }

        /**
         * @param InsertInterface $query
         * @return string|false
         * @throws DbException
         */
        public function exInsert(InsertInterface $query): string|false {
            $insertId = $this->processQuery($query);

            return is_int($insertId) ? (string)$insertId : false ;
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param UpdateInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exUpdateAsync(UpdateInterface $query, callable $callback = null): IDbMySQLiLink {
            return $this->processQueryAsync($query, $callback);
        }

        /**
         * @param UpdateInterface $query
         * @return bool
         * @throws DbException
         */
        public function exUpdate(UpdateInterface $query): bool {
            return $this->processQuery($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param DeleteInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exDeleteAsync(DeleteInterface $query, callable $callback = null): IDbMySQLiLink {
            return $this->processQueryAsync($query, $callback);
        }

        /**
         * @param DeleteInterface $query
         * @return bool
         * @throws DbException
         */
        public function exDelete(DeleteInterface $query): bool {
            return $this->processQuery($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $values
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exAsync(string $sql, array $values = [], callable $callback = null): IDbMySQLiLink {
            return $this->dbPool->queryAsync($sql, $values, $callback);
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $values
         * @return bool|int
         * @throws DbException
         */
        public function ex(string $sql, array $values = []): bool|int|array {
            return $this->dbPool->query($sql, $values);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param InsertInterface $query
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exInsertIgnoreAsync(InsertInterface $query, callable $callback = null): IDbMySQLiLink {
            $values = $query->getBindValues();
            $sql = $query->getStatement();

            $sql = 'INSERT IGNORE ' . substr($sql, 6);
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);

            return $this->dbPool->queryAsync($sql, $values, $callback);
        }

        /**
         * @param InsertInterface $query
         * @return array|int|string|bool
         * @throws DbException
         */
        public function exInsertIgnore(InsertInterface $query): array|int|string|bool {
            $values = $query->getBindValues();
            $sql = $query->getStatement();

            $sql = 'INSERT IGNORE ' . substr($sql, 6);
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);

            return $this->dbPool->query($sql, $values);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $values
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function exFetchAsync(string $sql, array $values = [], callable $callback = null): IDbMySQLiLink {
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);

            return $this->dbPool->queryAsync($sql, $values, $callback);
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $values
         * @return array|int|string|bool
         * @throws DbException
         */
        public function exFetch(string $sql, array $values = []): array|int|string|bool {
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);

            return $this->dbPool->query($sql, $values);
        }
    }
}
