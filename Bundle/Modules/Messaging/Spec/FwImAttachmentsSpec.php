<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Spec {
    use Aura\SqlQuery\Common\SelectInterface;
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImAttachments;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // SelectInterface no-op base (shared across all attachment specs)
    // ---------------------------------------------------------------------------

    abstract class BaseAttSelectAdapter implements SelectInterface {
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
    // Adapter for FwImAttachments::getByMessageIds()
    // ---------------------------------------------------------------------------

    class AttachmentsSelectAdapter extends BaseAttSelectAdapter {
        private array $allRows;

        private ?array $messageIds = null;

        public function __construct(array $rows) {
            $this->allRows = array_values($rows);
        }

        public function where($cond, array $bind = []): static {
            if (str_contains($cond, 'message_id IN')) {
                $this->messageIds = array_map('intval', (array)($bind[0] ?? []));
            }

            return $this;
        }

        public function getResults(): array {
            if ($this->messageIds === null) {
                return $this->allRows;
            }

            return array_values(array_filter($this->allRows, fn ($r) => in_array((int)$r['message_id'], $this->messageIds, true)
            ));
        }
    }

    // ---------------------------------------------------------------------------
    // In-memory stub for FwImAttachments
    // ---------------------------------------------------------------------------

    class TestImAttachments extends FwImAttachments {
        protected string $tableName = 'fw_im_attachments_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        private int $nextId = 1;

        public function insert(array $data, ?Closure $queryCallback = null): false|string {
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectAll(?Closure $queryCallback = null): array {
            if ($queryCallback === null) {
                return array_values($this->rows);
            }
            $adapter = new AttachmentsSelectAdapter($this->rows);
            $queryCallback($adapter);

            return $adapter->getResults();
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetAttachmentsDbSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function setupAttachmentsTable(): TestImAttachments {
        resetAttachmentsDbSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $inst = (new ReflectionClass(TestImAttachments::class))->newInstanceWithoutConstructor();
        $itemsProp->setValue(null, [TestImAttachments::class => $inst]);

        return $inst;
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwImAttachments', function (): void {
        describe('getByMessageIds() — pure grouping logic', function (): void {
            beforeEach(function (): void {
                $this->table = setupAttachmentsTable();
            });

            it('returns empty array for empty messageIds input', function (): void {
                $result = TestImAttachments::getByMessageIds([]);
                expect($result)->toBe([]);
            });

            it('returns empty array when no attachments match', function (): void {
                $this->table->rows = [
                    '1' => ['id' => '1', 'message_id' => '5', 'original_name' => 'a.pdf',
                        'stored_name' => 'stored_a.pdf', 'mime_type' => 'application/pdf',
                        'size' => 1000, 'created_at' => time()],
                ];
                $result = TestImAttachments::getByMessageIds([99, 100]);
                expect($result)->toBe([]);
            });

            it('groups attachments by message_id', function (): void {
                $this->table->rows = [
                    '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'a.pdf',
                        'stored_name' => 'stored_a.pdf', 'mime_type' => 'application/pdf',
                        'size' => 100, 'created_at' => time()],
                    '2' => ['id' => '2', 'message_id' => '10', 'original_name' => 'b.jpg',
                        'stored_name' => 'stored_b.jpg', 'mime_type' => 'image/jpeg',
                        'size' => 200, 'created_at' => time()],
                    '3' => ['id' => '3', 'message_id' => '20', 'original_name' => 'c.png',
                        'stored_name' => 'stored_c.png', 'mime_type' => 'image/png',
                        'size' => 300, 'created_at' => time()],
                ];

                $result = TestImAttachments::getByMessageIds([10, 20]);

                expect(isset($result[10]))->toBe(true);
                expect(isset($result[20]))->toBe(true);
                expect(count($result[10]))->toBe(2);
                expect(count($result[20]))->toBe(1);
            });

            it('only returns attachments for the requested message IDs', function (): void {
                $this->table->rows = [
                    '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'a.pdf',
                        'stored_name' => 'stored_a.pdf', 'mime_type' => 'application/pdf',
                        'size' => 100, 'created_at' => time()],
                    '2' => ['id' => '2', 'message_id' => '99', 'original_name' => 'other.pdf',
                        'stored_name' => 'stored_other.pdf', 'mime_type' => 'application/pdf',
                        'size' => 200, 'created_at' => time()],
                ];

                $result = TestImAttachments::getByMessageIds([10]);

                expect(isset($result[10]))->toBe(true);
                expect(isset($result[99]))->toBe(false);
            });

            it('key in result is an integer (message_id cast)', function (): void {
                $this->table->rows = [
                    '1' => ['id' => '1', 'message_id' => '7', 'original_name' => 'x.pdf',
                        'stored_name' => 'stored_x.pdf', 'mime_type' => 'application/pdf',
                        'size' => 50, 'created_at' => time()],
                ];

                $result = TestImAttachments::getByMessageIds([7]);
                $keys = array_keys($result);
                expect($keys[0])->toBe(7);
            });
        });
    });
}
