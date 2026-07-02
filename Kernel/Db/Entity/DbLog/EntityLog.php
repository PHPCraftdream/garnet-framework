<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\DbLog {
    use Aura\Sql\Exception;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\ValidationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    class EntityLog extends DbTable {
        public string $tableName = 'entity_log';

        public string $primaryKey = 'id';

        public string $idForVersion = '1000';

        public static function init(): ITableBuilderDriver {
            $table = static::get();
            $pk = $table->getPrimaryKey();

            return DbTableBuilderFactory::newCreateTable(table: $table, engine: 'InnoDB')
                ->addIdColumn($pk)
                ->addColumn(column: 'user_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'ut', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'action', type: 'VARCHAR', length: '32', null: false)
                ->addColumn(column: 'entity', type: 'VARCHAR', length: '32', null: false)
                ->addColumn(column: 'entity_id', type: 'INT', length: '11', null: false)
                ->addColumn(column: 'is_diff', type: 'TINYINT', length: '1', null: false)
                ->addColumn(column: 'data', type: 'MEDIUMTEXT', length: null, null: false)
                ->addIndex(indexName: 'user_id', indexes: ['user_id'], type: 'INDEX')
                ->addIndex(indexName: 'entity', indexes: ['entity', 'entity_id'], type: 'INDEX')
            ;
        }

        /**
         * @throws IniConfigException
         * @throws Exception
         * @throws DbException
         * @throws ValidationException
         */
        public function writeLog(string $entity, string|int $entityId, string $action, array $data, bool $isDiff = false): IDbMySQLiLink {
            $userId = Account::fromSession();

            return $this->insertAsync([
                'user_id' => $userId->id(),
                'action' => $action,
                'ut' => time(),
                'entity' => $entity,
                'entity_id' => $entityId,
                'is_diff' => intval($isDiff),
                'data' => json_encode($data),
            ]);
        }
    }
}
