<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Spec {
    use Closure;
    use LogicException;
    use mysqli;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\FwMagicLoginService;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Tables\FwMagicLoginTokens;
    use PHPCraftdream\Garnet\Kernel\Db\Link\CasUpdate;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // In-memory table stub
    // ---------------------------------------------------------------------------

    class TestMagicLoginTokens extends FwMagicLoginTokens {
        protected string $tableName = 'fw_magic_login_tokens_test';

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

        public function selectById(int|string $id, ?Closure $queryCallback = null): ?array {
            return $this->rows[(string)$id] ?? null;
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        public function selectOneByField(string $field, array|string|int $value, ?Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if (($row[$field] ?? null) === $value) {
                    return $row;
                }
            }

            return null;
        }

        public function getTableName(): string {
            return $this->tableName;
        }
    }

    // ---------------------------------------------------------------------------
    // Fake DB link for CasUpdate injection
    // ---------------------------------------------------------------------------

    class FakeMagicLoginDbLink implements IDbMySQLiLink {
        public int $affectedRows = 1;

        public array $queries = [];

        /** @var array<string, TestMagicLoginTokens>|null */
        public ?array $tables = null;

        public function getMysqli(): mysqli {
            throw new LogicException('getMysqli not supported in fake');
        }

        public function getLastAffectedRows(): int {
            return $this->affectedRows;
        }

        public function isBusy(): bool {
            return false;
        }

        public function queryAsync(string $sql, ?callable $callBack = null): IDbMySQLiLink {
            return $this;
        }

        /**
         * Simulates the CAS UPDATE against the in-memory TestMagicLoginTokens
         * table, so consume() behaves like the real thing: it only succeeds
         * once per row (used_at IS NULL guard), and returns the number of
         * affected rows exactly like a real MySQL UPDATE would.
         */
        public function query(string $sql, array $params = []): array|int|string|bool {
            $this->queries[] = ['sql' => $sql, 'params' => $params];

            if ($this->tables !== null && preg_match('/UPDATE\s+(\S+)\s+SET\s+used_at/i', $sql)) {
                [$usedAt, $tokenId] = $params;
                $tokenId = (string)$tokenId;

                foreach ($this->tables as $table) {
                    if (isset($table->rows[$tokenId]) && empty($table->rows[$tokenId]['used_at'])) {
                        $table->rows[$tokenId]['used_at'] = $usedAt;
                        $this->affectedRows = 1;

                        return true;
                    }
                }

                $this->affectedRows = 0;

                return true;
            }

            return true;
        }

        public function poll(): array|int|string|bool|null {
            return null;
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetDbTableSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function resetServiceState(): void {
        $ref = new ReflectionClass(FwMagicLoginService::class);

        $t = $ref->getProperty('tokensTable');
        $t->setValue(null, null);
    }

    function setupTables(): TestMagicLoginTokens {
        resetDbTableSingletons();
        resetServiceState();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $tokensInst = new ReflectionClass(TestMagicLoginTokens::class);
        $tokensObj = $tokensInst->newInstanceWithoutConstructor();

        $itemsProp->setValue(null, [
            TestMagicLoginTokens::class => $tokensObj,
        ]);

        FwMagicLoginService::setTableClasses(TestMagicLoginTokens::class);

        return $tokensObj;
    }

    /**
     * Inject a fake DB link into CasUpdate so consume() works without real MySQL.
     */
    function injectCasLink(FakeMagicLoginDbLink $link): void {
        $ref = new ReflectionClass(CasUpdate::class);
        $prop = $ref->getProperty('sharedLink');
        $prop->setValue(null, $link);
    }

    function resetCasLink(): void {
        $ref = new ReflectionClass(CasUpdate::class);
        $prop = $ref->getProperty('sharedLink');
        $prop->setValue(null, null);
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwMagicLoginService', function (): void {
        // -----------------------------------------------------------------------
        describe('setTableClasses() / guard', function (): void {
            beforeEach(function (): void {
                resetDbTableSingletons();
                resetServiceState();
            });

            it('throws LogicException when table classes are not configured', function (): void {
                expect(function (): void {
                    FwMagicLoginService::validate('sometoken');
                })->toThrow(new LogicException('FwMagicLoginService::setTableClasses() must be called before use.'));
            });

            it('does not throw after setTableClasses() is called', function (): void {
                setupTables();
                expect(function (): void {
                    FwMagicLoginService::validate('nonexistent');
                })->not->toThrow();
            });
        });

        // -----------------------------------------------------------------------
        describe('generate()', function (): void {
            beforeEach(function (): void {
                $this->tokens = setupTables();
            });

            it('creates a token that is exactly 32 hex characters', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                expect(strlen($row['token']))->toBe(32);
                expect(ctype_xdigit($row['token']))->toBe(true);
            });

            it('computes expires_at as created_at + 300', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                expect($row['expires_at'] - $row['created_at'])->toBe(300);
            });

            it('sets used_at to null initially', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                expect($row['used_at'])->toBeNull();
            });

            it('stores email, return_uri, consent_pd_at, consent_marketing_at as supplied', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step/token~INVITE', '1690000000', '1690000001');
                expect($row['email'])->toBe('user@example.com');
                expect($row['return_uri'])->toBe('/first-step/token~INVITE');
                expect($row['consent_pd_at'])->toBe('1690000000');
                expect($row['consent_marketing_at'])->toBe('1690000001');
            });

            it('assigns an id to the returned row', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                expect(isset($row['id']))->toBe(true);
                expect((int)$row['id'])->toBeGreaterThan(0);
            });

            it('inserts the row into the tokens table', function (): void {
                FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                expect(count($this->tokens->insertCalls))->toBe(1);
            });

            it('sets created_at to approximately now', function (): void {
                $before = time();
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                $after = time();
                expect($row['created_at'])->toBeGreaterThan($before - 1);
                expect($row['created_at'])->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('validate()', function (): void {
            beforeEach(function (): void {
                $this->tokens = setupTables();
            });

            it('returns valid=false with reason=unknown for an empty token string', function (): void {
                $result = FwMagicLoginService::validate('');
                expect($result['valid'])->toBe(false);
                expect($result['reason'])->toBe('unknown');
            });

            it('returns valid=false with reason=unknown for a token that was never generated', function (): void {
                $result = FwMagicLoginService::validate(bin2hex(random_bytes(16)));
                expect($result['valid'])->toBe(false);
                expect($result['reason'])->toBe('unknown');
            });

            it('returns valid=true with the token row for a valid freshly-generated token', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                $result = FwMagicLoginService::validate($row['token']);
                expect($result['valid'])->toBe(true);
                expect($result['token']['email'])->toBe('user@example.com');
                expect($result['token']['return_uri'])->toBe('/first-step');
                expect($result['token']['token'])->toBe($row['token']);
            });

            it('returns valid=false with reason=used for an already-consumed token', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');

                $fakeLink = new FakeMagicLoginDbLink();
                $fakeLink->tables = [$this->tokens];
                injectCasLink($fakeLink);

                $consumed = FwMagicLoginService::consume((int)$row['id']);
                expect($consumed)->toBe(true);

                $result = FwMagicLoginService::validate($row['token']);
                expect($result['valid'])->toBe(false);
                expect($result['reason'])->toBe('used');

                resetCasLink();
            });

            it('returns valid=false with reason=expired for a token whose expires_at is in the past', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');

                $this->tokens->updateById(['expires_at' => time() - 3600], $row['id']);

                $result = FwMagicLoginService::validate($row['token']);
                expect($result['valid'])->toBe(false);
                expect($result['reason'])->toBe('expired');
            });
        });

        // -----------------------------------------------------------------------
        describe('consume()', function (): void {
            beforeEach(function (): void {
                $this->tokens = setupTables();
                $this->fakeLink = new FakeMagicLoginDbLink();
                $this->fakeLink->tables = [$this->tokens];
                injectCasLink($this->fakeLink);
            });

            afterEach(function (): void {
                resetCasLink();
            });

            it('returns true for the first consume() of a valid, unused token', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');
                $result = FwMagicLoginService::consume((int)$row['id']);
                expect($result)->toBe(true);
            });

            it('returns false on a repeated consume() of the same tokenId (one-time-use guard)', function (): void {
                $row = FwMagicLoginService::generate('user@example.com', '/first-step', '1', '0');

                $first = FwMagicLoginService::consume((int)$row['id']);
                $second = FwMagicLoginService::consume((int)$row['id']);

                expect($first)->toBe(true);
                expect($second)->toBe(false);
            });

            it('returns false for consume() of a non-existent tokenId', function (): void {
                $result = FwMagicLoginService::consume(999999);
                expect($result)->toBe(false);
            });
        });
    });
}
