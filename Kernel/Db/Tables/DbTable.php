<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables {
    use Aura\Sql\Exception;
    use Aura\SqlQuery\Common\DeleteInterface;
    use Aura\SqlQuery\Common\InsertInterface;
    use Aura\SqlQuery\Common\SelectInterface;
    use Aura\SqlQuery\Common\UpdateInterface;
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryFactory;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    abstract class DbTable implements IDbTable {
        protected static array $items = [];

        protected ?string $prefix = null;

        protected string $tableName;

        protected string $primaryKey = 'id';

        protected array $defaultSelect = ['*'];

        protected int $defaultPageSize = 10;

        // ##############################################################################################################

        protected function __construct() {
        }

        abstract public static function init(): ITableBuilderDriver;

        public static function get(): static {
            $className = static::class;

            if (empty(self::$items[$className])) {
                $res = new static();
                self::$items[$className] = $res;
            }

            return self::$items[$className];
        }

        /**
         * @param callable|null $callback
         * @return void
         * @throws DbException
         * @throws IniConfigException
         */
        public function dropTableAsync(callable $callback = null): void {
            $this->getQueryEx()->exAsync("DROP TABLE IF EXISTS `{$this->getTableName()}`;", [], $callback);
        }

        /**
         * @return bool
         * @throws DbException
         * @throws IniConfigException
         */
        public function dropTable(): bool {
            return $this->getQueryEx()->ex("DROP TABLE IF EXISTS `{$this->getTableName()}`;");
        }

        // ##############################################################################################################

        public function getPrimaryKey(): string {
            return $this->primaryKey;
        }

        /**
         * @return string
         * @throws IniConfigException
         */
        protected function getPrefix(): string {
            if ($this->prefix !== null) {
                $prefix = $this->prefix;

                return empty($prefix) ? '' : "{$prefix}_";
            }

            $prefix = IniConfig::db()->param('prefix', '');

            return empty($prefix) || !is_string($prefix) ? '' : "{$prefix}_";
        }

        /**
         * @return string
         * @throws IniConfigException
         */
        public function getTableName(): string {
            $tableName = $this->tableName;
            $prefix = $this->getPrefix();

            return $prefix . $tableName;
        }

        public function getEntityName(): string {
            return $this->tableName;
        }

        /**
         * @return int
         * @throws IniConfigException
         */
        public function getPageSize(): int {
            $config = IniConfig::db();

            return $config->paramInt('pageSize', $this->defaultPageSize);
        }

        /**
         * @return QueryEx
         */
        public function getQueryEx(): QueryEx {
            return QueryEx::get();
        }

        // ##############################################################################################################

        /**
         * @return SelectInterface
         * @throws IniConfigException
         * @throws Exception
         */
        public function newSelect(): SelectInterface {
            $table = $this->getTableName();
            $queryFactory = QueryFactory::get();
            $query = $queryFactory->newSelect();
            $query->from($table);
            $query->cols($this->defaultSelect);

            return $query;
        }

        /**
         * @return InsertInterface
         * @throws IniConfigException
         * @throws Exception
         */
        public function newInsert(): InsertInterface {
            $table = $this->getTableName();
            $queryFactory = QueryFactory::get();
            $query = $queryFactory->newInsert();
            $query->into($table);

            return $query;
        }

        /**
         * @return UpdateInterface
         * @throws IniConfigException
         * @throws Exception
         */
        public function newUpdate(): UpdateInterface {
            $table = $this->getTableName();
            $queryFactory = QueryFactory::get();
            $query = $queryFactory->newUpdate();
            $query->table($table);

            return $query;
        }

        /**
         * @return DeleteInterface
         * @throws IniConfigException
         * @throws Exception
         */
        public function newDelete(): DeleteInterface {
            $table = $this->getTableName();
            $queryFactory = QueryFactory::get();
            $query = $queryFactory->newDelete();
            $query->from($table);

            return $query;
        }

        // ##############################################################################################################

        /**
         * @param callable|null $queryCallback
         * @param callable|null $callback
         * @return void
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function getCountAsync(callable $queryCallback = null, callable $callback = null): void {
            $query = $this->newSelect();

            if ($queryCallback) {
                $queryCallback($query);
            }

            $this->getQueryEx()->selectCountAsync($query, $callback);
        }

        /**
         * @param callable|null $queryCallback
         * @return int
         * @throws DbException
         * @throws IniConfigException
         * @throws Exception
         */
        public function getCount(callable $queryCallback = null): int {
            $query = $this->newSelect();

            if ($queryCallback) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->selectCount($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string|int $id
         * @param callable|null $callback
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function existsByIdAsync(string|int $id, callable $callback = null): void {
            $this->getCountAsync(
                function (SelectInterface $query) use ($id): void {
                    $pk = $this->getPrimaryKey();
                    $query->where("`{$pk}` = ?", [$id]);
                },
                $callback,
            );
        }

        /**
         * @param string|int $id
         * @return bool
         * @throws DbException
         * @throws IniConfigException
         * @throws Exception
         */
        public function existsById(string|int $id): bool {
            $count = $this->getCount(
                function (SelectInterface $query) use ($id): void {
                    $pk = $this->getPrimaryKey();
                    $query->where("`{$pk}` = ?", [$id]);
                }
            );

            return $count > 0;
        }

        // ##############################################################################################################

        /**
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectAllAsync(Closure $queryCallback = null, callable $callback = null): IDbMySQLiLink {
            $query = $this->newSelect();

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelectAsync($query, $callback);
        }

        /**
         * @param Closure|null $queryCallback
         * @return array
         * @throws DbException
         * @throws IniConfigException
         * @throws Exception
         */
        public function selectAll(Closure $queryCallback = null): array {
            $query = $this->newSelect();

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelect($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param int $page
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectPageAsync(int $page, Closure $queryCallback = null, callable $callback = null): IDbMySQLiLink {
            $query = $this->newSelect();
            $count = $this->getCount($queryCallback);
            $pageSize = $this->getPageSize();

            $pageData = new PageData($page, $count, $pageSize);
            $query->limit($pageSize);
            $query->offset($pageData->offset);

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelectAsync($query, function (array $items) use ($pageData, $callback): void {
                $pageData->pageItems = $items;
                $pageData->pageItemsCount = count($pageData->pageItems);

                $callback($pageData);
            });
        }

        /**
         * @param int $page
         * @param Closure|null $queryCallback
         * @return PageData
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectPage(int $page, Closure $queryCallback = null): PageData {
            $query = $this->newSelect();
            $count = $this->getCount($queryCallback);
            $pageSize = $this->getPageSize();

            $pageData = new PageData($page, $count, $pageSize);
            $query->limit($pageSize);
            $query->offset($pageData->offset);

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            $pageData->pageItems = $this->getQueryEx()->exSelect($query);
            $pageData->pageItemsCount = count($pageData->pageItems);

            return $pageData;
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string $field
         * @param int|string|array $value
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectByFieldAsync(
            string $field,
            int|string|array $value,
            Closure $queryCallback = null,
            callable $callback = null
        ): IDbMySQLiLink {
            $query = $this->newSelect();

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelectAsync($query, $callback);
        }

        /**
         * @param string $field
         * @param int|string|array $value
         * @param Closure|null $queryCallback
         * @return array
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectByField(string $field, int|string|array $value, Closure $queryCallback = null): array {
            $query = $this->newSelect();

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelect($query);
        }

        /**
         * @param string $field
         * @param int|float|string $value
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws IniConfigException
         */
        public function simpleSelectByFieldAsync(
            string $field,
            int|float|string $value,
            callable $callback = null,
        ): IDbMySQLiLink {
            $where = QueryTools::fieldVal($field, $value);
            $sql = "SELECT * FROM `{$this->getTableName()}` WHERE {$where};";

            return $this->getQueryEx()->exAsync($sql, [], fn ($rows) => $callback($rows));
        }

        /**
         * @param string $field
         * @param int|float|string $value
         * @return array|null
         * @throws DbException
         * @throws IniConfigException
         */
        public function simpleSelectByField(string $field, int|float|string $value): ?array {
            $where = QueryTools::fieldVal($field, $value);
            $sql = "SELECT * FROM `{$this->getTableName()}` WHERE {$where};";

            $result = $this->getQueryEx()->exFetch($sql, []);

            return $result ?: null;
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string $field
         * @param int|string|array $value
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectOneByFieldAsync(
            string $field,
            int|string|array $value,
            Closure $queryCallback = null,
            callable $callback = null,
        ): IDbMySQLiLink {
            return $this->selectByFieldAsync($field, $value, $queryCallback, fn ($rows) => $callback($rows[0] ?? null));
        }

        /**
         * @param string $field
         * @param int|string|array $value
         * @param Closure|null $queryCallback
         * @return array|null
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectOneByField(string $field, int|string|array $value, Closure $queryCallback = null): ?array {
            $result = $this->selectByField($field, $value, $queryCallback);

            return $result[0] ?? null;
        }

        /**
         * @param string $field
         * @param int|float|string $value
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws IniConfigException
         */
        public function simpleSelectOneByFieldAsync(
            string $field,
            int|float|string $value,
            callable $callback = null,
        ): IDbMySQLiLink {
            $where = QueryTools::fieldVal($field, $value);
            $sql = "SELECT * FROM `{$this->getTableName()}` WHERE {$where};";

            return $this->getQueryEx()->exAsync($sql, [], fn ($rows) => $callback($rows[0] ?? null));
        }

        /**
         * @param string $field
         * @param int|float|string $value
         * @return array|null
         * @throws DbException
         * @throws IniConfigException
         */
        public function simpleSelectOneByField(string $field, int|float|string $value): ?array {
            $where = QueryTools::fieldVal($field, $value);
            $sql = "SELECT * FROM `{$this->getTableName()}` WHERE {$where};";

            $result = $this->getQueryEx()->ex($sql, []);

            return $result[0] ?? null;
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param int|string|array $ids
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectByIdsAsync(
            int|string|array $ids,
            Closure $queryCallback = null,
            callable $callback = null
        ): IDbMySQLiLink {
            $pk = $this->getPrimaryKey();
            $query = $this->newSelect();

            if (is_array($ids)) {
                $query->where("`{$pk}` in (?)", [$ids]);
            } else {
                $query->where("`{$pk}` = ?", [$ids]);
            }

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelectAsync($query, $callback);
        }

        /**
         * @param int|string|array $ids
         * @param Closure|null $queryCallback
         * @return array
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectByIds(int|string|array $ids, Closure $queryCallback = null): array {
            $pk = $this->getPrimaryKey();
            $query = $this->newSelect();

            if (is_array($ids)) {
                $query->where("`{$pk}` in (?)", [$ids]);
            } else {
                $query->where("`{$pk}` = ?", [$ids]);
            }

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exSelect($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param int|string $id
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectByIdAsync(int|string $id, Closure $queryCallback = null, callable $callback = null): IDbMySQLiLink {
            $where = function (SelectInterface $query) use ($id, $queryCallback): void {
                $pk = $this->getPrimaryKey();
                $query->where("`{$pk}` = ?", [$id]);
                $query->limit(1);

                if (is_callable($queryCallback)) {
                    $queryCallback($query);
                }
            };

            return $this->selectAllAsync($where, fn ($rows) => $callback($rows[0] ?? null));
        }

        /**
         * @param int|string $id
         * @param Closure|null $queryCallback
         * @return array|null
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function selectById(int|string $id, Closure $queryCallback = null): ?array {
            $where = function (SelectInterface $query) use ($id, $queryCallback): void {
                $pk = $this->getPrimaryKey();
                $query->where("`{$pk}` = ?", [$id]);
                $query->limit(1);

                if (is_callable($queryCallback)) {
                    $queryCallback($query);
                }
            };

            $result = $this->selectAll($where);

            return $result[0] ?? null;
        }

        // ##############################################################################################################

        /**
         * @param array $data
         * @param Closure|null $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function insertAsync(array $data, Closure $queryCallback = null, callable $callback = null): IDbMySQLiLink {
            $query = $this->newInsert();
            $query->cols($data);

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exInsertAsync($query, $callback);
        }

        /**
         * @param array $data
         * @param Closure|null $queryCallback
         * @return false|string
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function insert(array $data, Closure $queryCallback = null): false|string {
            $query = $this->newInsert();
            $query->cols($data);

            if (is_callable($queryCallback)) {
                $queryCallback($query);
            }

            return $this->getQueryEx()->exInsert($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param array $queryData
         * @param string|null $onConflict
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws IniConfigException
         */
        public function insertBatchAsync(array $queryData, string $onConflict = null, callable $callback = null): IDbMySQLiLink {
            [$sql, $params] = QueryTools::makeInsertBatchIndexed(
                $this->getTableName(),
                $queryData,
                $onConflict,
                false,
            );

            return $this->getQueryEx()->exAsync($sql, $params, $callback);
        }

        /**
         * @param array $queryData
         * @param string|null $onConflict
         * @return bool
         * @throws DbException
         * @throws IniConfigException
         */
        public function insertBatch(array $queryData, string $onConflict = null): bool {
            [$sql, $params] = QueryTools::makeInsertBatchIndexed(
                $this->getTableName(),
                $queryData,
                $onConflict,
                false,
            );

            return $this->getQueryEx()->ex($sql, $params);
        }

        // ##############################################################################################################

        /**
         * @param array $updateData
         * @param Closure $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateByAsync(array $updateData, Closure $queryCallback, callable $callback = null): IDbMySQLiLink {
            $query = $this->newUpdate();

            $query->cols($updateData);
            $queryCallback($query);

            return $this->getQueryEx()->exUpdateAsync($query, $callback);
        }

        /**
         * @param array $updateData
         * @param Closure $queryCallback
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateBy(array $updateData, Closure $queryCallback): bool {
            $query = $this->newUpdate();

            $query->cols($updateData);
            $queryCallback($query);

            return $this->getQueryEx()->exUpdate($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param array $updateData
         * @param int|string|array $id
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateByIdAsync(array $updateData, int|string|array $id, callable $callback = null): IDbMySQLiLink {
            $pk = $this->getPrimaryKey();
            $query = $this->newUpdate();
            $query->cols($updateData);

            if (is_array($id)) {
                $query->where("`{$pk}` in (?)", [$id]);
            } else {
                $query->where("`{$pk}` = ?", [$id]);
            }

            return $this->getQueryEx()->exUpdateAsync($query, $callback);
        }

        /**
         * @param array $updateData
         * @param int|string|array $id
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateById(array $updateData, int|string|array $id): bool {
            $pk = $this->getPrimaryKey();
            $query = $this->newUpdate();
            $query->cols($updateData);

            if (is_array($id)) {
                $query->where("`{$pk}` in (?)", [$id]);
            } else {
                $query->where("`{$pk}` = ?", [$id]);
            }

            return $this->getQueryEx()->exUpdate($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param array $updateData
         * @param string $field
         * @param int|string|array $value
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateByFieldAsync(
            array $updateData,
            string $field,
            int|string|array $value,
            callable $callback = null
        ): IDbMySQLiLink {
            $query = $this->newUpdate();
            $query->cols($updateData);

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            return $this->getQueryEx()->exUpdateAsync($query, $callback);
        }

        /**
         * @param array $updateData
         * @param string $field
         * @param int|string|array $value
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function updateByField(array $updateData, string $field, int|string|array $value): bool {
            $query = $this->newUpdate();
            $query->cols($updateData);

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            return $this->getQueryEx()->exUpdate($query);
        }

        // #############################################################################################################

        /**
         * @param Closure $queryCallback
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteByAsync(Closure $queryCallback, callable $callback = null): IDbMySQLiLink {
            $query = $this->newDelete();
            $queryCallback($query);

            return $this->getQueryEx()->exDeleteAsync($query, $callback);
        }

        /**
         * @param Closure $queryCallback
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteBy(Closure $queryCallback): bool {
            $query = $this->newDelete();
            $queryCallback($query);

            return $this->getQueryEx()->exDelete($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param int|string|array $id
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteByIdAsync(int|string|array $id, callable $callback = null): IDbMySQLiLink {
            $pk = $this->getPrimaryKey();
            $query = $this->newDelete();

            if (is_array($id)) {
                $query->where("`{$pk}` in (?)", [$id]);
            } else {
                $query->where("`{$pk}` = ?", [$id]);
            }

            return $this->getQueryEx()->exDeleteAsync($query, $callback);
        }

        /**
         * @param int|string|array $id
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteById(int|string|array $id): bool {
            $pk = $this->getPrimaryKey();
            $query = $this->newDelete();

            if (is_array($id)) {
                $query->where("`{$pk}` in (?)", [$id]);
            } else {
                $query->where("`{$pk}` = ?", [$id]);
            }

            return $this->getQueryEx()->exDelete($query);
        }

        // -------------------------------------------------------------------------------------------------------------

        /**
         * @param string $field
         * @param int|string|array $value
         * @param callable|null $callback
         * @return IDbMySQLiLink
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteByFieldAsync(string $field, int|string|array $value, callable $callback = null): IDbMySQLiLink {
            $query = $this->newDelete();

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            return $this->getQueryEx()->exDeleteAsync($query, $callback);
        }

        /**
         * @param string $field
         * @param int|string|array $value
         * @return bool
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function deleteByField(string $field, int|string|array $value): bool {
            $query = $this->newDelete();

            if (is_array($value)) {
                $query->where("`{$field}` in (?)", [$value]);
            } else {
                $query->where("`{$field}` = ?", [$value]);
            }

            return $this->getQueryEx()->exDelete($query);
        }

        // ##############################################################################################################

        /**
         * @param string $primaryKey
         */
        public function setPrimaryKey(string $primaryKey): void {
            $this->primaryKey = $primaryKey;
        }
    }
}
