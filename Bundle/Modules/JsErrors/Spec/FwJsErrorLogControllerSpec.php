<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Controllers\FwJsErrorLogController;
    use PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Tables\FwJsErrors;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // In-memory stub table
    // ---------------------------------------------------------------------------

    class TestJsErrors extends FwJsErrors {
        protected string $tableName = 'fw_js_errors_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        public array $updateCalls = [];

        private int $nextId = 1;

        public function insert(array $data, ?Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function updateById(array $updateData, int|string|array $id, ?callable $callback = null): bool {
            $id = (string)$id;
            $this->updateCalls[] = ['data' => $updateData, 'id' => $id];

            if (isset($this->rows[$id])) {
                $this->rows[$id] = array_merge($this->rows[$id], $updateData);
            }

            return true;
        }

        public function selectOneByField(string $field, mixed $value, ?Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if ($row[$field] === $value) {
                    return $row;
                }
            }

            return null;
        }
    }

    // ---------------------------------------------------------------------------
    // Minimal IGlobalReqParams stub
    // ---------------------------------------------------------------------------

    class StubGlobals implements IGlobalReqParams {
        public function __construct(
            private array $post = [],
            private array $server = [],
        ) {
        }

        public function readServerValue(string $name, mixed $default = null): string|null {
            return isset($this->server[$name]) ? (string)$this->server[$name] : ($default !== null ? (string)$default : null);
        }

        public function readServerAll(): array {
            return $this->server;
        }

        public function readGetValue(string $name, mixed $default = null): mixed {
            return $default;
        }

        public function readGetAll(): array {
            return [];
        }

        public function readPostValue(string $name, mixed $default = null): mixed {
            return $this->post[$name] ?? $default;
        }

        public function readPostAll(): array {
            return $this->post;
        }

        public function readCookieValue(string $name, mixed $default = null): string|null {
            return null;
        }

        public function readCookieAll(): array {
            return [];
        }

        public function readFilesValue(string $name, mixed $default = null): mixed {
            return null;
        }

        public function readFilesAll(): array {
            return [];
        }

        public function getUri(): string {
            return '/js-error/~report';
        }

        public function httpMethod(): string {
            return 'POST';
        }

        public function isPost(): bool {
            return true;
        }

        public function isEmptyPost(): bool {
            return empty($this->post);
        }

        public function isGet(): bool {
            return false;
        }

        public function isLocalhost(): bool {
            return true;
        }

        public function isPhpServer(): bool {
            return false;
        }

        public function isDev(): bool {
            return false;
        }

        public function ip(): string {
            return '127.0.0.1';
        }
    }

    class StubUriParams implements IRouterUriParams {
        public function getHttpMethod(): string {
            return 'POST';
        }

        public function getRouteVal(): string {
            return '/js-error/~report';
        }

        public function getUriParam(string $name, ?string $defaultVal = null): ?string {
            return $defaultVal;
        }

        public function getUriParams(): array {
            return [];
        }

        public function getMethodName(): string {
            return 'report';
        }

        public function getMethodParams(): array {
            return [];
        }

        public function getMethodParam(int $name, ?string $defaultVal = null): ?string {
            return $defaultVal;
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetJsErrorsSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function resetControllerState(): void {
        $ref = new ReflectionClass(FwJsErrorLogController::class);
        $prop = $ref->getProperty('tableClass');
        $prop->setValue(null, null);
    }

    function setupJsErrorsTable(): TestJsErrors {
        resetJsErrorsSingletons();
        resetControllerState();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $inst = (new ReflectionClass(TestJsErrors::class))->newInstanceWithoutConstructor();
        $itemsProp->setValue(null, [TestJsErrors::class => $inst]);

        FwJsErrorLogController::setTableClass(TestJsErrors::class);

        return $inst;
    }

    function callReport(array $post = [], array $server = []): mixed {
        $globals = new StubGlobals($post, $server);
        $params = new StubUriParams();

        return FwJsErrorLogController::post__report($globals, $params);
    }

    function decodeResponse(mixed $response): array {
        $body = (string)$response->getBody();

        return json_decode($body, true);
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwJsErrorLogController', function (): void {
        // -----------------------------------------------------------------------
        describe('URL constant', function (): void {
            it('defines URL as /js-error/', function (): void {
                expect(FwJsErrorLogController::URL)->toBe('/js-error/');
            });
        });

        // -----------------------------------------------------------------------
        describe('setTableClass() / guard', function (): void {
            beforeEach(function (): void {
                resetJsErrorsSingletons();
                resetControllerState();
            });

            it('throws LogicException when tableClass not set', function (): void {
                expect(function (): void {
                    callReport(['message' => 'boom']);
                })->toThrow(new LogicException('FwJsErrorLogController::setTableClass() must be called before use.'));
            });

            it('does not throw after setTableClass() is called', function (): void {
                setupJsErrorsTable();
                expect(function (): void {
                    callReport(['message' => 'boom']);
                })->not->toThrow();
            });
        });

        // -----------------------------------------------------------------------
        describe('post__report() — validation', function (): void {
            beforeEach(function (): void {
                $this->table = setupJsErrorsTable();
            });

            it('returns 400 when message is empty', function (): void {
                $resp = callReport(['message' => '']);
                expect($resp->getStatusCode())->toBe(400);
                expect(decodeResponse($resp)['ok'])->toBe(false);
            });

            it('returns 400 when message exceeds 4096 chars', function (): void {
                $resp = callReport(['message' => str_repeat('x', 4097)]);
                expect($resp->getStatusCode())->toBe(400);
                expect(decodeResponse($resp)['error'])->toBe('invalid');
            });

            it('returns 400 for a whitespace-only message', function (): void {
                $resp = callReport(['message' => '   ']);
                expect($resp->getStatusCode())->toBe(400);
            });
        });

        // -----------------------------------------------------------------------
        describe('post__report() — insert path', function (): void {
            beforeEach(function (): void {
                $this->table = setupJsErrorsTable();
            });

            it('returns ok=true and inserted=true for a new error', function (): void {
                $resp = callReport(['message' => 'TypeError: x is undefined']);
                $body = decodeResponse($resp);
                expect($body['ok'])->toBe(true);
                expect($body['inserted'])->toBe(true);
            });

            it('inserts exactly one row', function (): void {
                callReport(['message' => 'ReferenceError: foo']);
                expect(count($this->table->insertCalls))->toBe(1);
            });

            it('stores the message in the inserted row', function (): void {
                callReport(['message' => 'SyntaxError: unexpected token']);
                expect($this->table->insertCalls[0]['message'])->toBe('SyntaxError: unexpected token');
            });

            it('stores line and col as integers', function (): void {
                callReport(['message' => 'err', 'line' => '42', 'col' => '7']);
                $row = $this->table->insertCalls[0];
                expect($row['line'])->toBe(42);
                expect($row['col'])->toBe(7);
            });

            it('stores a sha256 hash in the hash field', function (): void {
                callReport(['message' => 'err', 'file' => 'app.js', 'line' => '1']);
                $hash = $this->table->insertCalls[0]['hash'];
                expect(strlen($hash))->toBe(64);
            });

            it('sets count=1 on first insert', function (): void {
                callReport(['message' => 'err']);
                expect($this->table->insertCalls[0]['count'])->toBe(1);
            });

            it('sets first_seen_at and last_seen_at to approximately now', function (): void {
                $before = time();
                callReport(['message' => 'err']);
                $after = time();
                $row = $this->table->insertCalls[0];
                expect($row['first_seen_at'])->toBeGreaterThan($before - 1);
                expect($row['first_seen_at'])->toBeLessThan($after + 1);
                expect($row['last_seen_at'])->toBeGreaterThan($before - 1);
                expect($row['last_seen_at'])->toBeLessThan($after + 1);
            });

            it('sets null for empty optional fields (stack, file, url)', function (): void {
                callReport(['message' => 'err', 'stack' => '', 'file' => '', 'url' => '']);
                $row = $this->table->insertCalls[0];
                expect($row['stack'])->toBeNull();
                expect($row['file'])->toBeNull();
                expect($row['url'])->toBeNull();
            });

            it('stores user_agent from server vars', function (): void {
                callReport(
                    ['message' => 'err'],
                    ['HTTP_USER_AGENT' => 'Mozilla/5.0 Test']
                );
                expect($this->table->insertCalls[0]['user_agent'])->toBe('Mozilla/5.0 Test');
            });
        });

        // -----------------------------------------------------------------------
        describe('post__report() — deduplication / update path', function (): void {
            beforeEach(function (): void {
                $this->table = setupJsErrorsTable();
                // Pre-seed an existing row
                $msg = 'TypeError: cannot read property';
                $hash = hash('sha256', $msg . '||0');
                $this->existingRow = [
                    'id' => '1',
                    'hash' => $hash,
                    'message' => $msg,
                    'count' => 3,
                    'last_seen_at' => time() - 60,  // older than storm window
                ];
                $this->table->rows['1'] = $this->existingRow;
                $this->hash = $hash;
                $this->msg = $msg;
            });

            it('returns updated=true when same error seen again (outside storm window)', function (): void {
                $resp = callReport(['message' => $this->msg, 'line' => '0']);
                $body = decodeResponse($resp);
                expect($body['ok'])->toBe(true);
                expect($body['updated'])->toBe(true);
            });

            it('increments count by 1 on re-submit', function (): void {
                callReport(['message' => $this->msg, 'line' => '0']);
                expect($this->table->updateCalls[0]['data']['count'])->toBe(4);
            });

            it('does not insert a new row on duplicate', function (): void {
                callReport(['message' => $this->msg, 'line' => '0']);
                expect(count($this->table->insertCalls))->toBe(0);
            });

            it('returns throttled=true when re-submitted within ANTI_STORM_WINDOW_SEC', function (): void {
                // Patch last_seen_at to "just now"
                $this->table->rows['1']['last_seen_at'] = time();
                $resp = callReport(['message' => $this->msg, 'line' => '0']);
                $body = decodeResponse($resp);
                expect($body['ok'])->toBe(true);
                expect($body['throttled'])->toBe(true);
            });
        });
    });
}
