<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables {
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbTableException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbM2M;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    abstract class DbM2M extends DbTable implements IDbM2M {
        protected string $DbTableClass1;

        protected string $DbTableClass2;

        protected string $primaryKey = 'id';

        protected array $defaultSelect = ['*'];

        protected int $defaultPageSize = 10;

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::initM2M(table: static::get());
        }

        // ##############################################################################################################
        public function getTable1(): string {
            $class = $this->DbTableClass1;

            $objGet = $this->getCallableString("{$class}::get");
            $obj = $objGet();

            return $obj->getTableName();
        }

        public function getEntityName1(): string {
            $class = $this->DbTableClass1;

            $objGet = $this->getCallableString("{$class}::get");
            $obj = $objGet();

            return $obj->getEntityName();
        }

        public function getKey1(): string {
            return $this->getEntityName1() . '_id';
        }
        // --------------------------------------------------------------------------------------------------------------

        public function getTable2(): string {
            $class = $this->DbTableClass2;

            $objGet = $this->getCallableString("{$class}::get");
            $obj = $objGet();

            return $obj->getTableName();
        }

        public function getEntityName2(): string {
            $class = $this->DbTableClass2;

            $objGet = $this->getCallableString("{$class}::get");
            $obj = $objGet();

            return $obj->getEntityName();
        }

        public function getKey2(): string {
            return $this->getEntityName2() . '_id';
        }
        // --------------------------------------------------------------------------------------------------------------

        public function getTableName(): string {
            $tableName1 = mb_strtolower($this->getEntityName1());
            $tableName2 = mb_strtolower($this->getEntityName2());
            $config = IniConfig::get(IniConfig::ENV_DB);
            $prefix = $config->paramString('prefix', '');

            if (!empty($prefix)) {
                $prefix .= '_';
            }

            return $prefix . $tableName1 . '_' . $tableName2;
        }
        // ##############################################################################################################

        public function getPrimaryKey(): string {
            return $this->primaryKey;
        }
        // ##############################################################################################################

        /**
         * @param string $callable
         * @return callable-string
         * @throws DbTableException
         */
        protected function getCallableString(string $callable): string {
            if (!is_callable($callable)) {
                throw new DbTableException('Can not call: ' . $callable);
            }

            return $callable;
        }

        protected function updateLink(array|int $ids1, array|int $ids2, int $value): bool {
            $key1 = $this->getKey1();
            $key2 = $this->getKey2();

            $ids1Array = is_array($ids1) ? $ids1 : [$ids1];
            $ids2Array = is_array($ids2) ? $ids2 : [$ids2];

            $queryData = [];

            foreach ($ids1Array as $id1) {
                foreach ($ids2Array as $id2) {
                    $key1 = intval($id1);
                    $key2 = intval($id2);
                    $value = intval($value);
                    $queryData[] = "({$key1}, {$key2}, {$value})";
                }
            }

            $table = $this->getTableName();
            $values = join(', ', $queryData);
            $onConflict = 'ON DUPLICATE KEY UPDATE value = VALUES(value)';
            $sql = "INSERT INTO `{$table}` (`{$key1}`, `{$key2}`, `value`) ({$values}) {$onConflict};";

            $queryEx = $this->getQueryEx();

            $result = $queryEx->ex($sql, []);

            return is_bool($result) ? $result : true;
        }

        public function createLinks(array|int $ids1, array|int $ids2, int $value = 1): bool {
            return $this->updateLink($ids1, $ids2, 1);
        }

        public function deleteLinks(array|int $ids1, array|int $ids2): bool {
            return $this->updateLink($ids1, $ids2, 0);
        }
        // ##############################################################################################################

        protected function updateKeyLinks(int $idKey1, string $key1, string $key2, array|int $ids2, int $value = 1): bool {
            $ids2Array = is_array($ids2) ? $ids2 : [$ids2];
            $ids2 = array_map('intval', $ids2Array);
            $ids2 = join(', ', $ids2);

            $query = $this->newUpdate();

            $query->cols(['values' => "IF(`{$key2}` in ({$ids2}), {$value}, 0)"]);
            $query->where("`{$key1}` = ", [$idKey1]);

            return $this->getQueryEx()->exUpdate($query);
        }

        public function updateKey1Links(int $idKey1, array|int $ids2, int $value = 1): bool {
            $key1 = $this->getKey1();
            $key2 = $this->getKey2();

            return $this->updateKeyLinks($idKey1, $key1, $key2, $ids2, $value);
        }

        public function updateKey2Links(int $idKey1, array|int $ids2, int $value = 1): bool {
            $key1 = $this->getKey1();
            $key2 = $this->getKey2();

            return $this->updateKeyLinks($idKey1, $key1, $key2, $ids2, $value);
        }

        // ##############################################################################################################
    }
}
