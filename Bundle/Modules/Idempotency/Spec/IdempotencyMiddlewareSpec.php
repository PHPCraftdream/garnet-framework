<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Idempotency\Spec {
    use Closure;
    use LogicException;
    use Mockery;
    use PHPCraftdream\Garnet\Bundle\Modules\Idempotency\IdempotencyMiddleware;
    use PHPCraftdream\Garnet\Bundle\Modules\Idempotency\Tables\FwIdempotencyKeys;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use Psr\Http\Message\ResponseInterface;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // In-memory table stub
    // ---------------------------------------------------------------------------

    class TestIdempotencyKeys extends FwIdempotencyKeys {
        protected string $tableName = 'fw_idempotency_keys_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        public array $updateCalls = [];

        private int  $nextId = 1;

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

        public function selectOneByField(
            string $fieldName,
            mixed $value,
            ?Closure $queryCallback = null
        ): ?array {
            foreach ($this->rows as $row) {
                if (($row[$fieldName] ?? null) === $value) {
                    if ($queryCallback !== null) {
                        // Apply the where-filters from the closure via a simple
                        // match on 'account_id' and 'route_path' if they are
                        // provided through the closure's captured vars.
                        // We replicate the middleware's usage: account_id + route_path.
                        // The closure captures $accountId and $routePath; we can't
                        // easily execute it against the in-memory array, so instead
                        // we let the callers control which rows are in the table.
                    }

                    return $row;
                }
            }

            return null;
        }

        public function getTableName(): string {
            return $this->tableName;
        }

        public function getQueryEx(): \PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx {
            throw new LogicException('getQueryEx() not supported in test stub');
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetDbTableSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    function resetMiddlewareState(): void {
        $ref = new ReflectionClass(IdempotencyMiddleware::class);

        $tc = $ref->getProperty('tableClass');
        $tc->setAccessible(true);
        $tc->setValue(null);

        $ri = $ref->getProperty('rowId');
        $ri->setAccessible(true);
        $ri->setValue(null);
    }

    /**
     * Register the in-memory table singleton and set the middleware table class.
     *
     * @return TestIdempotencyKeys
     */
    function setupTable(): TestIdempotencyKeys {
        resetDbTableSingletons();
        resetMiddlewareState();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');
        $itemsProp->setAccessible(true);

        $tableObj = (new ReflectionClass(TestIdempotencyKeys::class))
            ->newInstanceWithoutConstructor();

        $itemsProp->setValue([TestIdempotencyKeys::class => $tableObj]);

        IdempotencyMiddleware::setTableClass(TestIdempotencyKeys::class);

        return $tableObj;
    }

    /**
     * Build a minimal IGlobalReqParams mock.
     *
     * @param array<string, mixed> $server  Keys/values for readServerValue().
     */
    function makeGlobals(bool $isPost = true, string $uri = '/api/test', array $server = []): IGlobalReqParams {
        $mock = Mockery::mock(IGlobalReqParams::class);
        $mock->allows('isPost')->andReturn($isPost);
        $mock->allows('getUri')->andReturn($uri);
        $mock->allows('readServerValue')->andReturnUsing(
            fn (string $name, mixed $default = null) => $server[$name] ?? $default
        );

        return $mock;
    }

    function makeParams(): IRouterUriParams {
        return Mockery::mock(IRouterUriParams::class);
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('IdempotencyMiddleware', function (): void {
        afterEach(function (): void {
            Mockery::close();
            resetMiddlewareState();
            resetDbTableSingletons();
        });

        // -----------------------------------------------------------------------
        describe('before() — pass-through conditions', function (): void {
            it('returns null for GET requests (non-POST)', function (): void {
                setupTable();
                $globals = makeGlobals(isPost: false);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });

            it('returns null when no table class is configured', function (): void {
                resetMiddlewareState(); // tableClass stays null
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => 'abcdef1234567890']);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });

            it('returns null when X-Idempotency-Key header is absent', function (): void {
                setupTable();
                $globals = makeGlobals(server: []);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });

            it('returns null when key is too short (< 16 chars)', function (): void {
                setupTable();
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => 'short']);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });

            it('returns null when key is too long (> 64 chars)', function (): void {
                setupTable();
                $longKey = str_repeat('a', 65);
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $longKey]);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });

            it('returns null when key contains invalid characters', function (): void {
                setupTable();
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => 'valid-key!@#$%^&*()']);
                $result = IdempotencyMiddleware::before($globals, makeParams());
                expect($result)->toBeNull();
            });
        });

        // -----------------------------------------------------------------------
        describe('before() — first-request reservation', function (): void {
            it('inserts a row and returns null for a new valid key', function (): void {
                $table = setupTable();
                $key = 'valid-key-1234567890';
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);

                $result = IdempotencyMiddleware::before($globals, makeParams());

                expect($result)->toBeNull();
                expect(count($table->insertCalls))->toBe(1);
                expect($table->insertCalls[0]['idem_key'])->toBe($key);
                expect($table->insertCalls[0]['http_status'])->toBe(0);
            });

            it('sets http_status=0 (in-flight) on the reserved row', function (): void {
                $table = setupTable();
                $key = 'my-unique-key-123456';
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);

                IdempotencyMiddleware::before($globals, makeParams());

                $inserted = $table->insertCalls[0];
                expect($inserted['http_status'])->toBe(0);
                expect($inserted['finalized_at'])->toBe(0);
            });

            it('records the route_path normalised from the URI', function (): void {
                $table = setupTable();
                $globals = makeGlobals(
                    uri: '/api/booking/create',
                    server: [IdempotencyMiddleware::HEADER_SERVER_KEY => 'key-for-route-1234567']
                );

                IdempotencyMiddleware::before($globals, makeParams());

                expect($table->insertCalls[0]['route_path'])->toBe('/api/booking/create');
            });

            it('accepts keys of exactly 16 characters', function (): void {
                $table = setupTable();
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => 'abcdefgh12345678']);

                $result = IdempotencyMiddleware::before($globals, makeParams());

                expect($result)->toBeNull();
                expect(count($table->insertCalls))->toBe(1);
            });

            it('accepts keys of exactly 64 characters', function (): void {
                $table = setupTable();
                $key = str_repeat('a', 64);
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);

                $result = IdempotencyMiddleware::before($globals, makeParams());

                expect($result)->toBeNull();
                expect(count($table->insertCalls))->toBe(1);
            });
        });

        // -----------------------------------------------------------------------
        describe('before() — duplicate / replay detection', function (): void {
            it('replays a 200 response for a finalized row', function (): void {
                $table = setupTable();
                $key = 'replay-key-12345678';

                // Pre-seed a finalized row in the table.
                $table->rows['1'] = [
                    'id' => '1',
                    'account_id' => 0,
                    'idem_key' => $key,
                    'route_path' => '/api/test',
                    'http_status' => 200,
                    'content_type' => 'application/json',
                    'response_body' => '{"ok":true}',
                    'created_at' => time() - 10,
                    'finalized_at' => time() - 5,
                ];

                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);
                $response = IdempotencyMiddleware::before($globals, makeParams());

                expect($response)->toBeAnInstanceOf(ResponseInterface::class);
                expect($response->getStatusCode())->toBe(200);
                expect($response->getHeaderLine('X-Idempotent-Replay'))->toBe('1');
            });

            it('returns 409 in-flight response when existing row is not yet finalized', function (): void {
                $table = setupTable();
                $key = 'inflight-key-1234567';

                $table->rows['1'] = [
                    'id' => '1',
                    'account_id' => 0,
                    'idem_key' => $key,
                    'route_path' => '/api/test',
                    'http_status' => 0,       // still in-flight
                    'content_type' => null,
                    'response_body' => null,
                    'created_at' => time() - 2,
                    'finalized_at' => 0,
                ];

                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);
                $response = IdempotencyMiddleware::before($globals, makeParams());

                expect($response)->toBeAnInstanceOf(ResponseInterface::class);
                expect($response->getStatusCode())->toBe(409);
            });

            it('does not insert a new row when a duplicate already exists', function (): void {
                $table = setupTable();
                $key = 'dup-key-1234567890ab';

                $table->rows['1'] = [
                    'id' => '1',
                    'account_id' => 0,
                    'idem_key' => $key,
                    'route_path' => '/api/test',
                    'http_status' => 200,
                    'content_type' => 'application/json',
                    'response_body' => '{}',
                    'created_at' => time() - 5,
                    'finalized_at' => time() - 1,
                ];

                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);
                IdempotencyMiddleware::before($globals, makeParams());

                // No new insert — only the pre-seeded row exists.
                expect(count($table->insertCalls))->toBe(0);
            });
        });

        // -----------------------------------------------------------------------
        describe('finalize()', function (): void {
            it('is a no-op and returns the response unchanged when no row was reserved', function (): void {
                setupTable();
                $response = Mockery::mock(ResponseInterface::class);
                $result = IdempotencyMiddleware::finalize($response);
                expect($result)->toBe($response);
            });

            it('updates the reserved row with status and body after controller runs', function (): void {
                $table = setupTable();
                $key = 'finalize-key-1234567';
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);

                // Reserve the row.
                IdempotencyMiddleware::before($globals, makeParams());

                // Build a fake PSR-7 response to finalize.
                $body = \GuzzleHttp\Psr7\Utils::streamFor('{"result":"ok"}');
                $psr = (new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], $body));

                IdempotencyMiddleware::finalize($psr);

                $updateCall = $table->updateCalls[0]['data'];
                expect($updateCall['http_status'])->toBe(200);
                expect($updateCall['content_type'])->toBe('application/json');
                expect($updateCall['response_body'])->toBe('{"result":"ok"}');
                expect($updateCall['finalized_at'])->toBeGreaterThan(0);
            });

            it('returns the original response object from finalize', function (): void {
                setupTable();
                $key = 'ret-key-12345678901234';
                $globals = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key]);
                IdempotencyMiddleware::before($globals, makeParams());

                $psr = new \GuzzleHttp\Psr7\Response(201);
                $result = IdempotencyMiddleware::finalize($psr);

                expect($result)->toBe($psr);
            });
        });
    });
}
