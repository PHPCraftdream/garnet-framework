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

    /**
     * Records where('col = :bind', ['bind' => $value]) calls so the stub
     * table below can apply them as exact-match filters.
     */
    class FakeIdemQuery {
        /** @var array<string, mixed> column => required value */
        public array $conditions = [];

        public function where(string $sql, array $params): void {
            if (preg_match('/^\s*([a-zA-Z_]+)\s*=/', $sql, $m) && count($params) === 1) {
                $this->conditions[$m[1]] = reset($params);
            }
        }
    }

    class TestIdempotencyKeys extends FwIdempotencyKeys {
        protected string $tableName = 'fw_idempotency_keys_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        public array $updateCalls = [];

        private int  $nextId = 1;

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

        public function selectOneByField(
            string $fieldName,
            mixed $value,
            ?Closure $queryCallback = null
        ): ?array {
            $conditions = [];

            if ($queryCallback !== null) {
                $fakeQuery = new FakeIdemQuery();
                $queryCallback($fakeQuery);
                $conditions = $fakeQuery->conditions;
            }

            foreach ($this->rows as $row) {
                if (($row[$fieldName] ?? null) !== $value) {
                    continue;
                }

                $matchesAll = true;

                foreach ($conditions as $col => $expected) {
                    if (($row[$col] ?? null) !== $expected) {
                        $matchesAll = false;

                        break;
                    }
                }

                if ($matchesAll) {
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

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetDbTableSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function resetMiddlewareState(): void {
        $ref = new ReflectionClass(IdempotencyMiddleware::class);

        $tc = $ref->getProperty('tableClass');
        $tc->setValue(null, null);

        $ri = $ref->getProperty('rowId');
        $ri->setValue(null, null);
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

        $tableObj = (new ReflectionClass(TestIdempotencyKeys::class))
            ->newInstanceWithoutConstructor();

        $itemsProp->setValue(null, [TestIdempotencyKeys::class => $tableObj]);

        IdempotencyMiddleware::setTableClass(TestIdempotencyKeys::class);

        return $tableObj;
    }

    /**
     * Build a minimal IGlobalReqParams mock.
     *
     * @param array<string, mixed> $server  Keys/values for readServerValue().
     */
    function makeGlobals(bool $isPost = true, string $uri = '/api/test', array $server = [], string $ip = '127.0.0.1'): IGlobalReqParams {
        $mock = Mockery::mock(IGlobalReqParams::class);
        $mock->allows('isPost')->andReturn($isPost);
        $mock->allows('getUri')->andReturn($uri);
        $mock->allows('readServerValue')->andReturnUsing(
            fn (string $name, mixed $default = null) => $server[$name] ?? $default
        );
        $mock->allows('ip')->andReturn($ip);

        return $mock;
    }

    function makeParams(): IRouterUriParams {
        return Mockery::mock(IRouterUriParams::class);
    }

    /** Mirrors IdempotencyMiddleware::anonymousPseudoAccountId() for seeding test rows. */
    function anonAccountId(string $ip = '127.0.0.1'): int {
        return -1 - (crc32($ip) % 2000000000);
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

            it('scopes anonymous requests by IP: two different anonymous clients with the same key/route do not collide', function (): void {
                $table = setupTable();
                $key = 'shared-anon-key-1234567';

                $clientA = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key], ip: '203.0.113.10');
                $resultA = IdempotencyMiddleware::before($clientA, makeParams());

                $clientB = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key], ip: '198.51.100.20');
                $resultB = IdempotencyMiddleware::before($clientB, makeParams());

                // Both reserve independently (no replay/409) and land two distinct rows —
                // if they collided, the second call would replay/409 off the first's row.
                expect($resultA)->toBeNull();
                expect($resultB)->toBeNull();
                expect(count($table->insertCalls))->toBe(2);
                expect($table->insertCalls[0]['account_id'])->not->toBe($table->insertCalls[1]['account_id']);
            });

            it('scopes repeat requests from the same anonymous IP to the same triple (replay still works)', function (): void {
                $table = setupTable();
                $key = 'anon-retry-key-12345678';

                $first = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key], ip: '203.0.113.10');
                IdempotencyMiddleware::before($first, makeParams());

                $retry = makeGlobals(server: [IdempotencyMiddleware::HEADER_SERVER_KEY => $key], ip: '203.0.113.10');
                $result = IdempotencyMiddleware::before($retry, makeParams());

                // Same IP + same key/route → same triple → in-flight 409, not a second insert.
                expect(count($table->insertCalls))->toBe(1);
                expect($result)->not->toBeNull();
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
                    'account_id' => anonAccountId(),
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
                    'account_id' => anonAccountId(),
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
                    'account_id' => anonAccountId(),
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

        // -----------------------------------------------------------------------
        describe('gc()', function (): void {
            it('returns 0 when no table class is configured', function (): void {
                resetMiddlewareState();
                $result = IdempotencyMiddleware::gc(time() - 86400);
                expect($result)->toBe(0);
            });
        });
    });
}
