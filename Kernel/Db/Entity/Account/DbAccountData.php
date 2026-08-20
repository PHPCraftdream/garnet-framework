<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use Aura\SqlQuery\Common\SelectInterface;
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

        /**
         * @param array $names Param names to read (filters accounts_data.param via WHERE ... IN).
         * @param array|null $accountIds Optional bounded set of account ids to read (filters
         *     accounts_data.account_id via WHERE ... IN). Pass null (default) to read the
         *     requested params for ALL accounts — existing callers that don't know a candidate
         *     id set keep working unchanged.
         * @return array
         */
        public static function getAllUsersData(array $names, ?array $accountIds = null): array {
            if (empty($names) || $accountIds === []) {
                return [];
            }

            $data = DbAccountData::get()->selectAll(function (SelectInterface $select) use ($names, $accountIds): void {
                $select->where('param in (?)', [$names]);

                if ($accountIds !== null) {
                    $select->where('account_id in (?)', [$accountIds]);
                }
            });
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
