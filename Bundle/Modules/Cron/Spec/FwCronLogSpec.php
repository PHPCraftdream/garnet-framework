<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Cron\Spec {
    use Closure;
    use Exception;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Cron\Tables\FwCronLog;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;
    use RuntimeException;
    use Throwable;

    // ---------------------------------------------------------------------------
    // In-memory stub table
    // ---------------------------------------------------------------------------

    class TestCronLog extends FwCronLog {
        protected string $tableName = 'fw_cron_log_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        public array $updateCalls = [];

        private int $nextId = 1;

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function updateById(array $updateData, int|string|array $id, callable $callback = null): bool {
            $id = (string)$id;
            $this->updateCalls[] = ['data' => $updateData, 'id' => $id];

            if (isset($this->rows[$id])) {
                $this->rows[$id] = array_merge($this->rows[$id], $updateData);
            }

            return true;
        }

        public function selectById(int|string $id, Closure $queryCallback = null): ?array {
            return $this->rows[(string)$id] ?? null;
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetCronSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    function setupCronTable(): TestCronLog {
        resetCronSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');
        $itemsProp->setAccessible(true);

        $inst = (new ReflectionClass(TestCronLog::class))->newInstanceWithoutConstructor();
        $itemsProp->setValue([TestCronLog::class => $inst]);

        return $inst;
    }

    // ---------------------------------------------------------------------------
    // Simulated "runWithLogging" logic — mirrors what FwCronService does when it
    // wraps a task callback with the log table.
    // ---------------------------------------------------------------------------

    function runWithLogging(TestCronLog $table, string $taskName, callable $callback): string {
        $startedAt = time();
        $id = $table->insert([
            'task_name' => $taskName,
            'started_at' => $startedAt,
            'finished_at' => 0,
            'duration_ms' => 0,
            'status' => 'running',
            'output' => null,
            'error_message' => null,
            'created_at' => $startedAt,
        ]);

        $msStart = (int)(microtime(true) * 1000);
        $output = null;
        $error = null;
        $status = 'success';

        try {
            ob_start();
            $callback();
            $output = ob_get_clean() ?: null;
        } catch (Throwable $e) {
            ob_end_clean();
            $status = 'error';
            $error = mb_substr($e->getMessage(), 0, 1024);
        }

        $finishedAt = time();
        $durationMs = (int)(microtime(true) * 1000) - $msStart;

        $table->updateById([
            'finished_at' => $finishedAt,
            'duration_ms' => max(0, $durationMs),
            'status' => $status,
            'output' => $output,
            'error_message' => $error,
        ], (int)$id);

        return (string)$id;
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwCronLog', function (): void {
        // -----------------------------------------------------------------------
        describe('schema structure', function (): void {
            it('primaryKey is id', function (): void {
                // Use concrete subclass since FwCronLog is abstract
                $inst = (new ReflectionClass(TestCronLog::class))->newInstanceWithoutConstructor();
                $ref = new ReflectionClass(FwCronLog::class);
                $prop = $ref->getProperty('primaryKey');
                $prop->setAccessible(true);
                expect($prop->getValue($inst))->toBe('id');
            });
        });

        // -----------------------------------------------------------------------
        describe('runWithLogging() — insert phase', function (): void {
            beforeEach(function (): void {
                $this->table = setupCronTable();
            });

            it('inserts a row with status=running at start', function (): void {
                runWithLogging($this->table, 'send-reminders', function (): void {});
                expect($this->table->insertCalls[0]['status'])->toBe('running');
            });

            it('stores the task_name', function (): void {
                runWithLogging($this->table, 'cleanup-expired', function (): void {});
                expect($this->table->insertCalls[0]['task_name'])->toBe('cleanup-expired');
            });

            it('sets started_at to approximately now', function (): void {
                $before = time();
                runWithLogging($this->table, 'noop', function (): void {});
                $after = time();
                $startedAt = $this->table->insertCalls[0]['started_at'];
                expect($startedAt)->toBeGreaterThan($before - 1);
                expect($startedAt)->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('runWithLogging() — success path', function (): void {
            beforeEach(function (): void {
                $this->table = setupCronTable();
            });

            it('updates status to success after successful callback', function (): void {
                $id = runWithLogging($this->table, 'noop', function (): void {});
                expect($this->table->rows[$id]['status'])->toBe('success');
            });

            it('sets finished_at after callback completes', function (): void {
                $before = time();
                $id = runWithLogging($this->table, 'noop', function (): void {});
                $after = time();
                $finishedAt = $this->table->rows[$id]['finished_at'];
                expect($finishedAt)->toBeGreaterThan($before - 1);
                expect($finishedAt)->toBeLessThan($after + 1);
            });

            it('sets duration_ms >= 0', function (): void {
                $id = runWithLogging($this->table, 'noop', function (): void {});
                expect($this->table->rows[$id]['duration_ms'])->toBeGreaterThan(-1);
            });

            it('leaves error_message as null on success', function (): void {
                $id = runWithLogging($this->table, 'noop', function (): void {});
                expect($this->table->rows[$id]['error_message'])->toBeNull();
            });
        });

        // -----------------------------------------------------------------------
        describe('runWithLogging() — error path', function (): void {
            beforeEach(function (): void {
                $this->table = setupCronTable();
            });

            it('updates status to error when callback throws', function (): void {
                $id = runWithLogging($this->table, 'failing-task', function (): void {
                    throw new RuntimeException('DB connection lost');
                });
                expect($this->table->rows[$id]['status'])->toBe('error');
            });

            it('stores the exception message in error_message', function (): void {
                $id = runWithLogging($this->table, 'failing-task', function (): void {
                    throw new RuntimeException('Connection timeout');
                });
                expect($this->table->rows[$id]['error_message'])->toBe('Connection timeout');
            });

            it('truncates error_message to 1024 chars', function (): void {
                $longMsg = str_repeat('e', 2000);
                $id = runWithLogging($this->table, 'failing-task', function () use ($longMsg): void {
                    throw new RuntimeException($longMsg);
                });
                expect(strlen($this->table->rows[$id]['error_message']))->toBe(1024);
            });

            it('still records finished_at on error', function (): void {
                $before = time();
                $id = runWithLogging($this->table, 'failing-task', function (): void {
                    throw new Exception('oops');
                });
                $after = time();
                $finishedAt = $this->table->rows[$id]['finished_at'];
                expect($finishedAt)->toBeGreaterThan($before - 1);
                expect($finishedAt)->toBeLessThan($after + 1);
            });
        });
    });
}
