<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\Mailer {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\FwAppMailer;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLogRecipients;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IMailer;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;
    use RuntimeException;
    use Throwable;

    // -----------------------------------------------------------------------
    // In-memory MailLog table
    // -----------------------------------------------------------------------

    class MailerSpecMailLogRecipients extends FwMailLogRecipients {
        protected string $tableName = 'test_mailer_spec_recipients';

        public static function init(): ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }
    }

    class MailerSpecMailLog extends FwMailLog {
        protected string $tableName = 'test_mailer_spec_mail_log';

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
            throw new LogicException('recipientsTable() not used in mailer spec');
        }
    }

    // -----------------------------------------------------------------------
    // Concrete FwAppMailer subclass for testing
    // -----------------------------------------------------------------------

    class TestableAppMailer extends FwAppMailer {
        private MailerSpecMailLog $logTable;

        public function __construct(IMailer $inner, MailerSpecMailLog $logTable) {
            parent::__construct($inner);
            $this->logTable = $logTable;
        }

        protected function mailLogTable(): DbTable {
            return $this->logTable;
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function resetDbTableSingletonsMailer(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function resetIniConfigMailer(): void {
        $ref = new ReflectionClass(IniConfig::class);

        $initParams = $ref->getProperty('initParams');
        $initParams->setAccessible(true);
        $initParams->setValue(null, []);

        $items = $ref->getProperty('items');
        $items->setAccessible(true);
        $items->setValue(null, []);
    }

    function makeAppIniMailer(string $env = 'prod'): string {
        $file = tempnam(sys_get_temp_dir(), 'garnet_mailer_');
        file_put_contents($file, "env={$env}\n");
        resetIniConfigMailer();
        IniConfig::defineAppIni($file);

        return $file;
    }

    function makeMailLog(): MailerSpecMailLog {
        resetDbTableSingletonsMailer();
        $inst = (new ReflectionClass(MailerSpecMailLog::class))->newInstanceWithoutConstructor();
        $ref = new ReflectionClass(DbTable::class);
        $items = $ref->getProperty('items');
        $items->setAccessible(true);
        $items->setValue(null, [MailerSpecMailLog::class => $inst]);

        return $inst;
    }

    // -----------------------------------------------------------------------
    // Specs
    // -----------------------------------------------------------------------

    describe('FwAppMailer', function (): void {
        // These specs wipe IniConfig's static state (resetIniConfigMailer)
        // to swap in throwaway app.ini fixtures. Without restoring it, the
        // global ENV_DB/ENV_APP/ENV_EMAIL definitions set by the kahlan
        // bootstrap (TestsInit/init.php) stay cleared, and later specs that
        // call IniConfig::db() (e.g. Fw*Table init() schema specs) blow up
        // with "Env not found: ENV_DB". Re-assert the bootstrap config once
        // this describe finishes.
        afterAll(function (): void {
            resetIniConfigMailer();
            $cfg = __DIR__ . '/../../../../TestsInit/TestConfig/';
            IniConfig::defineAppIni($cfg . 'app.ini');
            IniConfig::defineDbIni($cfg . 'db.ini');
            IniConfig::defineEmailIni($cfg . 'email.ini');
        });

        describe('detectMailType()', function (): void {
            beforeEach(function (): void {
                $this->logTable = makeMailLog();
                $innerMailer = new class() implements IMailer {
                    public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
                    }
                };
                $this->mailer = new TestableAppMailer($innerMailer, $this->logTable);
                $this->iniFile = makeAppIniMailer('prod');
            });

            afterEach(function (): void {
                if (file_exists($this->iniFile)) {
                    unlink($this->iniFile);
                }
            });

            it('detects auth_code type from subject containing "авториз"', function (): void {
                $this->mailer->sendHtmlMail('a@example.com', 'Авторизация на сайте', '<p>code</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['mail_type'])->toBe('auth_code');
            });

            it('detects auth_code type from subject containing "auth" (case-insensitive)', function (): void {
                $this->mailer->sendHtmlMail('a@example.com', 'Your Auth Code', '<p>code</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['mail_type'])->toBe('auth_code');
            });

            it('falls back to "general" for unrecognised subjects', function (): void {
                $this->mailer->sendHtmlMail('a@example.com', 'Welcome to the platform', '<p>Hi</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['mail_type'])->toBe('general');
            });
        });

        describe('sendHtmlMail() — prod mode, normal address', function (): void {
            beforeEach(function (): void {
                $this->logTable = makeMailLog();
                $this->innerCalled = false;
                $logTableRef = $this->logTable;
                $innerCalledRef = &$this->innerCalled;

                $inner = new class($innerCalledRef) implements IMailer {
                    private bool $wasCalled = false;

                    public function __construct(private bool &$flag) {
                    }

                    public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
                        $this->flag = true;
                    }
                };
                $this->mailer = new TestableAppMailer($inner, $this->logTable);
                $this->iniFile = makeAppIniMailer('prod');
            });

            afterEach(function (): void {
                if (file_exists($this->iniFile)) {
                    unlink($this->iniFile);
                }
            });

            it('logs status=sent when inner mailer succeeds', function (): void {
                $this->mailer->sendHtmlMail('user@example.com', 'Test', '<p>body</p>');
                expect($this->logTable->insertCalls[0]['status'])->toBe('sent');
            });

            it('logs recipient_email, subject, body_html on success', function (): void {
                $this->mailer->sendHtmlMail('user@example.com', 'My Subject', '<b>Hello</b>');
                $row = $this->logTable->insertCalls[0];
                expect($row['recipient_email'])->toBe('user@example.com');
                expect($row['subject'])->toBe('My Subject');
                expect($row['body_html'])->toBe('<b>Hello</b>');
            });

            it('logs created_at as a recent unix timestamp', function (): void {
                $before = time();
                $this->mailer->sendHtmlMail('user@example.com', 'Test', '<p>b</p>');
                $after = time();
                $ts = $this->logTable->insertCalls[0]['created_at'];
                expect($ts)->toBeGreaterThan($before - 1);
                expect($ts)->toBeLessThan($after + 1);
            });

            it('logs error_log=null on success', function (): void {
                $this->mailer->sendHtmlMail('user@example.com', 'Test', '<p>b</p>');
                expect($this->logTable->insertCalls[0]['error_log'])->toBeNull();
            });
        });

        describe('sendHtmlMail() — prod mode, inner throws', function (): void {
            beforeEach(function (): void {
                $this->logTable = makeMailLog();
                $inner = new class() implements IMailer {
                    public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
                        throw new RuntimeException('SMTP error: connection refused');
                    }
                };
                $this->mailer = new TestableAppMailer($inner, $this->logTable);
                $this->iniFile = makeAppIniMailer('prod');
            });

            afterEach(function (): void {
                if (file_exists($this->iniFile)) {
                    unlink($this->iniFile);
                }
            });

            it('logs status=failed when inner mailer throws', function (): void {
                try {
                    $this->mailer->sendHtmlMail('fail@example.com', 'Test', '<p>b</p>');
                } catch (Throwable) {
                }
                expect($this->logTable->insertCalls[0]['status'])->toBe('failed');
            });

            it('re-throws the exception after logging', function (): void {
                $threw = false;

                try {
                    $this->mailer->sendHtmlMail('fail@example.com', 'Test', '<p>b</p>');
                } catch (RuntimeException $e) {
                    $threw = true;
                    expect($e->getMessage())->toContain('SMTP error');
                }
                expect($threw)->toBe(true);
            });

            it('logs the error message in error_log', function (): void {
                try {
                    $this->mailer->sendHtmlMail('fail@example.com', 'Test', '<p>b</p>');
                } catch (Throwable) {
                }
                $row = $this->logTable->insertCalls[0];
                expect($row['error_log'])->toContain('SMTP error');
            });
        });

        describe('sendHtmlMail() — dev mode, .test address', function (): void {
            beforeEach(function (): void {
                $this->logTable = makeMailLog();
                $this->innerCalled = false;
                $flag = &$this->innerCalled;
                $inner = new class($flag) implements IMailer {
                    public function __construct(private bool &$flag) {
                    }

                    public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
                        $this->flag = true;
                    }
                };
                $this->mailer = new TestableAppMailer($inner, $this->logTable);
                $this->iniFile = makeAppIniMailer('dev');
            });

            afterEach(function (): void {
                if (file_exists($this->iniFile)) {
                    unlink($this->iniFile);
                }
            });

            it('logs status=skipped_dev without calling inner mailer', function (): void {
                $this->mailer->sendHtmlMail('dev@local.test', 'Code', '<p>123</p>');
                expect($this->logTable->insertCalls[0]['status'])->toBe('skipped_dev');
                expect($this->innerCalled)->toBe(false);
            });

            it('still logs recipient, subject, body on dev skip', function (): void {
                $this->mailer->sendHtmlMail('dev@local.test', 'My Code', '<p>999</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['recipient_email'])->toBe('dev@local.test');
                expect($row['subject'])->toBe('My Code');
                expect($row['body_html'])->toBe('<p>999</p>');
            });
        });

        describe('setNextMeta()', function (): void {
            beforeEach(function (): void {
                $this->logTable = makeMailLog();
                $inner = new class() implements IMailer {
                    public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
                    }
                };
                $this->mailer = new TestableAppMailer($inner, $this->logTable);
                $this->iniFile = makeAppIniMailer('prod');
                // Reset static meta
                FwAppMailer::setNextMeta([]);
            });

            afterEach(function (): void {
                FwAppMailer::setNextMeta([]);

                if (file_exists($this->iniFile)) {
                    unlink($this->iniFile);
                }
            });

            it('attaches meta JSON to the log row when set before sending', function (): void {
                FwAppMailer::setNextMeta(['booking_id' => 42]);
                $this->mailer->sendHtmlMail('user@example.com', 'Booking', '<p>b</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['meta'])->toContain('42');
            });

            it('stores null meta when nothing was set', function (): void {
                $this->mailer->sendHtmlMail('user@example.com', 'Plain', '<p>p</p>');
                $row = $this->logTable->insertCalls[0];
                expect($row['meta'])->toBeNull();
            });

            it('meta is consumed once — not reused by a second send', function (): void {
                FwAppMailer::setNextMeta(['slot_id' => 7]);
                $this->mailer->sendHtmlMail('a@example.com', 'First', '<p>1</p>');
                $this->mailer->sendHtmlMail('b@example.com', 'Second', '<p>2</p>');
                // First has meta, second must not
                expect($this->logTable->insertCalls[0]['meta'])->not->toBeNull();
                expect($this->logTable->insertCalls[1]['meta'])->toBeNull();
            });
        });
    });
}
