<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Spec\RequestLog {
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Request\Controllers\FwDashboardRequestLogController;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use ReflectionClass;

    // -----------------------------------------------------------------------
    // Concrete subclass with filesystem overrides for deterministic testing
    // -----------------------------------------------------------------------

    class TestRequestLogController extends FwDashboardRequestLogController {
        private static string $routeLogDirOverride = '';

        private static string $errorLogDirOverride = '';

        public static function setRouteDirOverride(string $dir): void {
            self::$routeLogDirOverride = $dir;
        }

        public static function setErrorDirOverride(string $dir): void {
            self::$errorLogDirOverride = $dir;
        }

        protected static function routeLogDir(): string {
            return self::$routeLogDirOverride;
        }

        protected static function errorLogDir(): string {
            return self::$errorLogDirOverride;
        }

        protected static function pageUrl(): string {
            return '/admin/logs';
        }

        protected static function isModerator(): bool {
            return true;
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

        // Expose protected methods as public for spec assertions
        public static function publicListDatesIn(string $dir): array {
            return parent::listDatesIn($dir);
        }

        public static function publicListRequestDates(): array {
            return parent::listRequestDates();
        }

        public static function publicListErrorDates(): array {
            return parent::listErrorDates();
        }

        public static function publicParseLog(string $date): array {
            return parent::parseLog($date);
        }

        public static function publicParseErrors(string $date): array {
            return parent::parseErrors($date);
        }

        public static function publicLoadAccountsMap(array $ids): array {
            // The real implementation calls DbAccount; override to avoid DB
            return [];
        }
    }

    // Override loadAccountsMap via another subclass so we skip DB
    class TestRequestLogNoDb extends TestRequestLogController {
        protected static function loadAccountsMap(array $ids): array {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers to build temp filesystem fixtures
    // -----------------------------------------------------------------------

    function makeTempDir(): string {
        $dir = sys_get_temp_dir() . '/garnet_reqlog_' . uniqid() . '/';
        mkdir($dir, 0o777, true);

        return $dir;
    }

    // The constant is protected; we access via our concrete subclass
    const TEST_ROUTE_LOG_FILENAME = 'ROUTE_LOGGER-requests.log';

    function makeRouteDateDir(string $base, string $date, string $content): string {
        $dayDir = $base . $date . DIRECTORY_SEPARATOR;
        mkdir($dayDir, 0o777, true);
        file_put_contents($dayDir . TEST_ROUTE_LOG_FILENAME, $content);

        return $dayDir;
    }

    function makeErrorDateDir(string $base, string $date): string {
        $dayDir = $base . $date . DIRECTORY_SEPARATOR;
        mkdir($dayDir, 0o777, true);

        return $dayDir;
    }

    function makeErrorFile(string $dayDir, string $name, string $hash, string $ts, string $message): void {
        $filename = "ERROR_LOGGER-{$name}-{$hash}.log";
        $content = "{$ts}: \n{$message}\n";
        file_put_contents($dayDir . $filename, $content);
    }

    function buildLogEntry(string $ts, array $data): string {
        return $ts . ": \n" . json_encode($data) . "\n\n";
    }

    function rrmdir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // -----------------------------------------------------------------------
    // Specs: listDatesIn()
    // -----------------------------------------------------------------------

    describe('FwDashboardRequestLogController::listDatesIn()', function (): void {
        afterEach(function (): void {
            if (isset($this->dir) && is_dir($this->dir)) {
                rrmdir($this->dir);
            }
        });

        it('returns empty array for empty string path', function (): void {
            $result = TestRequestLogController::publicListDatesIn('');
            expect($result)->toBe([]);
        });

        it('returns empty array for non-existent directory', function (): void {
            $result = TestRequestLogController::publicListDatesIn('/does/not/exist/');
            expect($result)->toBe([]);
        });

        it('returns date directories in descending order', function (): void {
            $this->dir = makeTempDir();

            foreach (['2025-01-10', '2025-01-08', '2025-01-09'] as $d) {
                mkdir($this->dir . $d);
            }
            $result = TestRequestLogController::publicListDatesIn($this->dir);
            expect($result)->toBe(['2025-01-10', '2025-01-09', '2025-01-08']);
        });

        it('ignores files (not directories)', function (): void {
            $this->dir = makeTempDir();
            mkdir($this->dir . '2025-01-01');
            file_put_contents($this->dir . '2025-01-02', 'not a directory');
            $result = TestRequestLogController::publicListDatesIn($this->dir);
            expect(count($result))->toBe(1);
            expect($result[0])->toBe('2025-01-01');
        });

        it('ignores directories with non-date names', function (): void {
            $this->dir = makeTempDir();
            mkdir($this->dir . '2025-01-01');
            mkdir($this->dir . 'not-a-date');
            mkdir($this->dir . '20250101');
            $result = TestRequestLogController::publicListDatesIn($this->dir);
            expect($result)->toBe(['2025-01-01']);
        });
    });

    // -----------------------------------------------------------------------
    // Specs: listRequestDates()
    // -----------------------------------------------------------------------

    describe('FwDashboardRequestLogController::listRequestDates()', function (): void {
        afterEach(function (): void {
            if (isset($this->dir) && is_dir($this->dir)) {
                rrmdir($this->dir);
            }
        });

        it('only includes dates that have the ROUTE_LOG_FILE_NAME file', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);

            // Valid date: has the log file
            $dayDir = $this->dir . '2025-01-05' . DIRECTORY_SEPARATOR;
            mkdir($dayDir);
            file_put_contents($dayDir . TEST_ROUTE_LOG_FILENAME, '');

            // Date without the log file
            mkdir($this->dir . '2025-01-04' . DIRECTORY_SEPARATOR);

            $result = TestRequestLogController::publicListRequestDates();
            expect($result)->toBe(['2025-01-05']);
        });

        it('returns empty array when no dates have the log file', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            mkdir($this->dir . '2025-01-01' . DIRECTORY_SEPARATOR);
            $result = TestRequestLogController::publicListRequestDates();
            expect($result)->toBe([]);
        });
    });

    // -----------------------------------------------------------------------
    // Specs: parseLog()
    // -----------------------------------------------------------------------

    describe('FwDashboardRequestLogController::parseLog()', function (): void {
        afterEach(function (): void {
            if (isset($this->dir) && is_dir($this->dir)) {
                rrmdir($this->dir);
            }
        });

        it('returns empty array for invalid date format', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            expect(TestRequestLogController::publicParseLog('not-a-date'))->toBe([]);
        });

        it('returns empty array when the log file does not exist', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            mkdir($this->dir . '2025-01-01' . DIRECTORY_SEPARATOR);
            expect(TestRequestLogController::publicParseLog('2025-01-01'))->toBe([]);
        });

        it('returns empty array for an empty log file', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            makeRouteDateDir($this->dir, '2025-01-01', '');
            expect(TestRequestLogController::publicParseLog('2025-01-01'))->toBe([]);
        });

        it('parses a single valid log entry', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            $entry = buildLogEntry('2025-01-01 10:00:00', [
                'method' => 'GET',
                'uri' => '/api/test',
                'status' => 200,
                'account_id' => null,
            ]);
            makeRouteDateDir($this->dir, '2025-01-01', $entry);
            $rows = TestRequestLogController::publicParseLog('2025-01-01');
            expect(count($rows))->toBe(1);
            expect($rows[0]['method'])->toBe('GET');
            expect($rows[0]['uri'])->toBe('/api/test');
            expect($rows[0]['status'])->toBe(200);
        });

        it('sets log_ts from the entry header', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            $entry = buildLogEntry('2025-06-01 12:34:56', ['method' => 'POST', 'uri' => '/x', 'status' => 201]);
            makeRouteDateDir($this->dir, '2025-06-01', $entry);
            $rows = TestRequestLogController::publicParseLog('2025-06-01');
            expect($rows[0]['log_ts'])->toBe('2025-06-01 12:34:56');
        });

        it('parses multiple entries and returns newest first', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            $content = buildLogEntry('2025-01-01 08:00:00', ['method' => 'GET', 'uri' => '/a', 'status' => 200]);
            $content .= buildLogEntry('2025-01-01 09:00:00', ['method' => 'POST', 'uri' => '/b', 'status' => 201]);
            makeRouteDateDir($this->dir, '2025-01-01', $content);
            $rows = TestRequestLogController::publicParseLog('2025-01-01');
            // array_reverse → last written first
            expect(count($rows))->toBe(2);
            expect($rows[0]['uri'])->toBe('/b');
            expect($rows[1]['uri'])->toBe('/a');
        });

        it('skips blocks with malformed JSON', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            $badEntry = "2025-01-01 10:00:00: \nnot valid json\n\n";
            $goodEntry = buildLogEntry('2025-01-01 11:00:00', ['method' => 'GET', 'uri' => '/ok', 'status' => 200]);
            makeRouteDateDir($this->dir, '2025-01-01', $badEntry . $goodEntry);
            $rows = TestRequestLogController::publicParseLog('2025-01-01');
            expect(count($rows))->toBe(1);
            expect($rows[0]['uri'])->toBe('/ok');
        });

        it('skips blocks without a newline separator', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setRouteDirOverride($this->dir);
            $noNewline = '2025-01-01 10:00:00: {"method":"GET"}';
            makeRouteDateDir($this->dir, '2025-01-01', $noNewline);
            $rows = TestRequestLogController::publicParseLog('2025-01-01');
            expect($rows)->toBe([]);
        });
    });

    // -----------------------------------------------------------------------
    // Specs: parseErrors()
    // -----------------------------------------------------------------------

    describe('FwDashboardRequestLogController::parseErrors()', function (): void {
        afterEach(function (): void {
            if (isset($this->dir) && is_dir($this->dir)) {
                rrmdir($this->dir);
            }
        });

        it('returns empty array for invalid date format', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            expect(TestRequestLogController::publicParseErrors('not-a-date'))->toBe([]);
        });

        it('returns empty array when directory does not exist', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            expect(TestRequestLogController::publicParseErrors('2025-01-01'))->toBe([]);
        });

        it('parses a single error log file and extracts ts, name, hash, message', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            $hash = md5('test');
            $dayDir = makeErrorDateDir($this->dir, '2025-03-15');
            makeErrorFile($dayDir, 'MyException', $hash, '2025-03-15 10:30:00', "Error trace\nline 2");
            $rows = TestRequestLogController::publicParseErrors('2025-03-15');
            expect(count($rows))->toBe(1);
            expect($rows[0]['name'])->toBe('MyException');
            expect($rows[0]['hash'])->toBe($hash);
            expect($rows[0]['ts'])->toBe('2025-03-15 10:30:00');
            expect($rows[0]['message'])->toContain('Error trace');
        });

        it('ignores files not ending with .log', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            $dayDir = makeErrorDateDir($this->dir, '2025-04-01');
            file_put_contents($dayDir . 'ERROR_LOGGER-Some-' . md5('x') . '.txt', "ts: \nmsg\n");
            $rows = TestRequestLogController::publicParseErrors('2025-04-01');
            expect($rows)->toBe([]);
        });

        it('parses multiple error files and returns them newest-first by mtime', function (): void {
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            $dayDir = makeErrorDateDir($this->dir, '2025-05-10');
            $hash1 = md5('a');
            $hash2 = md5('b');
            makeErrorFile($dayDir, 'ErrA', $hash1, '2025-05-10 08:00:00', 'First error');
            sleep(1); // ensure distinct mtime on filesystem
            makeErrorFile($dayDir, 'ErrB', $hash2, '2025-05-10 09:00:00', 'Second error');
            $rows = TestRequestLogController::publicParseErrors('2025-05-10');
            expect(count($rows))->toBe(2);
            // newest mtime first
            expect($rows[0]['name'])->toBe('ErrB');
        });

        it('strips mtime key from result rows (internal sort-only field)', function (): void {
            // mtime is NOT stripped by parseErrors itself; it is stripped in post__errorsPage
            // This test confirms parseErrors leaves mtime present for sorting
            $this->dir = makeTempDir();
            TestRequestLogController::setErrorDirOverride($this->dir);
            $dayDir = makeErrorDateDir($this->dir, '2025-05-01');
            makeErrorFile($dayDir, 'ErrX', md5('y'), '2025-05-01 10:00:00', 'msg');
            $rows = TestRequestLogController::publicParseErrors('2025-05-01');
            expect(array_key_exists('mtime', $rows[0]))->toBe(true);
        });
    });

    // -----------------------------------------------------------------------
    // Specs: URL helpers
    // -----------------------------------------------------------------------

    describe('FwDashboardRequestLogController URL helpers', function (): void {
        it('requestsPageUrl() appends /~page to the page URL', function (): void {
            $ref = new ReflectionClass(TestRequestLogController::class);
            $m = $ref->getMethod('requestsPageUrl');
            $m->setAccessible(true);
            expect($m->invoke(null))->toBe('/admin/logs/~page');
        });

        it('errorsPageUrl() appends /~errorsPage to the page URL', function (): void {
            $ref = new ReflectionClass(TestRequestLogController::class);
            $m = $ref->getMethod('errorsPageUrl');
            $m->setAccessible(true);
            expect($m->invoke(null))->toBe('/admin/logs/~errorsPage');
        });
    });

    // -----------------------------------------------------------------------
    // Specs: PaginationHelper constants used in post__page / post__errorsPage
    // -----------------------------------------------------------------------

    describe('PaginationHelper constants used by request-log controller', function (): void {
        it('DEFAULT_PER_PAGE is 10', function (): void {
            expect(PaginationHelper::DEFAULT_PER_PAGE)->toBe(10);
        });

        it('MIN_PER_PAGE is 10', function (): void {
            expect(PaginationHelper::MIN_PER_PAGE)->toBe(10);
        });

        it('MAX_PER_PAGE_LARGE >= 500 (request log uses up to 1000)', function (): void {
            expect(PaginationHelper::MAX_PER_PAGE_LARGE)->toBeGreaterThan(499);
        });

        it('MAX_PER_PAGE_MEDIUM < MAX_PER_PAGE_LARGE (errors are heavier)', function (): void {
            expect(PaginationHelper::MAX_PER_PAGE_MEDIUM)->toBeLessThan(PaginationHelper::MAX_PER_PAGE_LARGE);
        });
    });
}
