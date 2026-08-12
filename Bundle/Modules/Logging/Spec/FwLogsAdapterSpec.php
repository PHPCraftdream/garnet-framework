<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\LogsAdapter {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers\FwLogsActionAdapter;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers\FwLogsMailAdapter;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // Empty-rows stubs — no DB access needed since fetchLogs() skips the
    // Account::getAccounts() lookup entirely when selectAll() returns [].
    // -----------------------------------------------------------------------

    class AdapterSpecActionLog extends FwAdminActionLog {
        protected string $tableName = 'test_adapter_spec_action_log';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return [];
        }
    }

    class AdapterSpecMailLog extends FwMailLog {
        protected string $tableName = 'test_adapter_spec_mail_log';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return [];
        }

        protected static function recipientsTable(): FwMailLogRecipients {
            throw new LogicException('Not used');
        }
    }

    /** DbTable's constructor is protected — tables are normally reached via ::get(). */
    function newActionLogStub(): AdapterSpecActionLog {
        return (new ReflectionClass(AdapterSpecActionLog::class))->newInstanceWithoutConstructor();
    }

    function newMailLogStub(): AdapterSpecMailLog {
        return (new ReflectionClass(AdapterSpecMailLog::class))->newInstanceWithoutConstructor();
    }

    // -----------------------------------------------------------------------
    // Specs: FwLogsActionAdapter
    // -----------------------------------------------------------------------

    describe('FwLogsActionAdapter — moderator gate is threaded through run()', function (): void {
        it('run() throws when isModerator=false, without touching the table', function (): void {
            $table = newActionLogStub();

            expect(function () use ($table): void {
                FwLogsActionAdapter::run($table, 100, false);
            })->toThrow(new LogicException('FwLogsActionAdapter::run() requires isModerator to be true.'));
        });

        it('run() succeeds and returns rows when isModerator=true', function (): void {
            $table = newActionLogStub();
            $result = FwLogsActionAdapter::run($table, 100, true);

            expect($result)->toBe([]);
        });
    });

    // -----------------------------------------------------------------------
    // Specs: FwLogsMailAdapter
    // -----------------------------------------------------------------------

    describe('FwLogsMailAdapter — moderator gate is threaded through run()', function (): void {
        it('run() throws when isModerator=false, without touching the table', function (): void {
            $table = newMailLogStub();

            expect(function () use ($table): void {
                FwLogsMailAdapter::run($table, false, 100, false);
            })->toThrow(new LogicException('FwLogsMailAdapter::run() requires isModerator to be true.'));
        });

        it('run() succeeds and returns rows when isModerator=true', function (): void {
            $table = newMailLogStub();
            $result = FwLogsMailAdapter::run($table, false, 100, true);

            expect($result)->toBe([]);
        });
    });
}
