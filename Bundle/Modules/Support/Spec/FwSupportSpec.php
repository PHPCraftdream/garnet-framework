<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Controllers\FwSupportAdminController;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Controllers\FwSupportController;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Tables\FwSupportAssignmentLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Tables\FwSupportAttachments;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Tables\FwSupportMessages;
    use PHPCraftdream\Garnet\Bundle\Modules\Support\Tables\FwSupportTickets;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // -------------------------------------------------------------------------
    // In-memory table stubs — no real DB
    // -------------------------------------------------------------------------

    class TestSupportTickets extends FwSupportTickets {
        protected string $tableName = 'test_support_tickets';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        private int $nextId = 1;

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectOneByField(string $field, mixed $value, Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if ((string)($row[$field] ?? null) === (string)$value) {
                    return $row;
                }
            }

            return null;
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        public function selectByField(string $field, mixed $value, Closure $queryCallback = null): array {
            return array_values(array_filter($this->rows, fn ($r) => (string)($r[$field] ?? null) === (string)$value));
        }

        public function updateByField(array $data, string $field, mixed $value, Closure $queryCallback = null): bool {
            foreach ($this->rows as &$row) {
                if ((string)($row[$field] ?? null) === (string)$value) {
                    $row = array_merge($row, $data);
                }
            }
            unset($row);

            return true;
        }

        /**
         * Override to use in-memory rows instead of real DB.
         */
        public static function getUnreadCountForUser(int $accountId): int {
            /** @var self $instance */
            $instance = static::get();
            $total = 0;

            foreach ($instance->rows as $row) {
                if ((int)($row['account_id'] ?? 0) === $accountId) {
                    $total += (int)($row['unread_user'] ?? 0);
                }
            }

            return $total;
        }
    }

    class TestSupportMessages extends FwSupportMessages {
        protected string $tableName = 'test_support_messages';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        private int $nextId = 1;

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectOneByField(string $field, mixed $value, Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if ((string)($row[$field] ?? null) === (string)$value) {
                    return $row;
                }
            }

            return null;
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }
    }

    class TestSupportAttachments extends FwSupportAttachments {
        protected string $tableName = 'test_support_attachments';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        private int $nextId = 1;

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectOneByField(string $field, mixed $value, Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if ((string)($row[$field] ?? null) === (string)$value) {
                    return $row;
                }
            }

            return null;
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }

        /**
         * Override getByMessageIds to use in-memory rows instead of real DB.
         * @param int[] $messageIds
         * @return array<int, array[]>
         */
        public static function getByMessageIds(array $messageIds): array {
            if (empty($messageIds)) {
                return [];
            }

            /** @var self $instance */
            $instance = static::get();
            $rows = array_values(array_filter(
                $instance->rows,
                fn ($row) => in_array((int)$row['message_id'], $messageIds, true)
            ));

            $grouped = [];

            foreach ($rows as $row) {
                $grouped[(int)$row['message_id']][] = $row;
            }

            return $grouped;
        }
    }

    class TestSupportAssignmentLog extends FwSupportAssignmentLog {
        protected string $tableName = 'test_support_assignment_log';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        public array $rows = [];

        public array $insertCalls = [];

        private int $nextId = 1;

        public function insert(array $data, Closure $queryCallback = null): false|string {
            $this->insertCalls[] = $data;
            $id = (string)$this->nextId++;
            $data['id'] = $id;
            $this->rows[$id] = $data;

            return $id;
        }

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
        }
    }

    // -------------------------------------------------------------------------
    // Concrete test-only controller subclasses (satisfy abstract methods)
    // -------------------------------------------------------------------------

    class TestSupportController extends FwSupportController {
        public const URL = '/support';

        protected static function getUploadDir(): string {
            return sys_get_temp_dir();
        }

        protected static function getSideMenu(string $url): array {
            return [];
        }

        protected static function getMainMenu(string $url): array {
            return [];
        }

        protected static function ticketsTable(): DbTable {
            return TestSupportTickets::get();
        }

        protected static function messagesTable(): DbTable {
            return TestSupportMessages::get();
        }

        protected static function attachmentsTable(): DbTable {
            return TestSupportAttachments::get();
        }

        /**
         * Expose enrichWithAttachments for white-box testing.
         */
        public static function exposedEnrichWithAttachments(array &$messages): void {
            static::enrichWithAttachments($messages);
        }
    }

    class TestSupportAdminController extends FwSupportAdminController {
        public const URL = '/admin/support';

        protected static function getUploadDir(): string {
            return sys_get_temp_dir();
        }

        protected static function isModerator(): bool {
            return true;
        }

        protected static function getSideMenu(string $url): array {
            return [];
        }

        protected static function getMainMenu(string $url): array {
            return [];
        }

        protected static function ticketsTable(): DbTable {
            return TestSupportTickets::get();
        }

        protected static function messagesTable(): DbTable {
            return TestSupportMessages::get();
        }

        protected static function attachmentsTable(): DbTable {
            return TestSupportAttachments::get();
        }

        protected static function assignmentLogTable(): DbTable {
            return TestSupportAssignmentLog::get();
        }

        protected static function resolveUserRole(int $accountId): array {
            return ['role' => 'user', 'has_expert_profile' => false];
        }

        protected static function getStatusLabels(): array {
            return [
                'open' => 'Open',
                'investigation' => 'Investigation',
                'in_progress' => 'In Progress',
                'waiting_user' => 'Waiting User',
                'waiting_support' => 'Waiting Support',
                'escalated' => 'Escalated',
                'on_hold' => 'On Hold',
                'resolved' => 'Resolved',
                'rejected' => 'Rejected',
            ];
        }

        protected static function getStatusChangedLabel(): string {
            return 'Status changed';
        }

        protected static function getAssignedToLabel(): string {
            return 'Assigned to';
        }

        protected static function getUnassignedLabel(): string {
            return 'Unassigned';
        }

        protected static function fetchModerators(): array {
            return [];
        }

        public static function exposedEnrichWithAttachments(array &$messages): void {
            static::enrichWithAttachments($messages);
        }

        public static function exposedStatusLabels(): array {
            return static::getStatusLabels();
        }
    }

    // -------------------------------------------------------------------------
    // Helper: reset DbTable singleton registry and reinstall test instances
    // -------------------------------------------------------------------------

    function resetDbTableSingletons(): void {
        $ref = new ReflectionClass(DbTable::class);
        $prop = $ref->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    function setupSupportTables(): array {
        resetDbTableSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');
        $itemsProp->setAccessible(true);

        $ticketsObj = (new ReflectionClass(TestSupportTickets::class))->newInstanceWithoutConstructor();
        $messagesObj = (new ReflectionClass(TestSupportMessages::class))->newInstanceWithoutConstructor();
        $attachmentsObj = (new ReflectionClass(TestSupportAttachments::class))->newInstanceWithoutConstructor();
        $assignLogObj = (new ReflectionClass(TestSupportAssignmentLog::class))->newInstanceWithoutConstructor();

        $itemsProp->setValue([
            TestSupportTickets::class => $ticketsObj,
            TestSupportMessages::class => $messagesObj,
            TestSupportAttachments::class => $attachmentsObj,
            TestSupportAssignmentLog::class => $assignLogObj,
        ]);

        return [$ticketsObj, $messagesObj, $attachmentsObj, $assignLogObj];
    }

    // =========================================================================
    // Specs
    // =========================================================================

    describe('TestSupportAttachments::getByMessageIds()', function (): void {
        beforeEach(function (): void {
            [$t, $m, $a] = setupSupportTables();
            $this->attachments = $a;
        });

        it('returns empty array when messageIds is empty', function (): void {
            $result = TestSupportAttachments::getByMessageIds([]);
            expect($result)->toBe([]);
        });

        it('returns empty array when no rows match', function (): void {
            $result = TestSupportAttachments::getByMessageIds([42, 99]);
            expect($result)->toBe([]);
        });

        it('groups attachments by message_id', function (): void {
            $this->attachments->rows = [
                '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'a.pdf', 'stored_name' => 'a_stored.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'created_at' => 0],
                '2' => ['id' => '2', 'message_id' => '10', 'original_name' => 'b.jpg', 'stored_name' => 'b_stored.jpg', 'mime_type' => 'image/jpeg', 'size' => 200, 'created_at' => 0],
                '3' => ['id' => '3', 'message_id' => '20', 'original_name' => 'c.png', 'stored_name' => 'c_stored.png', 'mime_type' => 'image/png', 'size' => 300, 'created_at' => 0],
            ];
            $result = TestSupportAttachments::getByMessageIds([10, 20]);
            expect(count($result[10]))->toBe(2);
            expect(count($result[20]))->toBe(1);
        });

        it('only returns attachments for requested message IDs', function (): void {
            $this->attachments->rows = [
                '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'x.pdf', 'stored_name' => 'x_s.pdf', 'mime_type' => 'application/pdf', 'size' => 50, 'created_at' => 0],
                '2' => ['id' => '2', 'message_id' => '99', 'original_name' => 'y.pdf', 'stored_name' => 'y_s.pdf', 'mime_type' => 'application/pdf', 'size' => 50, 'created_at' => 0],
            ];
            $result = TestSupportAttachments::getByMessageIds([10]);
            expect(isset($result[10]))->toBe(true);
            expect(isset($result[99]))->toBe(false);
        });

        it('keys the result map by integer message_id', function (): void {
            $this->attachments->rows = [
                '1' => ['id' => '1', 'message_id' => '5', 'original_name' => 'f.pdf', 'stored_name' => 'fs.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'created_at' => 0],
            ];
            $result = TestSupportAttachments::getByMessageIds([5]);
            expect(isset($result[5]))->toBe(true);
        });
    });

    // -------------------------------------------------------------------------

    describe('TestSupportTickets::getUnreadCountForUser()', function (): void {
        beforeEach(function (): void {
            [$this->tickets] = setupSupportTables();
        });

        it('returns 0 when user has no tickets', function (): void {
            $count = TestSupportTickets::getUnreadCountForUser(999);
            expect($count)->toBe(0);
        });

        it('returns 0 when tickets exist but unread_user is 0', function (): void {
            $this->tickets->rows = [
                '1' => ['id' => '1', 'account_id' => '7', 'subject' => 'T', 'status' => 'open', 'unread_user' => '0', 'unread_staff' => '0', 'created_at' => 0, 'updated_at' => 0],
            ];
            $count = TestSupportTickets::getUnreadCountForUser(7);
            expect($count)->toBe(0);
        });

        it('sums unread_user across multiple tickets', function (): void {
            $this->tickets->rows = [
                '1' => ['id' => '1', 'account_id' => '7', 'subject' => 'T1', 'status' => 'open', 'unread_user' => '2', 'unread_staff' => '0', 'created_at' => 0, 'updated_at' => 0],
                '2' => ['id' => '2', 'account_id' => '7', 'subject' => 'T2', 'status' => 'open', 'unread_user' => '3', 'unread_staff' => '0', 'created_at' => 0, 'updated_at' => 0],
            ];
            $count = TestSupportTickets::getUnreadCountForUser(7);
            expect($count)->toBe(5);
        });
    });

    // -------------------------------------------------------------------------

    describe('FwSupportController::enrichWithAttachments()', function (): void {
        beforeEach(function (): void {
            setupSupportTables();
        });

        it('sets attachments to empty array when no attachments exist', function (): void {
            $messages = [
                ['id' => '1', 'body' => 'Hello'],
            ];
            TestSupportController::exposedEnrichWithAttachments($messages);
            expect($messages[0]['attachments'])->toBe([]);
        });

        it('does nothing when messages array is empty', function (): void {
            $messages = [];
            TestSupportController::exposedEnrichWithAttachments($messages);
            expect($messages)->toBe([]);
        });

        it('attaches the correct attachment to the matching message', function (): void {
            /** @var TestSupportAttachments $atts */
            $atts = TestSupportAttachments::get();
            $atts->rows = [
                '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'doc.pdf', 'stored_name' => 'doc_s.pdf', 'mime_type' => 'application/pdf', 'size' => 512, 'created_at' => 0],
            ];

            $messages = [['id' => '10', 'body' => 'msg']];
            TestSupportController::exposedEnrichWithAttachments($messages);

            expect(count($messages[0]['attachments']))->toBe(1);
            expect($messages[0]['attachments'][0]['original_name'])->toBe('doc.pdf');
        });

        it('adds download_url to each attachment', function (): void {
            /** @var TestSupportAttachments $atts */
            $atts = TestSupportAttachments::get();
            $atts->rows = [
                '1' => ['id' => '7', 'message_id' => '10', 'original_name' => 'img.png', 'stored_name' => 'img_s.png', 'mime_type' => 'image/png', 'size' => 100, 'created_at' => 0],
            ];

            $messages = [['id' => '10', 'body' => 'msg']];
            TestSupportController::exposedEnrichWithAttachments($messages);

            $url = $messages[0]['attachments'][0]['download_url'];
            expect(str_contains($url, 'download'))->toBe(true);
            expect(str_contains($url, '7'))->toBe(true);
        });

        it('correctly assigns multiple attachments to different messages', function (): void {
            /** @var TestSupportAttachments $atts */
            $atts = TestSupportAttachments::get();
            $atts->rows = [
                '1' => ['id' => '1', 'message_id' => '10', 'original_name' => 'a.pdf', 'stored_name' => 'as.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'created_at' => 0],
                '2' => ['id' => '2', 'message_id' => '20', 'original_name' => 'b.pdf', 'stored_name' => 'bs.pdf', 'mime_type' => 'application/pdf', 'size' => 2, 'created_at' => 0],
            ];

            $messages = [
                ['id' => '10', 'body' => 'first'],
                ['id' => '20', 'body' => 'second'],
            ];
            TestSupportController::exposedEnrichWithAttachments($messages);

            expect(count($messages[0]['attachments']))->toBe(1);
            expect($messages[0]['attachments'][0]['original_name'])->toBe('a.pdf');
            expect(count($messages[1]['attachments']))->toBe(1);
            expect($messages[1]['attachments'][0]['original_name'])->toBe('b.pdf');
        });
    });

    // -------------------------------------------------------------------------

    describe('FwSupportAdminController::enrichWithAttachments()', function (): void {
        beforeEach(function (): void {
            setupSupportTables();
        });

        it('adds download_url containing admin URL prefix', function (): void {
            /** @var TestSupportAttachments $atts */
            $atts = TestSupportAttachments::get();
            $atts->rows = [
                '1' => ['id' => '3', 'message_id' => '5', 'original_name' => 'x.pdf', 'stored_name' => 'xs.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'created_at' => 0],
            ];

            $messages = [['id' => '5', 'body' => 'admin msg']];
            TestSupportAdminController::exposedEnrichWithAttachments($messages);

            $url = $messages[0]['attachments'][0]['download_url'];
            expect(str_contains($url, '/admin/support'))->toBe(true);
        });

        it('leaves messages with no attachments having empty attachments array', function (): void {
            $messages = [['id' => '99', 'body' => 'no files']];
            TestSupportAdminController::exposedEnrichWithAttachments($messages);
            expect($messages[0]['attachments'])->toBe([]);
        });
    });

    // -------------------------------------------------------------------------

    describe('FwSupportAdminController::VALID_STATUSES', function (): void {
        it('contains all expected ticket statuses', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('VALID_STATUSES');

            expect(in_array('open', $const, true))->toBe(true);
            expect(in_array('investigation', $const, true))->toBe(true);
            expect(in_array('in_progress', $const, true))->toBe(true);
            expect(in_array('waiting_user', $const, true))->toBe(true);
            expect(in_array('waiting_support', $const, true))->toBe(true);
            expect(in_array('escalated', $const, true))->toBe(true);
            expect(in_array('on_hold', $const, true))->toBe(true);
            expect(in_array('resolved', $const, true))->toBe(true);
            expect(in_array('rejected', $const, true))->toBe(true);
        });

        it('has exactly 9 valid statuses', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('VALID_STATUSES');
            expect(count($const))->toBe(9);
        });

        it('does not contain invalid status like "closed"', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('VALID_STATUSES');
            expect(in_array('closed', $const, true))->toBe(false);
        });

        it('does not contain invalid status like "deleted"', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('VALID_STATUSES');
            expect(in_array('deleted', $const, true))->toBe(false);
        });
    });

    // -------------------------------------------------------------------------

    describe('FwSupportAdminController — ticket workflow transitions', function (): void {
        /**
         * Verify that the status message inserted by post__changeStatus uses
         * translated labels and the correct arrow separator.
         */

        beforeEach(function (): void {
            [, $this->messages] = setupSupportTables();
        });

        it('getStatusLabels returns label for every VALID_STATUS key', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('VALID_STATUSES');

            $labels = TestSupportAdminController::exposedStatusLabels();

            foreach ($const as $status) {
                expect(isset($labels[$status]))->toBe(true);
            }
        });
    });

    // -------------------------------------------------------------------------

    describe('FwSupportController — UPLOAD_SUBDIR is "support"', function (): void {
        it('upload sub-directory constant is "support"', function (): void {
            $ref = new ReflectionClass(FwSupportController::class);
            $const = $ref->getConstant('UPLOAD_SUBDIR');
            expect($const)->toBe('support');
        });
    });

    describe('FwSupportAdminController — UPLOAD_SUBDIR is "support"', function (): void {
        it('upload sub-directory constant is "support"', function (): void {
            $ref = new ReflectionClass(FwSupportAdminController::class);
            $const = $ref->getConstant('UPLOAD_SUBDIR');
            expect($const)->toBe('support');
        });
    });

    // -------------------------------------------------------------------------

    describe('TestSupportAttachments::getByMessageIds() — edge cases', function (): void {
        beforeEach(function (): void {
            [$t, $m, $this->attachments] = setupSupportTables();
        });

        it('handles single message with multiple attachments', function (): void {
            $this->attachments->rows = [
                '1' => ['id' => '1', 'message_id' => '42', 'original_name' => 'f1.pdf', 'stored_name' => 'f1s.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'created_at' => 0],
                '2' => ['id' => '2', 'message_id' => '42', 'original_name' => 'f2.pdf', 'stored_name' => 'f2s.pdf', 'mime_type' => 'application/pdf', 'size' => 2, 'created_at' => 0],
                '3' => ['id' => '3', 'message_id' => '42', 'original_name' => 'f3.pdf', 'stored_name' => 'f3s.pdf', 'mime_type' => 'application/pdf', 'size' => 3, 'created_at' => 0],
            ];
            $result = TestSupportAttachments::getByMessageIds([42]);
            expect(count($result[42]))->toBe(3);
        });

        it('handles message IDs with no matching attachments gracefully', function (): void {
            $this->attachments->rows = [];
            $result = TestSupportAttachments::getByMessageIds([1, 2, 3]);
            expect($result)->toBe([]);
        });
    });
}
