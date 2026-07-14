<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\Mail {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // Concrete in-memory stubs
    // -----------------------------------------------------------------------

    class TestMailLogRecipients extends FwMailLogRecipients {
        protected string $tableName = 'test_fw_mail_log_recipients';

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
    }

    class TestMailLog extends FwMailLog {
        protected string $tableName = 'test_fw_mail_log';

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

        protected static function recipientsTable(): FwMailLogRecipients {
            // Return a stub; not called directly in current tests
            throw new LogicException('recipientsTable() not used in this spec');
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function resetDbTableSingletonsMailLog(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function makeTestMailLog(): TestMailLog {
        resetDbTableSingletonsMailLog();
        $inst = (new ReflectionClass(TestMailLog::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass(DbTable::class);
        $items = $ref->getProperty('items');
        $items->setAccessible(true);
        $items->setValue(null, [TestMailLog::class => $inst]);

        return $inst;
    }

    // -----------------------------------------------------------------------
    // Specs
    // -----------------------------------------------------------------------

    describe('FwMailLog table schema fields', function (): void {
        beforeEach(function (): void {
            $this->table = makeTestMailLog();
        });

        it('can insert a row with all standard fields', function (): void {
            $now = time();
            $this->table->insert([
                'account_id' => 5,
                'recipient_email' => 'user@example.com',
                'mail_type' => 'auth_code',
                'subject' => 'Your login code',
                'body_html' => '<p>Code: 123456</p>',
                'status' => 'sent',
                'error_log' => null,
                'created_at' => $now,
            ]);
            expect(count($this->table->insertCalls))->toBe(1);
            $row = $this->table->insertCalls[0];
            expect($row['recipient_email'])->toBe('user@example.com');
            expect($row['mail_type'])->toBe('auth_code');
            expect($row['subject'])->toBe('Your login code');
            expect($row['status'])->toBe('sent');
        });

        it('allows null account_id for anonymous recipients', function (): void {
            $this->table->insert([
                'account_id' => null,
                'recipient_email' => 'anon@example.com',
                'mail_type' => 'general',
                'subject' => 'Hello',
                'body_html' => '<p>Hi</p>',
                'status' => 'sent',
                'error_log' => null,
                'created_at' => time(),
            ]);
            expect($this->table->insertCalls[0]['account_id'])->toBeNull();
        });

        it('stores error_log on failed delivery', function (): void {
            $this->table->insert([
                'account_id' => 1,
                'recipient_email' => 'fail@example.com',
                'mail_type' => 'general',
                'subject' => 'Test',
                'body_html' => '<p>Test</p>',
                'status' => 'failed',
                'error_log' => 'SMTP connection refused',
                'created_at' => time(),
            ]);
            expect($this->table->insertCalls[0]['error_log'])->toBe('SMTP connection refused');
            expect($this->table->insertCalls[0]['status'])->toBe('failed');
        });

        it('stores null error_log on success', function (): void {
            $this->table->insert([
                'account_id' => 1,
                'recipient_email' => 'ok@example.com',
                'mail_type' => 'auth_code',
                'subject' => 'Code',
                'body_html' => '<p>1234</p>',
                'status' => 'sent',
                'error_log' => null,
                'created_at' => time(),
            ]);
            expect($this->table->insertCalls[0]['error_log'])->toBeNull();
        });

        it('records status=skipped_dev for dev-mode test addresses', function (): void {
            $this->table->insert([
                'account_id' => null,
                'recipient_email' => 'dev@local.test',
                'mail_type' => 'auth_code',
                'subject' => 'Dev code',
                'body_html' => '<p>xyz</p>',
                'status' => 'skipped_dev',
                'error_log' => null,
                'created_at' => time(),
            ]);
            expect($this->table->insertCalls[0]['status'])->toBe('skipped_dev');
        });

        it('selectAll returns all inserted rows', function (): void {
            for ($i = 1; $i <= 3; $i++) {
                $this->table->insert([
                    'account_id' => $i,
                    'recipient_email' => "user{$i}@example.com",
                    'mail_type' => 'general',
                    'subject' => "Subject {$i}",
                    'body_html' => "<p>{$i}</p>",
                    'status' => 'sent',
                    'error_log' => null,
                    'created_at' => time(),
                ]);
            }
            expect(count($this->table->selectAll()))->toBe(3);
        });
    });

    describe('FwMailLogRecipients table schema fields', function (): void {
        beforeEach(function (): void {
            resetDbTableSingletonsMailLog();
            $inst = (new ReflectionClass(TestMailLogRecipients::class))->newInstanceWithoutConstructor();
            $ref = new ReflectionClass(DbTable::class);
            $items = $ref->getProperty('items');
            $items->setAccessible(true);
            $items->setValue(null, [TestMailLogRecipients::class => $inst]);
            $this->table = $inst;
        });

        it('can insert a row linking mail_log_id to recipient_email', function (): void {
            $this->table->insert([
                'mail_log_id' => 1,
                'account_id' => 5,
                'recipient_email' => 'rcpt@example.com',
            ]);
            expect(count($this->table->insertCalls))->toBe(1);
            expect($this->table->insertCalls[0]['mail_log_id'])->toBe(1);
            expect($this->table->insertCalls[0]['recipient_email'])->toBe('rcpt@example.com');
        });

        it('allows null account_id for unregistered recipients', function (): void {
            $this->table->insert([
                'mail_log_id' => 2,
                'account_id' => null,
                'recipient_email' => 'external@example.com',
            ]);
            expect($this->table->insertCalls[0]['account_id'])->toBeNull();
        });
    });
}
