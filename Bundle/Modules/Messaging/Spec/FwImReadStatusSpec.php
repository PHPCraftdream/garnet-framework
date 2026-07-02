<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Spec {
    use Aura\SqlQuery\Common\SelectInterface;
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImConversations;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImMessages;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImReadStatus;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // No-op base adapter implementing SelectInterface (all return $this or stub)
    // ---------------------------------------------------------------------------

    abstract class BaseRsSelectAdapter implements SelectInterface {
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
    // Adapters for each table
    // ---------------------------------------------------------------------------

    class RsReadStatusAdapter extends BaseRsSelectAdapter {
        private array $allRows;

        private array $conditions = [];

        public function __construct(array $rows) {
            $this->allRows = array_values($rows);
        }

        public function where($cond, array $bind = []): static {
            $this->conditions[] = ['cond' => $cond, 'bind' => $bind];

            return $this;
        }

        public function getResults(): array {
            $rows = $this->allRows;

            foreach ($this->conditions as $c) {
                $cond = $c['cond'];
                $bind = $c['bind'];

                if (preg_match('/conversation_id\s*=\s*\?\s*AND\s*account_id\s*=\s*\?/i', $cond)) {
                    $convId = (int)$bind[0];
                    $accountId = (int)$bind[1];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['conversation_id'] === $convId && (int)$r['account_id'] === $accountId
                    ));
                } elseif (preg_match('/account_id\s*=\s*\?/i', $cond)) {
                    $accountId = (int)$bind[0];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['account_id'] === $accountId
                    ));
                }
            }

            return $rows;
        }
    }

    class RsConvsAdapter extends BaseRsSelectAdapter {
        private array $allRows;

        private array $conditions = [];

        public function __construct(array $rows) {
            $this->allRows = array_values($rows);
        }

        public function where($cond, array $bind = []): static {
            $this->conditions[] = ['cond' => $cond, 'bind' => $bind];

            return $this;
        }

        public function getResults(): array {
            $rows = $this->allRows;

            foreach ($this->conditions as $c) {
                $cond = $c['cond'];
                $bind = $c['bind'];

                if (preg_match('/participant_a\s*=\s*\?\s*OR\s*participant_b\s*=\s*\?/i', $cond)) {
                    $id = (int)$bind[0];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['participant_a'] === $id || (int)$r['participant_b'] === $id
                    ));
                }
            }

            return $rows;
        }
    }

    class RsMsgsAdapter extends BaseRsSelectAdapter {
        private array $allRows;

        private array $conditions = [];

        private bool $countMode = false;

        public function __construct(array $rows) {
            $this->allRows = array_values($rows);
        }

        public function where($cond, array $bind = []): static {
            $this->conditions[] = ['cond' => $cond, 'bind' => $bind];

            return $this;
        }

        public function resetCols(): static {
            return $this;
        }

        public function cols(array $cols): static {
            if (in_array('COUNT(*) as cnt', $cols, true)) {
                $this->countMode = true;
            }

            return $this;
        }

        public function getResults(): array {
            $rows = $this->allRows;

            foreach ($this->conditions as $c) {
                $cond = $c['cond'];
                $bind = $c['bind'];

                // "conversation_id = ? AND sender_id != ? AND id > ?"
                if (preg_match('/conversation_id\s*=\s*\?\s*AND\s*sender_id\s*!=\s*\?\s*AND\s*id\s*>\s*\?/i', $cond)) {
                    $convId = (int)$bind[0];
                    $senderId = (int)$bind[1];
                    $lastRead = (int)$bind[2];
                    $rows = array_values(array_filter($rows, fn ($r) => (int)$r['conversation_id'] === $convId &&
                        (int)$r['sender_id'] !== $senderId &&
                        (int)$r['id'] > $lastRead
                    ));
                }
            }

            if ($this->countMode) {
                return [['cnt' => count($rows)]];
            }

            return $rows;
        }
    }

    // ---------------------------------------------------------------------------
    // In-memory table stubs
    // ---------------------------------------------------------------------------

    class TestImReadStatus2 extends FwImReadStatus {
        protected string $tableName = 'fw_im_read_status_test2';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        protected static function conversationsTableClass(): string {
            return TestReadConversations2::class;
        }

        protected static function messagesTableClass(): string {
            return TestReadMessages2::class;
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

        public function updateByField(array $data, string $field, mixed $value): bool {
            $this->updateCalls[] = ['data' => $data, 'field' => $field, 'value' => $value];

            foreach ($this->rows as &$row) {
                if (isset($row[$field]) && (string)$row[$field] === (string)$value) {
                    $row = array_merge($row, $data);
                }
            }
            unset($row);

            return true;
        }

        public function selectAll(Closure $queryCallback = null): array {
            if ($queryCallback === null) {
                return array_values($this->rows);
            }
            $adapter = new RsReadStatusAdapter($this->rows);
            $queryCallback($adapter);

            return $adapter->getResults();
        }
    }

    class TestReadConversations2 extends FwImConversations {
        protected string $tableName = 'fw_im_read_convs_test2';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public function selectAll(Closure $queryCallback = null): array {
            if ($queryCallback === null) {
                return array_values($this->rows);
            }
            $adapter = new RsConvsAdapter($this->rows);
            $queryCallback($adapter);

            return $adapter->getResults();
        }
    }

    class TestReadMessages2 extends FwImMessages {
        protected string $tableName = 'fw_im_read_msgs_test2';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public function selectAll(Closure $queryCallback = null): array {
            if ($queryCallback === null) {
                return array_values($this->rows);
            }
            $adapter = new RsMsgsAdapter($this->rows);
            $queryCallback($adapter);

            return $adapter->getResults();
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetReadStatusSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    function setupReadStatusTables(): array {
        resetReadStatusSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');
        $itemsProp->setAccessible(true);

        $rsInst = (new ReflectionClass(TestImReadStatus2::class))->newInstanceWithoutConstructor();
        $convInst = (new ReflectionClass(TestReadConversations2::class))->newInstanceWithoutConstructor();
        $msgInst = (new ReflectionClass(TestReadMessages2::class))->newInstanceWithoutConstructor();

        $itemsProp->setValue([
            TestImReadStatus2::class => $rsInst,
            TestReadConversations2::class => $convInst,
            TestReadMessages2::class => $msgInst,
        ]);

        return [$rsInst, $convInst, $msgInst];
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwImReadStatus', function (): void {
        // -----------------------------------------------------------------------
        describe('markRead() — new record insertion', function (): void {
            beforeEach(function (): void {
                [$this->rs, $this->convs, $this->msgs] = setupReadStatusTables();
            });

            it('inserts a new read-status record when none exists', function (): void {
                TestImReadStatus2::markRead(1, 42, 100);
                expect(count($this->rs->insertCalls))->toBe(1);
                $ins = $this->rs->insertCalls[0];
                expect($ins['conversation_id'])->toBe(1);
                expect($ins['account_id'])->toBe(42);
                expect($ins['last_read_message_id'])->toBe(100);
            });

            it('stores a non-zero updated_at timestamp', function (): void {
                $before = time();
                TestImReadStatus2::markRead(1, 42, 100);
                $after = time();
                $ins = $this->rs->insertCalls[0];
                expect($ins['updated_at'])->toBeGreaterThan($before - 1);
                expect($ins['updated_at'])->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('markRead() — update existing record', function (): void {
            beforeEach(function (): void {
                [$this->rs, $this->convs, $this->msgs] = setupReadStatusTables();

                // Seed an existing read-status record
                $this->rs->rows['1'] = [
                    'id' => '1',
                    'conversation_id' => '5',
                    'account_id' => '10',
                    'last_read_message_id' => '50',
                    'updated_at' => time() - 60,
                ];
            });

            it('updates last_read_message_id when new value is higher', function (): void {
                TestImReadStatus2::markRead(5, 10, 75);
                expect(count($this->rs->updateCalls))->toBe(1);
                $update = $this->rs->updateCalls[0];
                expect($update['data']['last_read_message_id'])->toBe(75);
            });

            it('does NOT update when new message ID equals existing', function (): void {
                TestImReadStatus2::markRead(5, 10, 50);
                expect(count($this->rs->updateCalls))->toBe(0);
            });

            it('does NOT update when new message ID is lower than existing', function (): void {
                TestImReadStatus2::markRead(5, 10, 30);
                expect(count($this->rs->updateCalls))->toBe(0);
            });

            it('does NOT insert a new row when updating', function (): void {
                TestImReadStatus2::markRead(5, 10, 75);
                expect(count($this->rs->insertCalls))->toBe(0);
            });
        });

        // -----------------------------------------------------------------------
        describe('getUnreadCountForUser() — count logic', function (): void {
            beforeEach(function (): void {
                [$this->rs, $this->convs, $this->msgs] = setupReadStatusTables();
            });

            it('returns 0 when user has no conversations', function (): void {
                $count = TestImReadStatus2::getUnreadCountForUser(99);
                expect($count)->toBe(0);
            });

            it('returns 0 when all messages are from self', function (): void {
                $this->convs->rows['1'] = ['id' => '10', 'participant_a' => '1', 'participant_b' => '2'];

                $this->msgs->rows['1'] = ['id' => '1', 'conversation_id' => '10', 'sender_id' => '1', 'body' => 'hi', 'created_at' => time()];
                $this->msgs->rows['2'] = ['id' => '2', 'conversation_id' => '10', 'sender_id' => '1', 'body' => 'yo', 'created_at' => time()];

                $count = TestImReadStatus2::getUnreadCountForUser(1);
                expect($count)->toBe(0);
            });

            it('counts messages from partner that are after last_read_message_id', function (): void {
                $this->convs->rows['1'] = ['id' => '10', 'participant_a' => '1', 'participant_b' => '2'];

                $this->rs->rows['1'] = [
                    'id' => '1', 'conversation_id' => '10',
                    'account_id' => '1', 'last_read_message_id' => '5', 'updated_at' => time(),
                ];

                for ($i = 6; $i <= 8; $i++) {
                    $this->msgs->rows[(string)$i] = [
                        'id' => (string)$i, 'conversation_id' => '10',
                        'sender_id' => '2', 'body' => 'msg', 'created_at' => time(),
                    ];
                }

                $count = TestImReadStatus2::getUnreadCountForUser(1);
                expect($count)->toBe(3);
            });

            it('returns 0 when user has read all messages (lastRead >= all message IDs)', function (): void {
                $this->convs->rows['1'] = ['id' => '10', 'participant_a' => '1', 'participant_b' => '2'];

                $this->rs->rows['1'] = [
                    'id' => '1', 'conversation_id' => '10',
                    'account_id' => '1', 'last_read_message_id' => '20', 'updated_at' => time(),
                ];

                $this->msgs->rows['10'] = ['id' => '10', 'conversation_id' => '10', 'sender_id' => '2', 'body' => 'x', 'created_at' => time()];
                $this->msgs->rows['20'] = ['id' => '20', 'conversation_id' => '10', 'sender_id' => '2', 'body' => 'y', 'created_at' => time()];

                $count = TestImReadStatus2::getUnreadCountForUser(1);
                expect($count)->toBe(0);
            });

            it('sums unread across multiple conversations', function (): void {
                $this->convs->rows['1'] = ['id' => '10', 'participant_a' => '1', 'participant_b' => '2'];
                $this->convs->rows['2'] = ['id' => '11', 'participant_a' => '1', 'participant_b' => '3'];

                // No read status (never opened) — lastRead defaults to 0
                // 2 unread in conversation 10 (from user 2)
                $this->msgs->rows['1'] = ['id' => '1', 'conversation_id' => '10', 'sender_id' => '2', 'body' => 'a', 'created_at' => time()];
                $this->msgs->rows['2'] = ['id' => '2', 'conversation_id' => '10', 'sender_id' => '2', 'body' => 'b', 'created_at' => time()];
                // 1 unread in conversation 11 (from user 3)
                $this->msgs->rows['3'] = ['id' => '3', 'conversation_id' => '11', 'sender_id' => '3', 'body' => 'c', 'created_at' => time()];

                $count = TestImReadStatus2::getUnreadCountForUser(1);
                expect($count)->toBe(3);
            });
        });
    });
}
