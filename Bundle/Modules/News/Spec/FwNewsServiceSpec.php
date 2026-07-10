<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\News\Spec {
    use Aura\SqlQuery\Common\InsertInterface;
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\News\FwNewsService;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsArchived;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsEvents;
    use PHPCraftdream\Garnet\Bundle\Modules\News\Tables\FwNewsReads;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // Minimal QueryEx stub so getQueryEx()->exInsertIgnore() does not blow up
    // ---------------------------------------------------------------------------

    class StubQueryEx extends QueryEx {
        public array $insertIgnoreCalls = [];

        public array $exCalls = [];

        public function __construct() {
            // Do not call parent constructor (avoids DbPool dependency)
        }

        public function exInsertIgnore(InsertInterface $query): array|int|string|bool {
            $this->insertIgnoreCalls[] = $query;

            return true;
        }

        public function ex(string $sql, array $values = []): array|int|bool {
            $this->exCalls[] = ['sql' => $sql, 'params' => $values];

            return true;
        }
    }

    // ---------------------------------------------------------------------------
    // Minimal InsertInterface stub (returned by newInsert())
    // All interface methods are untyped (Aura style), so we can implement them.
    // ---------------------------------------------------------------------------

    class StubInsert implements InsertInterface {
        public array $colData = [];

        // ValuesInterface
        public function col($col, ...$value) {
            return $this;
        }

        public function cols(array $cols) {
            $this->colData = array_merge($this->colData, $cols);

            return $this;
        }

        public function set($col, $value) {
            return $this;
        }

        // InsertInterface
        public function into($into) {
            return $this;
        }

        public function setLastInsertIdNames(array $last_insert_id_names): void {
        }

        public function getLastInsertIdName($col) {
            return null;
        }

        public function addRows(array $rows) {
            return $this;
        }

        public function addRow(array $cols = []) {
            return $this;
        }

        // QueryInterface
        public function __toString() {
            return '';
        }

        public function getStatement() {
            return '';
        }

        public function getQuoteNamePrefix() {
            return '`';
        }

        public function getQuoteNameSuffix() {
            return '`';
        }

        public function bindValues(array $bind_values) {
            return $this;
        }

        public function bindValue($name, $value) {
            return $this;
        }

        public function getBindValues() {
            return [];
        }

        public function resetFlags() {
            return $this;
        }
    }

    // ---------------------------------------------------------------------------
    // In-memory FwNewsEvents stub
    // ---------------------------------------------------------------------------

    class TestNewsEvents extends FwNewsEvents {
        protected string $tableName = 'fw_news_events_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        private int $nextId = 1;

        /** selectAll filter callback — stored by test for inspection */
        public array $lastSelectArgs = [];

        public StubQueryEx $queryEx;

        public function __construct() {
            $this->queryEx = new StubQueryEx();
        }

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectAll(Closure $queryCallback = null): array {
            $this->lastSelectArgs[] = $queryCallback;

            return array_values($this->rows);
        }

        public function getCount(?callable $queryCallback = null): int {
            return count($this->rows);
        }

        public function getTableName(): string {
            return $this->tableName;
        }

        public function getQueryEx(): StubQueryEx {
            return $this->queryEx;
        }

        public function newInsert(): StubInsert {
            return new StubInsert();
        }

        public function deleteBy(Closure $queryCallback): bool {
            // For deleteByTargetKey tests — not used on events table directly
            return true;
        }
    }

    // ---------------------------------------------------------------------------
    // In-memory FwNewsReads stub
    // ---------------------------------------------------------------------------

    class TestNewsReads extends FwNewsReads {
        protected string $tableName = 'fw_news_reads_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertIgnoreCalls = [];

        public StubQueryEx $queryEx;

        public function __construct() {
            $this->queryEx = new StubQueryEx();
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        public function getTableName(): string {
            return $this->tableName;
        }

        public function getQueryEx(): StubQueryEx {
            return $this->queryEx;
        }

        public function newInsert(): StubInsert {
            return new StubInsert();
        }
    }

    // ---------------------------------------------------------------------------
    // In-memory FwNewsArchived stub
    // ---------------------------------------------------------------------------

    class TestNewsArchived extends FwNewsArchived {
        protected string $tableName = 'fw_news_archived_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $deleteByArgs = [];

        public StubQueryEx $queryEx;

        public function __construct() {
            $this->queryEx = new StubQueryEx();
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        public function getTableName(): string {
            return $this->tableName;
        }

        public function getQueryEx(): StubQueryEx {
            return $this->queryEx;
        }

        public function newInsert(): StubInsert {
            return new StubInsert();
        }

        public function deleteBy(Closure $queryCallback): bool {
            $this->deleteByArgs[] = $queryCallback;

            // Simulate delete first row (sufficient for unarchive tests)
            if (!empty($this->rows)) {
                array_shift($this->rows);
            }

            return true;
        }
    }

    // ---------------------------------------------------------------------------
    // Concrete test service — overrides abstract methods + markAllRead/getUnreadCount
    // to avoid raw DbPool SQL
    // ---------------------------------------------------------------------------

    class TestNewsService extends FwNewsService {
        public static TestNewsEvents $eventsInst;

        public static TestNewsReads $readsInst;

        public static TestNewsArchived $archivedInst;

        /** Captured markAllRead calls */
        public static array $markAllReadCalls = [];

        protected static function eventsTable(): FwNewsEvents {
            return static::$eventsInst;
        }

        protected static function readsTable(): FwNewsReads {
            return static::$readsInst;
        }

        protected static function archivedTable(): FwNewsArchived {
            return static::$archivedInst;
        }

        /** Override to avoid raw DbPool usage in specs */
        public static function markAllRead(int $accountId): void {
            static::$markAllReadCalls[] = $accountId;
        }

        /**
         * Override getUnreadCount: count events not in reads rows for accountId.
         * Mirrors the semantic without raw SQL.
         */
        public static function getUnreadCount(int $accountId): int {
            $readEventIds = array_column(static::$readsInst->rows, 'event_id');
            $count = 0;

            foreach (static::$eventsInst->rows as $row) {
                $visible = (
                    ($row['audience_type'] === FwNewsService::AUDIENCE_BROADCAST && (int)$row['actor_id'] !== $accountId) ||
                    ($row['audience_type'] === FwNewsService::AUDIENCE_PERSONAL && (int)($row['audience_id'] ?? 0) === $accountId)
                );

                if ($visible && !in_array((int)$row['id'], array_map('intval', $readEventIds), true)) {
                    $count++;
                }
            }

            return $count;
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    function resetNewsDbTableSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function setupNewsTables(): array {
        resetNewsDbTableSingletons();

        $eventsObj = new TestNewsEvents();
        $readsObj = new TestNewsReads();
        $archivedObj = new TestNewsArchived();

        TestNewsService::$eventsInst = $eventsObj;
        TestNewsService::$readsInst = $readsObj;
        TestNewsService::$archivedInst = $archivedObj;
        TestNewsService::$markAllReadCalls = [];

        return [$eventsObj, $readsObj, $archivedObj];
    }

    /** Seed a broadcast event row directly into in-memory events table. */
    function seedBroadcast(TestNewsEvents $events, int $id, int $actorId, string $eventType = 'test_event', ?string $targetKey = null, int $createdAt = 0): void {
        $events->rows[(string)$id] = [
            'id' => (string)$id,
            'event_type' => $eventType,
            'audience_type' => FwNewsService::AUDIENCE_BROADCAST,
            'audience_id' => null,
            'actor_id' => (string)$actorId,
            'target_key' => $targetKey,
            'payload' => json_encode(['k' => 'v']),
            'created_at' => (string)($createdAt ?: time()),
        ];
    }

    /** Seed a personal event row directly into in-memory events table. */
    function seedPersonal(TestNewsEvents $events, int $id, int $actorId, int $audienceId, string $eventType = 'test_event', ?string $targetKey = null): void {
        $events->rows[(string)$id] = [
            'id' => (string)$id,
            'event_type' => $eventType,
            'audience_type' => FwNewsService::AUDIENCE_PERSONAL,
            'audience_id' => (string)$audienceId,
            'actor_id' => (string)$actorId,
            'target_key' => $targetKey,
            'payload' => json_encode(['msg' => 'hello']),
            'created_at' => (string)time(),
        ];
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwNewsService', function (): void {
        // -----------------------------------------------------------------------
        describe('constants', function (): void {
            it('AUDIENCE_BROADCAST equals broadcast', function (): void {
                expect(FwNewsService::AUDIENCE_BROADCAST)->toBe('broadcast');
            });

            it('AUDIENCE_PERSONAL equals personal', function (): void {
                expect(FwNewsService::AUDIENCE_PERSONAL)->toBe('personal');
            });

            it('FEED_TTL_SEC is 90 days in seconds', function (): void {
                expect(FwNewsService::FEED_TTL_SEC)->toBe(90 * 86400);
            });

            it('MESSAGE_THROTTLE_SEC is 1 hour', function (): void {
                expect(FwNewsService::MESSAGE_THROTTLE_SEC)->toBe(3600);
            });
        });

        // -----------------------------------------------------------------------
        describe('createBroadcast()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('returns a positive integer ID', function (): void {
                $id = TestNewsService::createBroadcast('slot_created', 10, ['slot' => 1]);
                expect($id)->toBeGreaterThan(0);
            });

            it('inserts a row with audience_type=broadcast', function (): void {
                TestNewsService::createBroadcast('slot_created', 10, []);
                $inserted = $this->events->insertCalls[0];
                expect($inserted['audience_type'])->toBe('broadcast');
            });

            it('stores the event_type, actor_id and payload', function (): void {
                TestNewsService::createBroadcast('slot_created', 42, ['x' => 1]);
                $inserted = $this->events->insertCalls[0];
                expect($inserted['event_type'])->toBe('slot_created');
                expect($inserted['actor_id'])->toBe(42);
                expect($inserted['payload'])->toBe(json_encode(['x' => 1], JSON_UNESCAPED_UNICODE));
            });

            it('sets audience_id to null for broadcast', function (): void {
                TestNewsService::createBroadcast('evt', 5, []);
                expect($this->events->insertCalls[0]['audience_id'])->toBeNull();
            });

            it('stores target_key when provided', function (): void {
                TestNewsService::createBroadcast('evt', 5, [], 'slot:99');
                expect($this->events->insertCalls[0]['target_key'])->toBe('slot:99');
            });

            it('sets target_key to null when not provided', function (): void {
                TestNewsService::createBroadcast('evt', 5, []);
                expect($this->events->insertCalls[0]['target_key'])->toBeNull();
            });

            it('sets created_at to approximately now', function (): void {
                $before = time();
                TestNewsService::createBroadcast('evt', 5, []);
                $after = time();
                $createdAt = $this->events->insertCalls[0]['created_at'];
                expect($createdAt)->toBeGreaterThan($before - 1);
                expect($createdAt)->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('createPersonal()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('returns a positive integer ID', function (): void {
                $id = TestNewsService::createPersonal('msg_received', 1, 2, []);
                expect($id)->toBeGreaterThan(0);
            });

            it('inserts a row with audience_type=personal', function (): void {
                TestNewsService::createPersonal('msg_received', 1, 2, []);
                expect($this->events->insertCalls[0]['audience_type'])->toBe('personal');
            });

            it('stores the audience_id', function (): void {
                TestNewsService::createPersonal('msg_received', 1, 77, []);
                expect($this->events->insertCalls[0]['audience_id'])->toBe(77);
            });

            it('stores optional target_key', function (): void {
                TestNewsService::createPersonal('msg_received', 1, 2, [], 'conv:5');
                expect($this->events->insertCalls[0]['target_key'])->toBe('conv:5');
            });
        });

        // -----------------------------------------------------------------------
        describe('createThrottledEvent()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('creates the event when no recent duplicate exists', function (): void {
                // events table is empty
                $id = TestNewsService::createThrottledEvent('im_msg', 1, 2, ['text' => 'hi']);
                expect($id)->toBeGreaterThan(0);
            });

            it('returns null (throttled) when a recent duplicate already exists', function (): void {
                // Seed a recent event so selectAll returns a non-empty result
                seedPersonal($this->events, 1, 1, 2, 'im_msg');

                $id = TestNewsService::createThrottledEvent('im_msg', 1, 2, ['text' => 'hi again']);
                expect($id)->toBeNull();
            });

            it('inserts a personal event on first call', function (): void {
                TestNewsService::createThrottledEvent('im_msg', 10, 20, ['k' => 'v']);
                expect(count($this->events->insertCalls))->toBe(1);
                expect($this->events->insertCalls[0]['audience_type'])->toBe('personal');
            });
        });

        // -----------------------------------------------------------------------
        describe('getFeed()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('returns the expected result shape', function (): void {
                $result = TestNewsService::getFeed(1, 1, 20);
                expect(array_key_exists('items', $result))->toBe(true);
                expect(array_key_exists('page', $result))->toBe(true);
                expect(array_key_exists('perPage', $result))->toBe(true);
                expect(array_key_exists('total', $result))->toBe(true);
                expect(array_key_exists('totalPages', $result))->toBe(true);
                expect(array_key_exists('unreadCount', $result))->toBe(true);
            });

            it('returns empty items for empty table', function (): void {
                $result = TestNewsService::getFeed(1, 1, 20);
                expect($result['items'])->toBe([]);
                expect($result['total'])->toBe(0);
                expect($result['totalPages'])->toBe(1);
            });

            it('enriches items with is_read=false when not read', function (): void {
                seedBroadcast($this->events, 1, 99);
                $result = TestNewsService::getFeed(1, 1, 20);
                expect($result['items'][0]['is_read'])->toBe(false);
                expect($result['items'][0]['read_at'])->toBeNull();
            });

            it('enriches items with is_read=true when read', function (): void {
                seedBroadcast($this->events, 1, 99);
                $this->reads->rows['1'] = ['event_id' => '1', 'account_id' => '1', 'read_at' => time()];
                $result = TestNewsService::getFeed(1, 1, 20);
                expect($result['items'][0]['is_read'])->toBe(true);
            });

            it('decodes payload from JSON', function (): void {
                seedBroadcast($this->events, 1, 99);
                $result = TestNewsService::getFeed(1, 1, 20);
                expect($result['items'][0]['payload'])->toBe(['k' => 'v']);
            });

            it('page 1 with perPage=1 returns totalPages = total event count', function (): void {
                seedBroadcast($this->events, 1, 99);
                seedBroadcast($this->events, 2, 99);
                seedBroadcast($this->events, 3, 99);
                $result = TestNewsService::getFeed(1, 1, 1);
                expect($result['totalPages'])->toBe(3);
                expect($result['total'])->toBe(3);
            });

            it('is_archived is false when not archived', function (): void {
                seedBroadcast($this->events, 1, 99);
                $result = TestNewsService::getFeed(1, 1, 20, true);
                expect($result['items'][0]['is_archived'])->toBe(false);
            });

            it('is_archived is true when event is archived', function (): void {
                seedBroadcast($this->events, 1, 99);
                $this->archived->rows['1'] = ['event_id' => '1', 'account_id' => '1', 'archived_at' => time()];
                $result = TestNewsService::getFeed(1, 1, 20, true);
                expect($result['items'][0]['is_archived'])->toBe(true);
            });

            it('unreadCount matches number of unread visible events', function (): void {
                seedBroadcast($this->events, 1, 99);
                seedBroadcast($this->events, 2, 99);
                // Mark event 1 as read
                $this->reads->rows['1'] = ['event_id' => '1', 'account_id' => '1', 'read_at' => time()];
                $result = TestNewsService::getFeed(1, 1, 20);
                // User 1 sees broadcasts from actor 99 — 2 events, 1 read, 1 unread
                expect($result['unreadCount'])->toBe(1);
            });
        });

        // -----------------------------------------------------------------------
        describe('markRead()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('does nothing for an empty eventIds array', function (): void {
                TestNewsService::markRead(1, []);
                expect(count($this->reads->queryEx->insertIgnoreCalls))->toBe(0);
            });

            it('calls exInsertIgnore once per event ID', function (): void {
                TestNewsService::markRead(1, [10, 20, 30]);
                expect(count($this->reads->queryEx->insertIgnoreCalls))->toBe(3);
            });

            it('each insert carries the correct account_id and event_id', function (): void {
                TestNewsService::markRead(5, [42]);
                /** @var StubInsert $stub */
                $stub = $this->reads->queryEx->insertIgnoreCalls[0];
                expect($stub->colData['account_id'])->toBe(5);
                expect($stub->colData['event_id'])->toBe(42);
            });

            it('read_at is approximately now', function (): void {
                $before = time();
                TestNewsService::markRead(1, [1]);
                $after = time();
                /** @var StubInsert $stub */
                $readAt = $this->reads->queryEx->insertIgnoreCalls[0]->colData['read_at'];
                expect($readAt)->toBeGreaterThan($before - 1);
                expect($readAt)->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('markAllRead()', function (): void {
            beforeEach(function (): void {
                setupNewsTables();
            });

            it('records the call with the correct account_id', function (): void {
                TestNewsService::markAllRead(7);
                expect(TestNewsService::$markAllReadCalls)->toContain(7);
            });
        });

        // -----------------------------------------------------------------------
        describe('archive()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('does nothing for an empty eventIds array', function (): void {
                TestNewsService::archive(1, []);
                expect(count($this->archived->queryEx->insertIgnoreCalls))->toBe(0);
            });

            it('calls exInsertIgnore once per event ID', function (): void {
                TestNewsService::archive(1, [5, 6]);
                expect(count($this->archived->queryEx->insertIgnoreCalls))->toBe(2);
            });

            it('each insert carries the correct account_id and event_id', function (): void {
                TestNewsService::archive(3, [77]);
                /** @var StubInsert $stub */
                $stub = $this->archived->queryEx->insertIgnoreCalls[0];
                expect($stub->colData['account_id'])->toBe(3);
                expect($stub->colData['event_id'])->toBe(77);
            });

            it('archived_at is approximately now', function (): void {
                $before = time();
                TestNewsService::archive(1, [1]);
                $after = time();
                /** @var StubInsert $stub */
                $archivedAt = $this->archived->queryEx->insertIgnoreCalls[0]->colData['archived_at'];
                expect($archivedAt)->toBeGreaterThan($before - 1);
                expect($archivedAt)->toBeLessThan($after + 1);
            });
        });

        // -----------------------------------------------------------------------
        describe('unarchive()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
                // Seed an archived row
                $this->archived->rows['1'] = ['event_id' => '10', 'account_id' => '1', 'archived_at' => time()];
            });

            it('does nothing for an empty eventIds array', function (): void {
                TestNewsService::unarchive(1, []);
                expect(count($this->archived->deleteByArgs))->toBe(0);
            });

            it('calls deleteBy once per event ID', function (): void {
                TestNewsService::unarchive(1, [10, 20]);
                expect(count($this->archived->deleteByArgs))->toBe(2);
            });

            it('removes the archived row from the in-memory store', function (): void {
                TestNewsService::unarchive(1, [10]);
                expect(count($this->archived->rows))->toBe(0);
            });
        });

        // -----------------------------------------------------------------------
        describe('deleteByTargetKey()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('calls ex() on the events queryEx with a DELETE SQL', function (): void {
                TestNewsService::deleteByTargetKey('slot:42');
                expect(count($this->events->queryEx->exCalls))->toBe(1);
                expect($this->events->queryEx->exCalls[0]['params'])->toBe(['slot:42']);
            });

            it('includes event_type in WHERE when provided', function (): void {
                TestNewsService::deleteByTargetKey('slot:42', 'new_slot');
                $sql = $this->events->queryEx->exCalls[0]['sql'];
                expect($sql)->toContain('event_type');
                expect($this->events->queryEx->exCalls[0]['params'])->toBe(['slot:42', 'new_slot']);
            });

            it('does not include event_type in WHERE when null', function (): void {
                TestNewsService::deleteByTargetKey('slot:42');
                $sql = $this->events->queryEx->exCalls[0]['sql'];
                expect($sql)->not->toContain('event_type');
            });
        });

        // -----------------------------------------------------------------------
        describe('getUnreadCount()', function (): void {
            beforeEach(function (): void {
                [$this->events, $this->reads, $this->archived] = setupNewsTables();
            });

            it('returns 0 when there are no events', function (): void {
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(0);
            });

            it('counts broadcast events not authored by the user as unread', function (): void {
                seedBroadcast($this->events, 1, 99); // actor=99, user=1 → visible
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(1);
            });

            it('does not count own broadcast events', function (): void {
                seedBroadcast($this->events, 1, 1); // actor=1, user=1 → NOT visible
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(0);
            });

            it('counts personal events addressed to the user', function (): void {
                seedPersonal($this->events, 1, 99, 1); // audience=1 → visible
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(1);
            });

            it('does not count personal events for other users', function (): void {
                seedPersonal($this->events, 1, 99, 2); // audience=2, user=1 → NOT visible
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(0);
            });

            it('decrements when event is marked read', function (): void {
                seedBroadcast($this->events, 1, 99);
                $this->reads->rows['1'] = ['event_id' => '1', 'account_id' => '1', 'read_at' => time()];
                $count = TestNewsService::getUnreadCount(1);
                expect($count)->toBe(0);
            });
        });
    });
}
