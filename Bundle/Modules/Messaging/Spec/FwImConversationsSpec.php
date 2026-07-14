<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImConversations;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // In-memory stub for FwImConversations — overrides all DB-touching methods
    // ---------------------------------------------------------------------------

    class TestImConversations extends FwImConversations {
        protected string $tableName = 'fw_im_conversations_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        /** Rows stored in memory keyed by string id */
        public array $rows = [];

        public array $insertCalls = [];

        private int $nextId = 1;

        // -- DbTable instance methods used by static calls via static::get() ----

        public function insert(array $data, ?Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectOneByField(string $field, mixed $value, ?Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if (isset($row[$field]) && (string)$row[$field] === (string)$value) {
                    return $row;
                }
            }

            return null;
        }

        // Override selectAll so that findOrCreate's closure receives our fake
        // object implementing SelectInterface (via adapter pattern).
        public function selectAll(?Closure $queryCallback = null): array {
            if ($queryCallback === null) {
                return array_values($this->rows);
            }
            $adapter = new ImConvsSelectAdapter($this->rows);
            $queryCallback($adapter);

            return $adapter->getResults();
        }
    }

    // ---------------------------------------------------------------------------
    // Adapter that satisfies Aura\SqlQuery\Common\SelectInterface for tests.
    // Only implements methods actually called in FwImConversations closures.
    // ---------------------------------------------------------------------------

    class ImConvsSelectAdapter implements \Aura\SqlQuery\Common\SelectInterface {
        private array $allRows;

        private array $conditions = [];

        public function __construct(array $rows) {
            $this->allRows = array_values($rows);
        }

        public function where($cond, array $bind = []): static {
            $this->conditions[] = ['condition' => $cond, 'params' => $bind];

            return $this;
        }

        public function orderBy(array $spec): static {
            return $this;
        }

        public function limit($limit): static {
            return $this;
        }

        public function offset($offset): static {
            return $this;
        }

        public function resetCols(): static {
            return $this;
        }

        public function cols(array $cols): static {
            return $this;
        }

        public function getResults(): array {
            $rows = $this->allRows;

            foreach ($this->conditions as $cond) {
                $condition = $cond['condition'];
                $params = $cond['params'];

                if (preg_match('/participant_a\s*=\s*\?\s*AND\s*participant_b\s*=\s*\?/i', $condition)) {
                    $a = (int)$params[0];
                    $b = (int)$params[1];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['participant_a'] === $a && (int)$r['participant_b'] === $b
                    ));
                } elseif (preg_match('/participant_a\s*=\s*\?\s*OR\s*participant_b\s*=\s*\?/i', $condition)) {
                    $id = (int)$params[0];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['participant_a'] === $id || (int)$r['participant_b'] === $id
                    ));
                }
            }

            return $rows;
        }

        // -- Remaining interface methods (all no-ops for tests) -------------------
        public function __toString(): string {
            return '';
        }

        public function getStatement(): string {
            return '';
        }

        public function getBindValues(): array {
            return [];
        }

        public function getQuoteNamePrefix(): string {
            return '`';
        }

        public function getQuoteNameSuffix(): string {
            return '`';
        }

        public function bindValues(array $bind_values): static {
            return $this;
        }

        public function bindValue($name, $value): static {
            return $this;
        }

        public function resetFlags(): static {
            return $this;
        }

        public function orWhere($cond, array $bind = []): static {
            return $this;
        }

        public function resetWhere(): static {
            return $this;
        }

        public function setPaging($paging): static {
            return $this;
        }

        public function getPaging(): int {
            return 0;
        }

        public function forUpdate($enable = true): static {
            return $this;
        }

        public function distinct($enable = true): static {
            return $this;
        }

        public function isDistinct(): bool {
            return false;
        }

        public function removeCol($alias): bool {
            return false;
        }

        public function hasCol($alias): bool {
            return false;
        }

        public function hasCols(): bool {
            return false;
        }

        public function getCols(): array {
            return [];
        }

        public function from($spec): static {
            return $this;
        }

        public function fromRaw($spec): static {
            return $this;
        }

        public function fromSubSelect($spec, $name): static {
            return $this;
        }

        public function join($join, $spec, $cond = null): static {
            return $this;
        }

        public function innerJoin($spec, $cond = null, array $bind = []): static {
            return $this;
        }

        public function leftJoin($spec, $cond = null, array $bind = []): static {
            return $this;
        }

        public function joinSubSelect($join, $spec, $name, $cond = null): static {
            return $this;
        }

        public function groupBy(array $spec): static {
            return $this;
        }

        public function having($cond, array $bind = []): static {
            return $this;
        }

        public function orHaving($cond, array $bind = []): static {
            return $this;
        }

        public function page($page): static {
            return $this;
        }

        public function getPage(): int {
            return 0;
        }

        public function union(): static {
            return $this;
        }

        public function unionAll(): static {
            return $this;
        }

        public function reset(): mixed {
            return null;
        }

        public function resetTables(): static {
            return $this;
        }

        public function resetGroupBy(): static {
            return $this;
        }

        public function resetHaving(): static {
            return $this;
        }

        public function resetOrderBy(): static {
            return $this;
        }

        public function resetUnions(): static {
            return $this;
        }

        public function getLimit(): int {
            return 0;
        }

        public function getOffset(): int {
            return 0;
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetImDbSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function setupImConversationsTable(): TestImConversations {
        resetImDbSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $inst = (new ReflectionClass(TestImConversations::class))->newInstanceWithoutConstructor();
        $itemsProp->setValue(null, [TestImConversations::class => $inst]);

        return $inst;
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwImConversations', function (): void {
        // -----------------------------------------------------------------------
        describe('getPartnerId() — pure logic, no DB', function (): void {
            it('returns participant_b when myId matches participant_a', function (): void {
                $conv = ['participant_a' => 10, 'participant_b' => 20];
                $result = FwImConversations::getPartnerId($conv, 10);
                expect($result)->toBe(20);
            });

            it('returns participant_a when myId matches participant_b', function (): void {
                $conv = ['participant_a' => 10, 'participant_b' => 20];
                $result = FwImConversations::getPartnerId($conv, 20);
                expect($result)->toBe(10);
            });

            it('works with string-typed array values (DB returns strings)', function (): void {
                $conv = ['participant_a' => '5', 'participant_b' => '99'];
                $result = FwImConversations::getPartnerId($conv, 5);
                expect($result)->toBe(99);
            });

            it('falls through to participant_a when myId does not match participant_a', function (): void {
                $conv = ['participant_a' => 1, 'participant_b' => 2];
                $result = FwImConversations::getPartnerId($conv, 2);
                expect($result)->toBe(1);
            });
        });

        // -----------------------------------------------------------------------
        describe('isParticipant() — memory table', function (): void {
            beforeEach(function (): void {
                $this->table = setupImConversationsTable();
                $this->table->rows = [
                    '1' => ['id' => '1', 'participant_a' => '3', 'participant_b' => '7',
                        'last_message_at' => '0', 'created_at' => '0'],
                ];
            });

            it('returns true for participant_a', function (): void {
                expect(TestImConversations::isParticipant(1, 3))->toBe(true);
            });

            it('returns true for participant_b', function (): void {
                expect(TestImConversations::isParticipant(1, 7))->toBe(true);
            });

            it('returns false for a third-party account', function (): void {
                expect(TestImConversations::isParticipant(1, 99))->toBe(false);
            });

            it('returns false when conversation does not exist', function (): void {
                expect(TestImConversations::isParticipant(999, 3))->toBe(false);
            });
        });

        // -----------------------------------------------------------------------
        describe('findOrCreate() — memory table', function (): void {
            beforeEach(function (): void {
                $this->table = setupImConversationsTable();
                $this->table->rows = [];
            });

            it('creates a new conversation and returns its ID', function (): void {
                $id = TestImConversations::findOrCreate(5, 8);
                expect($id)->toBeGreaterThan(0);
                expect(count($this->table->insertCalls))->toBe(1);
            });

            it('stores min(a,b) as participant_a and max(a,b) as participant_b', function (): void {
                TestImConversations::findOrCreate(8, 3);
                $inserted = $this->table->insertCalls[0];
                expect($inserted['participant_a'])->toBe(3);
                expect($inserted['participant_b'])->toBe(8);
            });

            it('is symmetric — same ID regardless of argument order', function (): void {
                $id1 = TestImConversations::findOrCreate(5, 9);
                $id2 = TestImConversations::findOrCreate(9, 5);
                // Second call should find existing, not insert again
                expect(count($this->table->insertCalls))->toBe(1);
                expect($id1)->toBe($id2);
            });

            it('returns existing conversation ID without inserting again', function (): void {
                $id1 = TestImConversations::findOrCreate(2, 6);
                $id2 = TestImConversations::findOrCreate(2, 6);
                expect($id1)->toBe($id2);
                expect(count($this->table->insertCalls))->toBe(1);
            });
        });
    });
}
