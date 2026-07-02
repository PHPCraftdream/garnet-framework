<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class DbAccountData extends DbTable {
        public const NAME_LENGTH = 32;

        public const VAL_LENGTH = 255;

        protected string $tableName = 'accounts_data';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            $nameLen = static::NAME_LENGTH . '';
            $valLen = static::VAL_LENGTH . '';

            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'account_id', type: 'VARCHAR', length: $nameLen)
                ->addColumn(column: 'param', type: 'VARCHAR', length: $nameLen)
                ->addColumn(column: 'value', type: 'VARCHAR', length: $valLen)
                ->addIndex(indexName: 'login', indexes: ['account_id', 'param'], type: 'UNIQUE')
            ;
        }

        public static function getAllUsersData(array $names): array {
            $data = DbAccountData::get()->selectAll();
            $result = [];
            $namesMap = [];

            foreach ($names as $name) {
                $namesMap[$name] = true;
            }

            foreach ($data as $item) {
                if (!isset($item['account_id']) || !isset($item['param']) || !isset($item['value'])) {
                    continue;
                }

                $val = StrTools::isIntStr($item['value']) ? intval($item['value']) : $item['value'];
                $id = $item['account_id'];
                $name = $item['param'];

                if (empty($namesMap[$name])) {
                    $result[$name] = null;

                    continue;
                }

                if (isset($result[$id])) {
                    $result[$id][$name] = $val;
                } else {
                    $result[$id] = [$name => $val];
                }
            }

            return $result;
        }
    }
}
