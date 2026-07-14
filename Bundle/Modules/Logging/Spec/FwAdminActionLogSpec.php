<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // Concrete in-memory stub for FwAdminActionLog
    // -----------------------------------------------------------------------

    class TestAdminActionLog extends FwAdminActionLog {
        protected string $tableName = 'test_fw_admin_action_log';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        private int $nextId = 1;

        public function insert(array $data, ?Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        protected static function recipientsTable(): \PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients {
            throw new LogicException('Not used in action log');
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function resetDbTableSingletonsAal(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function makeTestAdminActionLog(): TestAdminActionLog {
        resetDbTableSingletonsAal();
        $inst = (new ReflectionClass(TestAdminActionLog::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass(DbTable::class);
        $items = $ref->getProperty('items');
        $items->setAccessible(true);
        $items->setValue(null, [TestAdminActionLog::class => $inst]);

        return $inst;
    }

    // -----------------------------------------------------------------------
    // Specs
    // -----------------------------------------------------------------------

    describe('FwAdminActionLog::writeLog()', function (): void {
        beforeEach(function (): void {
            $this->table = makeTestAdminActionLog();
        });

        it('inserts exactly one row per call', function (): void {
            $this->table->writeLog(1, 'admin', 2, 'user', 'IS_APPROVED', '0', '1');
            expect(count($this->table->insertCalls))->toBe(1);
        });

        it('stores actor_id and actor_login correctly', function (): void {
            $this->table->writeLog(42, 'superadmin', 7, 'bob', 'IS_ADMIN', '0', '1');
            $row = $this->table->insertCalls[0];
            expect($row['actor_id'])->toBe(42);
            expect($row['actor_login'])->toBe('superadmin');
        });

        it('stores target_id and target_login correctly', function (): void {
            $this->table->writeLog(1, 'admin', 99, 'target_user', 'IS_MODERATOR', '0', '1');
            $row = $this->table->insertCalls[0];
            expect($row['target_id'])->toBe(99);
            expect($row['target_login'])->toBe('target_user');
        });

        it('stores action, old_value, and new_value', function (): void {
            $this->table->writeLog(1, 'admin', 2, 'user', 'IS_APPROVED', 'false', 'true');
            $row = $this->table->insertCalls[0];
            expect($row['action'])->toBe('IS_APPROVED');
            expect($row['old_value'])->toBe('false');
            expect($row['new_value'])->toBe('true');
        });

        it('sets created_at to approximately the current unix timestamp', function (): void {
            $before = time();
            $this->table->writeLog(1, 'admin', 2, 'user', 'IS_APPROVED', '0', '1');
            $after = time();
            $ts = $this->table->insertCalls[0]['created_at'];
            expect($ts)->toBeGreaterThan($before - 1);
            expect($ts)->toBeLessThan($after + 1);
        });

        it('inserts multiple independent rows for multiple calls', function (): void {
            $this->table->writeLog(1, 'admin1', 10, 'user10', 'IS_APPROVED', '0', '1');
            $this->table->writeLog(2, 'admin2', 20, 'user20', 'IS_DISABLED', '1', '0');
            expect(count($this->table->insertCalls))->toBe(2);
            expect($this->table->insertCalls[0]['actor_id'])->toBe(1);
            expect($this->table->insertCalls[1]['actor_id'])->toBe(2);
        });

        it('allows empty string values for old_value and new_value', function (): void {
            $this->table->writeLog(1, 'admin', 2, 'user', 'NOTE', '', '');
            $row = $this->table->insertCalls[0];
            expect($row['old_value'])->toBe('');
            expect($row['new_value'])->toBe('');
        });

        it('action field accepts any string up to 64 chars', function (): void {
            $longAction = str_repeat('x', 64);
            $this->table->writeLog(1, 'admin', 2, 'user', $longAction, '0', '1');
            expect($this->table->insertCalls[0]['action'])->toBe($longAction);
        });
    });
}
