<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    use Aura\SqlQuery\Common\DeleteInterface;
    use Aura\SqlQuery\Common\InsertInterface;
    use Aura\SqlQuery\Common\SelectInterface;
    use Aura\SqlQuery\Common\UpdateInterface;
    use Aura\SqlQuery\QueryInterface;
    use Generator;
    use PDO;
    use PDOException;
    use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;

    class QueryExPdo {
        /**
         * @var static
         */
        protected static ?QueryExPdo $instance = null;

        protected ExtPDO|null $pdo = null;

        protected function __construct() {
        }

        public static function get(): static {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        /**
         * @var int
         */
        protected int $fetch = PDO::FETCH_ASSOC;

        /**
         * @return int
         */
        public function getFetch(): int {
            return $this->fetch;
        }

        /**
         * @param int $fetch
         * @return void
         */
        public function setFetch(int $fetch): void {
            $this->fetch = $fetch;
        }

        /**
         * @param ExtPDO $pdo
         * @return $this
         */
        public function setPDO(ExtPDO $pdo): QueryExPdo {
            $this->pdo = $pdo;

            return $this;
        }

        /**
         * @return ExtPDO
         */
        public function getPDO(): ExtPDO {
            return $this->pdo;
        }

        /**
         * @var LastQuery|null
         */
        protected LastQuery|null $lastQuery = null;

        /**
         * @param QueryInterface $query
         * @return void
         */
        protected function saveLastQuery(QueryInterface $query): void {
            $this->lastQuery = new LastQuery($query->getStatement(), $query->getBindValues());
        }

        /**
         * @return LastQuery|null
         */
        public function getLastQuery(): LastQuery|null {
            return $this->lastQuery;
        }

        /**
         * @param SelectInterface $query
         * @return array
         * @throws DbException
         */
        public function exSelect(SelectInterface $query): array {
            $pdo = $this->getPDO();

            $this->saveLastQuery($query);

            $values = $query->getBindValues();
            $sql = $query->getStatement();
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);
            $statement = $pdo->prepare($sql);
            $statement->execute($values);

            $result = [];
            $fetch = $this->getFetch();

            while ($item = $statement->fetch($fetch)) {
                $result[] = $item;
            }

            return $result;
        }

        /**
         * @param SelectInterface $query
         * @return int
         * @throws DbException
         */
        public function selectCount(SelectInterface $query): int {
            $query->resetCols();
            $query->cols(["count(*) as '__cnt__'"]);

            $pdo = $this->getPDO();
            $this->saveLastQuery($query);

            $values = $query->getBindValues();
            $sql = $query->getStatement();
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);
            $statement = $pdo->prepare($sql);
            $statement->execute($values);

            $item = $statement->fetch(PDO::FETCH_OBJ);

            return is_object($item) && property_exists($item, '__cnt__') ? intval($item->__cnt__) : 0;
        }

        /**
         * @param QueryInterface $query
         * @return bool
         * @throws DbException
         */
        protected function processQuery(QueryInterface $query): bool {
            $pdo = $this->getPDO();
            $this->saveLastQuery($query);

            $values = $query->getBindValues();
            $sql = $query->getStatement();
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);
            $statement = $pdo->prepare($sql);

            return $statement->execute($values);
        }

        /**
         * @param InsertInterface $query
         * @param string $idField
         * @return string|false
         * @throws DbException
         */
        public function exInsert(InsertInterface $query, string $idField = 'id'): string|false {
            $this->processQuery($query);

            $pdo = $this->getPDO();

            return $pdo->lastInsertId($idField);
        }

        /**
         * @param UpdateInterface $query
         * @return bool
         * @throws DbException
         */
        public function exUpdate(UpdateInterface $query): bool {
            return $this->processQuery($query);
        }

        /**
         * @param DeleteInterface $query
         * @return bool
         * @throws DbException
         */
        public function exDelete(DeleteInterface $query): bool {
            return $this->processQuery($query);
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $args
         * @return bool
         * @throws DbException
         */
        public function ex(string $sql, array $args = []): bool {
            $pdo = $this->getPDO();
            [$sql, $args] = QueryTools::patchArgsIndexed($sql, $args);
            $this->lastQuery = new LastQuery($sql, $args);
            $statement = $pdo->prepare($sql);

            try {
                return $statement->execute($args);
            } catch (PDOException $e) {
                $dbEx = new DbException('QueryEx error', 0, $e);
                $dbEx->setSql($sql);
                $dbEx->setArgs($args);

                throw $dbEx;
            }
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $args
         * @param string $idField
         * @return string|false
         * @throws DbException
         */
        public function exSimpleInsert(string $sql, array $args = [], string $idField = 'id'): string|false {
            $pdo = $this->getPDO();
            [$sql, $args] = QueryTools::patchArgsIndexed($sql, $args);
            $this->lastQuery = new LastQuery($sql, $args);
            $statement = $pdo->prepare($sql);
            $statement->execute($args);

            return $pdo->lastInsertId($idField);
        }

        /**
         * @param InsertInterface $query
         * @return void
         * @throws DbException
         */
        public function exInsertIgnore(InsertInterface $query): void {
            $pdo = $this->getPDO();

            $values = $query->getBindValues();
            $sql = $query->getStatement();

            $sql = 'INSERT IGNORE ' . substr($sql, 6);
            [$sql, $values] = QueryTools::patchArgsIndexed($sql, $values);

            $this->lastQuery = new LastQuery($sql, $values);
            $statement = $pdo->prepare($sql);
            $statement->execute($values);
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $args
         * @return array
         * @throws DbException
         */
        public function exFetch(string $sql, array $args = []): array {
            $pdo = $this->getPDO();

            [$sql, $args] = QueryTools::patchArgsIndexed($sql, $args);
            $this->lastQuery = new LastQuery($sql, $args);
            $statement = $pdo->prepare($sql);
            $statement->execute($args);

            $result = [];
            $fetch = $this->getFetch();

            while ($item = $statement->fetch($fetch)) {
                $result[] = $item;
            }

            return $result;
        }

        /**
         * @param string $sql
         * @param array<string|int, string|int|float> $args
         * @return Generator
         * @throws DbException
         */
        public function exFetchItr(string $sql, array $args = []): Generator {
            $pdo = $this->getPDO();

            [$sql, $args] = QueryTools::patchArgsIndexed($sql, $args);
            $this->lastQuery = new LastQuery($sql, $args);
            $statement = $pdo->prepare($sql);
            $statement->execute($args);

            $fetch = $this->getFetch();

            while ($item = $statement->fetch($fetch)) {
                yield $item;
            }
        }
    }
}
