<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\LogsController {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Controllers\FwDashboardLogsController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // In-memory stub for FwAdminActionLog
    // -----------------------------------------------------------------------

    class CtrlSpecActionLog extends FwAdminActionLog {
        protected string $tableName = 'test_ctrl_spec_action_log';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public function insert(array $data, ?Closure $queryCallback = null): false|string {
            $data['id'] = (string)(count($this->rows) + 1);
            $this->rows[] = $data;

            return $data['id'];
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        protected static function recipientsTable(): FwMailLogRecipients {
            throw new LogicException('Not used');
        }
    }

    // -----------------------------------------------------------------------
    // Concrete subclass of the abstract controller, wiring test double
    // -----------------------------------------------------------------------

    class TestLogsController extends FwDashboardLogsController {
        private static CtrlSpecActionLog $logTable;

        private static bool $moderator = true;

        public static function setTable(CtrlSpecActionLog $t): void {
            self::$logTable = $t;
        }

        public static function setModerator(bool $v): void {
            self::$moderator = $v;
        }

        protected static function actionLogTable(): FwAdminActionLog {
            return self::$logTable;
        }

        protected static function gridConfig(): array {
            return ['columns' => ['id', 'actor_id', 'action']];
        }

        protected static function isModerator(): bool {
            return self::$moderator;
        }

        protected static function isOwner(): bool {
            return false;
        }

        protected static function getSideMenu(string $url): array {
            return [];
        }

        protected static function getMainMenu(string $url): array {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function resetDbTableSingletonsCtrl(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function makeCtrlActionLog(): CtrlSpecActionLog {
        resetDbTableSingletonsCtrl();
        $inst = (new ReflectionClass(CtrlSpecActionLog::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass(DbTable::class);
        $items = $ref->getProperty('items');
        $items->setValue(null, [CtrlSpecActionLog::class => $inst]);

        return $inst;
    }

    // Expose fetchLogs() as a callable without booting HTTP stack
    function callFetchLogs(int $limit = 100): array {
        // We access via Reflection since it is protected static
        $ref = new ReflectionClass(TestLogsController::class);
        $method = $ref->getMethod('fetchLogs');

        return $method->invoke(null, $limit);
    }

    // -----------------------------------------------------------------------
    // Specs
    // -----------------------------------------------------------------------

    describe('FwDashboardLogsController::resolveRole()', function (): void {
        it('returns "admin" when IS_ADMIN flag is set', function (): void {
            $account = [Account::IS_ADMIN => 1, Account::IS_OWNER => 0, Account::IS_MODERATOR => 0, 'type' => 'user'];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('admin');
        });

        it('returns "owner" when IS_OWNER flag is set and IS_ADMIN is not', function (): void {
            $account = [Account::IS_ADMIN => 0, Account::IS_OWNER => 1, Account::IS_MODERATOR => 0, 'type' => 'user'];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('owner');
        });

        it('returns "moderator" when IS_MODERATOR flag is set and higher flags are not', function (): void {
            $account = [Account::IS_ADMIN => 0, Account::IS_OWNER => 0, Account::IS_MODERATOR => 1, 'type' => 'user'];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('moderator');
        });

        it('falls back to account type when no elevated flag is set', function (): void {
            $account = [Account::IS_ADMIN => 0, Account::IS_OWNER => 0, Account::IS_MODERATOR => 0, 'type' => 'expert'];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('expert');
        });

        it('returns "user" when all flags are absent and type is not set', function (): void {
            $account = [];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('user');
        });

        it('admin flag takes priority over owner and moderator', function (): void {
            $account = [Account::IS_ADMIN => 1, Account::IS_OWNER => 1, Account::IS_MODERATOR => 1, 'type' => 'user'];
            $ref = new ReflectionClass(TestLogsController::class);
            $m = $ref->getMethod('resolveRole');
            expect($m->invoke(null, $account))->toBe('admin');
        });
    });

    describe('FwDashboardLogsController::fetchLogs() — in-memory path', function (): void {
        beforeEach(function (): void {
            $this->logTable = makeCtrlActionLog();
            TestLogsController::setTable($this->logTable);
            TestLogsController::setModerator(true);
        });

        it('returns an empty array when the log table has no rows', function (): void {
            $logs = callFetchLogs();
            expect($logs)->toBeAn('array');
            expect(count($logs))->toBe(0);
        });

        it('returns rows from the table with actor_name/actor_type/target_name/target_type keys added', function (): void {
            $this->logTable->rows[] = [
                'id' => '1',
                'actor_id' => 0,
                'target_id' => 0,
                'action' => 'IS_APPROVED',
                'actor_login' => 'admin',
                'target_login' => 'user',
                'old_value' => '0',
                'new_value' => '1',
                'created_at' => time(),
            ];
            $logs = callFetchLogs();
            expect(count($logs))->toBe(1);
            expect(array_key_exists('actor_name', $logs[0]))->toBe(true);
            expect(array_key_exists('target_name', $logs[0]))->toBe(true);
            expect(array_key_exists('actor_type', $logs[0]))->toBe(true);
            expect(array_key_exists('target_type', $logs[0]))->toBe(true);
        });

        it('sets actor_name="" and target_name="" when actor/target have no account record', function (): void {
            $this->logTable->rows[] = [
                'id' => '1',
                'actor_id' => 0,
                'target_id' => 0,
                'action' => 'NOTE',
                'actor_login' => 'deleted_admin',
                'target_login' => 'deleted_user',
                'old_value' => '',
                'new_value' => '',
                'created_at' => time(),
            ];
            $logs = callFetchLogs();
            expect($logs[0]['actor_name'])->toBe('');
            expect($logs[0]['target_name'])->toBe('');
        });
    });
}
