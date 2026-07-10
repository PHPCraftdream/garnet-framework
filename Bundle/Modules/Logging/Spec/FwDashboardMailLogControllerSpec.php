<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\MailLogCtrl {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Controllers\FwDashboardMailLogController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // In-memory MailLog stub
    // -----------------------------------------------------------------------

    class MailCtrlSpecRecipients extends FwMailLogRecipients {
        protected string $tableName = 'test_mail_ctrl_spec_recipients';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('not in tests');
        }
    }

    class MailCtrlSpecMailLog extends FwMailLog {
        protected string $tableName = 'test_mail_ctrl_spec_mail_log';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('not in tests');
        }

        public array $rows = [];

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $data['id'] = (string)(count($this->rows) + 1);
            $this->rows[] = $data;

            return $data['id'];
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        protected static function recipientsTable(): FwMailLogRecipients {
            throw new LogicException('Not used in spec');
        }
    }

    // -----------------------------------------------------------------------
    // Concrete controller subclass
    // -----------------------------------------------------------------------

    class TestMailLogController extends FwDashboardMailLogController {
        private static MailCtrlSpecMailLog $logTable;

        private static bool $adminFlag = false;

        private static bool $modFlag = true;

        public static function setTable(MailCtrlSpecMailLog $t): void {
            self::$logTable = $t;
        }

        public static function setIsAdmin(bool $v): void {
            self::$adminFlag = $v;
        }

        public static function setIsModerator(bool $v): void {
            self::$modFlag = $v;
        }

        protected static function mailLogTable(): FwMailLog {
            return self::$logTable;
        }

        protected static function isAdmin(): bool {
            return self::$adminFlag;
        }

        protected static function isModerator(): bool {
            return self::$modFlag;
        }

        protected static function isOwner(): bool {
            return false;
        }

        protected static function gridConfig(): array {
            return [];
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

    function resetDbTableSingletonsMailCtrl(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function makeMailCtrlLog(): MailCtrlSpecMailLog {
        resetDbTableSingletonsMailCtrl();
        $inst = (new ReflectionClass(MailCtrlSpecMailLog::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass(DbTable::class);
        $items = $ref->getProperty('items');
        $items->setAccessible(true);
        $items->setValue(null, [MailCtrlSpecMailLog::class => $inst]);

        return $inst;
    }

    function callMailFetchLogs(int $limit = 200): array {
        $ref = new ReflectionClass(TestMailLogController::class);
        $method = $ref->getMethod('fetchLogs');
        $method->setAccessible(true);

        return $method->invoke(null, $limit);
    }

    function seedMailRow(MailCtrlSpecMailLog $table, string $mailType = 'auth_code', string $status = 'sent'): void {
        // Use account_id=0 so the Account lookup is skipped (filter: (int)$id > 0)
        $table->rows[] = [
            'id' => (string)(count($table->rows) + 1),
            'account_id' => 0,
            'recipient_email' => 'user@example.com',
            'mail_type' => $mailType,
            'subject' => 'Test subject',
            'body_html' => '<p>Secret code: 123456</p>',
            'meta' => json_encode(['booking_id' => 99]),
            'status' => $status,
            'error_log' => null,
            'created_at' => time(),
        ];
    }

    // -----------------------------------------------------------------------
    // Specs
    // -----------------------------------------------------------------------

    describe('FwDashboardMailLogController::fetchLogs() — permission gating', function (): void {
        beforeEach(function (): void {
            $this->table = makeMailCtrlLog();
            TestMailLogController::setTable($this->table);
        });

        it('non-admin: body_html is stripped from log rows', function (): void {
            TestMailLogController::setIsAdmin(false);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();
            expect(array_key_exists('body_html', $logs[0]))->toBe(false);
        });

        it('non-admin: meta is stripped from log rows', function (): void {
            TestMailLogController::setIsAdmin(false);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();
            expect(array_key_exists('meta', $logs[0]))->toBe(false);
        });

        it('admin: body_html is present in log rows', function (): void {
            TestMailLogController::setIsAdmin(true);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();
            expect(array_key_exists('body_html', $logs[0]))->toBe(true);
            expect($logs[0]['body_html'])->toContain('Secret code');
        });

        it('admin: meta is present in log rows', function (): void {
            TestMailLogController::setIsAdmin(true);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();
            expect(array_key_exists('meta', $logs[0]))->toBe(true);
        });

        it('adds account_name and account_login keys to every row', function (): void {
            TestMailLogController::setIsAdmin(false);
            seedMailRow($this->table); // account_id=0 → no account lookup
            $logs = callMailFetchLogs();
            expect(array_key_exists('account_name', $logs[0]))->toBe(true);
            expect(array_key_exists('account_login', $logs[0]))->toBe(true);
        });

        it('account_name and account_login default to empty string for unknown account_id', function (): void {
            TestMailLogController::setIsAdmin(false);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();
            expect($logs[0]['account_name'])->toBe('');
            expect($logs[0]['account_login'])->toBe('');
        });

        it('returns empty array when table has no rows', function (): void {
            TestMailLogController::setIsAdmin(false);
            $logs = callMailFetchLogs();
            expect($logs)->toBe([]);
        });

        it('returns all rows up to limit when rows exist', function (): void {
            TestMailLogController::setIsAdmin(false);

            for ($i = 1; $i <= 5; $i++) {
                seedMailRow($this->table, 'general', 'sent');
            }
            $logs = callMailFetchLogs(5);
            expect(count($logs))->toBe(5);
        });

        it('strips body_html from all rows for non-admin, not just first', function (): void {
            TestMailLogController::setIsAdmin(false);
            seedMailRow($this->table);
            seedMailRow($this->table);
            $logs = callMailFetchLogs();

            foreach ($logs as $log) {
                expect(array_key_exists('body_html', $log))->toBe(false);
            }
        });
    });
}
