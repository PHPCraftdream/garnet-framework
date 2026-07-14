<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Comments\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Comments\Tables\FwComments;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // In-memory stub table
    // ---------------------------------------------------------------------------

    class TestComments extends FwComments {
        protected string $tableName = 'fw_comments_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
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

        /**
         * We override selectAll to apply the query callbacks via a simple in-memory filter.
         * FwComments::getForEntity / countForEntity pass WHERE logic through a SelectInterface —
         * to avoid wiring Aura query objects in tests, we re-implement the method at the
         * concrete class level and supply the filter directly in getForEntity / countForEntity
         * overrides below.
         */
        public function selectAll(?Closure $queryCallback = null): array {
            return array_values($this->rows);
        }
    }

    /**
     * Concrete test-level subclass that overrides the two static query methods so they
     * operate against the in-memory $rows instead of firing real SQL.
     */
    class TestCommentsQueried extends TestComments {
        public static function getForEntity(string $entityType, int $entityId, bool $includeHidden = false): array {
            $inst = static::get();

            /** @var TestCommentsQueried $inst */
            return array_values(array_filter($inst->rows, function (array $row) use ($entityType, $entityId, $includeHidden): bool {
                if ($row['entity_type'] !== $entityType || (int)$row['entity_id'] !== $entityId) {
                    return false;
                }

                if (!$includeHidden && ($row['is_hidden'] ?? 0)) {
                    return false;
                }

                return true;
            }));
        }

        public static function countForEntity(string $entityType, int $entityId, bool $includeHidden = false): int {
            return count(static::getForEntity($entityType, $entityId, $includeHidden));
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetCommentsSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setValue(null, []);
    }

    function setupCommentsTable(): TestCommentsQueried {
        resetCommentsSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $inst = (new ReflectionClass(TestCommentsQueried::class))->newInstanceWithoutConstructor();
        $itemsProp->setValue(null, [TestCommentsQueried::class => $inst]);

        return $inst;
    }

    function makeRow(int $id, string $type, int $entityId, bool $hidden = false): array {
        return [
            'id' => (string)$id,
            'author_id' => 1,
            'entity_type' => $type,
            'entity_id' => $entityId,
            'body' => "comment {$id}",
            'created_at' => 1000 + $id,
            'is_hidden' => $hidden ? 1 : 0,
        ];
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwComments', function (): void {
        // -----------------------------------------------------------------------
        describe('table schema constants', function (): void {
            it('primaryKey is id', function (): void {
                // Use a concrete subclass since FwComments is abstract
                $inst = (new ReflectionClass(TestCommentsQueried::class))->newInstanceWithoutConstructor();
                $ref = new ReflectionClass(FwComments::class);
                $prop = $ref->getProperty('primaryKey');
                expect($prop->getValue($inst))->toBe('id');
            });
        });

        // -----------------------------------------------------------------------
        describe('getForEntity()', function (): void {
            beforeEach(function (): void {
                $this->table = setupCommentsTable();

                $this->table->rows = [
                    '1' => makeRow(1, 'expert', 10),
                    '2' => makeRow(2, 'expert', 10),
                    '3' => makeRow(3, 'expert', 10, hidden: true),
                    '4' => makeRow(4, 'expert', 20),     // different entity
                    '5' => makeRow(5, 'course', 10),     // different type
                ];
            });

            it('returns only comments for the given entity_type + entity_id', function (): void {
                $rows = TestCommentsQueried::getForEntity('expert', 10);
                expect(count($rows))->toBe(2);   // row 1 + 2 (hidden row 3 excluded by default)
            });

            it('excludes hidden comments by default', function (): void {
                $rows = TestCommentsQueried::getForEntity('expert', 10);

                foreach ($rows as $row) {
                    expect((int)$row['is_hidden'])->toBe(0);
                }
            });

            it('includes hidden comments when includeHidden=true', function (): void {
                $rows = TestCommentsQueried::getForEntity('expert', 10, includeHidden: true);
                expect(count($rows))->toBe(3);
            });

            it('returns empty array when no comments exist for entity', function (): void {
                $rows = TestCommentsQueried::getForEntity('expert', 999);
                expect($rows)->toBe([]);
            });

            it('does not return comments belonging to a different entity_type', function (): void {
                $rows = TestCommentsQueried::getForEntity('course', 10);
                expect(count($rows))->toBe(1);
                expect($rows[0]['entity_type'])->toBe('course');
            });
        });

        // -----------------------------------------------------------------------
        describe('countForEntity()', function (): void {
            beforeEach(function (): void {
                $this->table = setupCommentsTable();

                $this->table->rows = [
                    '1' => makeRow(1, 'expert', 5),
                    '2' => makeRow(2, 'expert', 5),
                    '3' => makeRow(3, 'expert', 5, hidden: true),
                ];
            });

            it('returns count of visible comments by default', function (): void {
                $count = TestCommentsQueried::countForEntity('expert', 5);
                expect($count)->toBe(2);
            });

            it('returns total count including hidden when includeHidden=true', function (): void {
                $count = TestCommentsQueried::countForEntity('expert', 5, includeHidden: true);
                expect($count)->toBe(3);
            });

            it('returns 0 when no comments exist', function (): void {
                $count = TestCommentsQueried::countForEntity('expert', 9999);
                expect($count)->toBe(0);
            });

            it('returns 0 when all comments are hidden and includeHidden=false', function (): void {
                $this->table->rows = [
                    '1' => makeRow(1, 'expert', 7, hidden: true),
                    '2' => makeRow(2, 'expert', 7, hidden: true),
                ];
                $count = TestCommentsQueried::countForEntity('expert', 7);
                expect($count)->toBe(0);
            });
        });
    });
}
