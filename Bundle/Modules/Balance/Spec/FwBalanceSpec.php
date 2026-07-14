<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Spec {
    use Closure;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Controllers\FwBalanceAdminController;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwAccountBalance;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwBalanceLedger;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // Concrete test-only table subclasses (no real DB — all methods overridden)
    // ---------------------------------------------------------------------------

    class TestBalanceLedger extends FwBalanceLedger {
        protected string $tableName = 'fw_balance_ledger_test';

        protected static function balanceTable(): FwAccountBalance {
            return TestAccountBalance::get();
        }

        /** In-memory rows for unit-level tests (getBalance, addEntry field checks). */
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
    }

    class TestAccountBalance extends FwAccountBalance {
        protected string $tableName = 'fw_account_balance_test';

        protected static function ledgerTable(): FwBalanceLedger {
            return TestBalanceLedger::get();
        }

        /** No-op: unit-level tests must not hit the real DB via recalculate(). */
        public static function recalculate(int $accountId): void {
            // intentionally empty — DB-backed tests use DbTestAccountBalance
        }

        /** In-memory rows for unit-level tests (getBalance, addEntry field checks). */
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

        public function updateByField(array $updateData, string $field, mixed $value, ?callable $callback = null): bool {
            $this->updateCalls[] = ['data' => $updateData, 'field' => $field, 'value' => $value];

            foreach ($this->rows as &$row) {
                if (($row[$field] ?? null) === $value) {
                    $row = array_merge($row, $updateData);
                }
            }

            return true;
        }

        public function selectOneByField(string $field, array|string|int $value, ?Closure $queryCallback = null): ?array {
            foreach ($this->rows as $row) {
                if (($row[$field] ?? null) === $value) {
                    return $row;
                }
            }

            return null;
        }

        public function selectAll(?Closure $queryCallback = null): array {
            return array_values($this->rows);
        }
    }

    // ---------------------------------------------------------------------------
    // DB-backed test subclasses (real MySQL tables for recalculate() tests)
    // ---------------------------------------------------------------------------

    class DbTestBalanceLedger extends FwBalanceLedger {
        protected string $tableName = 'fw_balance_ledger_test';

        protected static function balanceTable(): FwAccountBalance {
            return DbTestAccountBalance::get();
        }
    }

    class DbTestAccountBalance extends FwAccountBalance {
        protected string $tableName = 'fw_account_balance_test';

        protected static function ledgerTable(): FwBalanceLedger {
            return DbTestBalanceLedger::get();
        }
    }

    // ---------------------------------------------------------------------------
    // Concrete test-only admin controller subclass
    // ---------------------------------------------------------------------------

    class TestBalanceAdminController extends FwBalanceAdminController {
        protected static function balanceTable(): FwAccountBalance {
            return TestAccountBalance::get();
        }

        protected static function getSideMenu(string $url): array {
            return [];
        }

        protected static function getMainMenu(string $url): array {
            return [];
        }

        protected static function isAllowed(): bool {
            return true;
        }

        protected static function buildGridConfig(): array {
            return [];
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

    function setupBalanceTables(): array {
        resetDbTableSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $ledgerRef = new ReflectionClass(TestBalanceLedger::class);
        $balanceRef = new ReflectionClass(TestAccountBalance::class);

        $ledgerObj = $ledgerRef->newInstanceWithoutConstructor();
        $balanceObj = $balanceRef->newInstanceWithoutConstructor();

        $itemsProp->setValue(null, [
            TestBalanceLedger::class => $ledgerObj,
            TestAccountBalance::class => $balanceObj,
        ]);

        return [$ledgerObj, $balanceObj];
    }

    function setupDbBalanceTables(): void {
        resetDbTableSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');

        $ledgerRef = new ReflectionClass(DbTestBalanceLedger::class);
        $balanceRef = new ReflectionClass(DbTestAccountBalance::class);

        $ledgerObj = $ledgerRef->newInstanceWithoutConstructor();
        $balanceObj = $balanceRef->newInstanceWithoutConstructor();

        $itemsProp->setValue(null, [
            DbTestBalanceLedger::class => $ledgerObj,
            DbTestAccountBalance::class => $balanceObj,
        ]);

        $ledgerName = DbTestBalanceLedger::get()->getTableName();
        $balanceName = DbTestAccountBalance::get()->getTableName();

        QueryEx::get()->ex("DROP TABLE IF EXISTS `{$balanceName}`", []);
        QueryEx::get()->ex("DROP TABLE IF EXISTS `{$ledgerName}`", []);

        QueryEx::get()->ex("
            CREATE TABLE `{$ledgerName}` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT(11) NOT NULL,
                is_credit TINYINT(1) NOT NULL DEFAULT 0,
                amount INT(11) NOT NULL DEFAULT 0,
                entry_type ENUM('top_up','booking_invoice','booking_payment','booking_refund','manual'),
                ref_type VARCHAR(50) NULL,
                ref_id INT(11) NULL,
                note VARCHAR(255) NULL,
                created_at INT(11) NOT NULL DEFAULT 0,
                INDEX account_id (account_id),
                INDEX ref (ref_type, ref_id)
            ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
        ", []);

        QueryEx::get()->ex("
            CREATE TABLE `{$balanceName}` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT(11) NOT NULL,
                balance INT(11) NOT NULL DEFAULT 0,
                updated_at INT(11) NOT NULL DEFAULT 0,
                UNIQUE KEY account_id (account_id)
            ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
        ", []);
    }

    function teardownDbBalanceTables(): void {
        $ledgerName = DbTestBalanceLedger::get()->getTableName();
        $balanceName = DbTestAccountBalance::get()->getTableName();

        QueryEx::get()->ex("DROP TABLE IF EXISTS `{$balanceName}`", []);
        QueryEx::get()->ex("DROP TABLE IF EXISTS `{$ledgerName}`", []);
        resetDbTableSingletons();
    }

    function truncateDbBalanceTables(): void {
        $ledgerName = DbTestBalanceLedger::get()->getTableName();
        $balanceName = DbTestAccountBalance::get()->getTableName();

        QueryEx::get()->ex("TRUNCATE TABLE `{$ledgerName}`", []);
        QueryEx::get()->ex("TRUNCATE TABLE `{$balanceName}`", []);
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwAccountBalance', function (): void {
        // -----------------------------------------------------------------------
        describe('getBalance()', function (): void {
            beforeEach(function (): void {
                [$this->ledger, $this->balance] = setupBalanceTables();
            });

            it('returns 0 when no balance row exists for the account', function (): void {
                $result = TestAccountBalance::getBalance(42);
                expect($result)->toBe(0);
            });

            it('returns stored balance when row exists', function (): void {
                $this->balance->rows['1'] = ['id' => '1', 'account_id' => 10, 'balance' => 500, 'updated_at' => time()];
                $result = TestAccountBalance::getBalance(10);
                expect($result)->toBe(500);
            });

            it('returns int type', function (): void {
                $this->balance->rows['1'] = ['id' => '1', 'account_id' => 7, 'balance' => '200', 'updated_at' => time()];
                $result = TestAccountBalance::getBalance(7);
                expect($result)->toBeA('integer');
            });

            it('returns 0 for an account with balance=0', function (): void {
                $this->balance->rows['1'] = ['id' => '1', 'account_id' => 5, 'balance' => 0, 'updated_at' => time()];
                $result = TestAccountBalance::getBalance(5);
                expect($result)->toBe(0);
            });

            it('returns correct balance for a specific account when multiple exist', function (): void {
                $this->balance->rows['1'] = ['id' => '1', 'account_id' => 1, 'balance' => 100, 'updated_at' => time()];
                $this->balance->rows['2'] = ['id' => '2', 'account_id' => 2, 'balance' => 999, 'updated_at' => time()];
                expect(TestAccountBalance::getBalance(2))->toBe(999);
                expect(TestAccountBalance::getBalance(1))->toBe(100);
            });
        });

        // -----------------------------------------------------------------------
        describe('recalculate() [real DB]', function (): void {
            beforeAll(function (): void {
                setupDbBalanceTables();
            });

            afterAll(function (): void {
                teardownDbBalanceTables();
            });

            beforeEach(function (): void {
                truncateDbBalanceTables();
            });

            it('inserts a new balance row when none exists yet (INSERT branch)', function (): void {
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 1, 'is_credit' => 1, 'amount' => 300,
                    'entry_type' => 'top_up', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);

                DbTestAccountBalance::recalculate(1);

                expect(DbTestAccountBalance::getBalance(1))->toBe(300);
            });

            it('updates existing balance row on re-recalculate (UPDATE branch)', function (): void {
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 1, 'is_credit' => 1, 'amount' => 100,
                    'entry_type' => 'top_up', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);
                DbTestAccountBalance::recalculate(1);
                expect(DbTestAccountBalance::getBalance(1))->toBe(100);

                // Add another ledger entry and recalculate again
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 1, 'is_credit' => 1, 'amount' => 350,
                    'entry_type' => 'top_up', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);
                DbTestAccountBalance::recalculate(1);

                expect(DbTestAccountBalance::getBalance(1))->toBe(450);
            });

            it('sets balance to 0 when ledger has no entries for account (COALESCE branch)', function (): void {
                DbTestAccountBalance::recalculate(99);

                expect(DbTestAccountBalance::getBalance(99))->toBe(0);
            });

            it('sets updated_at to approximately now', function (): void {
                $before = time();
                DbTestAccountBalance::recalculate(3);
                $after = time();

                $row = DbTestAccountBalance::get()->selectOneByField('account_id', 3);
                expect($row)->not->toBeNull();
                expect((int)$row['updated_at'])->toBeGreaterThan($before - 1);
                expect((int)$row['updated_at'])->toBeLessThan($after + 1);
            });

            it('correctly handles mix of credits and debits', function (): void {
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 5, 'is_credit' => 1, 'amount' => 1000,
                    'entry_type' => 'top_up', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 5, 'is_credit' => 0, 'amount' => 250,
                    'entry_type' => 'booking_payment', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);

                DbTestAccountBalance::recalculate(5);

                expect(DbTestAccountBalance::getBalance(5))->toBe(750);
            });

            it('each recalculate derives from full ledger state (atomicity)', function (): void {
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 10, 'is_credit' => 1, 'amount' => 200,
                    'entry_type' => 'top_up', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);
                DbTestBalanceLedger::get()->insert([
                    'account_id' => 10, 'is_credit' => 1, 'amount' => 300,
                    'entry_type' => 'manual', 'ref_type' => null, 'ref_id' => null,
                    'note' => null, 'created_at' => time(),
                ]);

                // Two sequential recalculates -- both must see the full ledger
                DbTestAccountBalance::recalculate(10);
                DbTestAccountBalance::recalculate(10);

                expect(DbTestAccountBalance::getBalance(10))->toBe(500);
            });
        });
    });

    // ---------------------------------------------------------------------------

    describe('FwBalanceLedger', function (): void {
        // -----------------------------------------------------------------------
        describe('addEntry()', function (): void {
            beforeEach(function (): void {
                [$this->ledger, $this->balance] = setupBalanceTables();
            });

            it('inserts a row into the ledger table', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'top_up');
                expect(count($this->ledger->insertCalls))->toBe(1);
            });

            it('stores account_id correctly', function (): void {
                TestBalanceLedger::addEntry(42, true, 200, 'top_up');
                expect($this->ledger->insertCalls[0]['account_id'])->toBe(42);
            });

            it('stores is_credit=1 for a credit entry', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'top_up');
                expect($this->ledger->insertCalls[0]['is_credit'])->toBe(1);
            });

            it('stores is_credit=0 for a debit entry', function (): void {
                TestBalanceLedger::addEntry(1, false, 50, 'booking_payment');
                expect($this->ledger->insertCalls[0]['is_credit'])->toBe(0);
            });

            it('stores the amount', function (): void {
                TestBalanceLedger::addEntry(1, true, 750, 'top_up');
                expect($this->ledger->insertCalls[0]['amount'])->toBe(750);
            });

            it('stores the entry_type', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'booking_refund');
                expect($this->ledger->insertCalls[0]['entry_type'])->toBe('booking_refund');
            });

            it('stores ref_type as null when empty string is provided', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'top_up', '', 0, '');
                expect($this->ledger->insertCalls[0]['ref_type'])->toBeNull();
            });

            it('stores ref_id as null when 0 is provided', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'top_up', '', 0);
                expect($this->ledger->insertCalls[0]['ref_id'])->toBeNull();
            });

            it('stores non-empty ref_type', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'booking_payment', 'booking', 7);
                expect($this->ledger->insertCalls[0]['ref_type'])->toBe('booking');
            });

            it('stores non-zero ref_id', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'booking_payment', 'booking', 99);
                expect($this->ledger->insertCalls[0]['ref_id'])->toBe(99);
            });

            it('stores note as null when empty', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'top_up', '', 0, '');
                expect($this->ledger->insertCalls[0]['note'])->toBeNull();
            });

            it('stores non-empty note', function (): void {
                TestBalanceLedger::addEntry(1, true, 100, 'manual', '', 0, 'Admin credit');
                expect($this->ledger->insertCalls[0]['note'])->toBe('Admin credit');
            });

            it('sets created_at to approximately now', function (): void {
                $before = time();
                TestBalanceLedger::addEntry(1, true, 100, 'top_up');
                $after = time();
                $createdAt = $this->ledger->insertCalls[0]['created_at'];
                expect($createdAt)->toBeGreaterThan($before - 1);
                expect($createdAt)->toBeLessThan($after + 1);
            });

            it('triggers balance recalculation after inserting the entry [real DB]', function (): void {
                setupDbBalanceTables();

                try {
                    truncateDbBalanceTables();
                    DbTestBalanceLedger::addEntry(1, true, 200, 'top_up');

                    expect(DbTestAccountBalance::getBalance(1))->toBe(200);
                } finally {
                    teardownDbBalanceTables();
                }
            });
        });

        // -----------------------------------------------------------------------
        describe('entryTypeEnum()', function (): void {
            it('includes top_up in enum definition', function (): void {
                // Access via reflection since entryTypeEnum is protected
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $enum = $method->invoke(null);
                expect($enum)->toContain('top_up');
            });

            it('includes booking_refund in enum definition', function (): void {
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $enum = $method->invoke(null);
                expect($enum)->toContain('booking_refund');
            });

            it('includes manual in enum definition', function (): void {
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $enum = $method->invoke(null);
                expect($enum)->toContain('manual');
            });
        });
    });

    // ---------------------------------------------------------------------------

    describe('FwBalanceAdminController', function (): void {
        // -----------------------------------------------------------------------
        describe('resolveRole()', function (): void {
            beforeEach(function (): void {
                // resolveRole is protected, access via reflection
                $this->ref = new ReflectionClass(TestBalanceAdminController::class);
                $this->method = $this->ref->getMethod('resolveRole');
            });

            it('returns "admin" when IS_ADMIN flag is set', function (): void {
                $account = [
                    'type' => 'user',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 1,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 0,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('admin');
            });

            it('returns "owner" when IS_OWNER is set but IS_ADMIN is not', function (): void {
                $account = [
                    'type' => 'user',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 1,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 0,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('owner');
            });

            it('returns "moderator" when IS_MODERATOR is set and IS_ADMIN/IS_OWNER are not', function (): void {
                $account = [
                    'type' => 'user',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 1,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('moderator');
            });

            it('returns account type when no elevated flags are set', function (): void {
                $account = [
                    'type' => 'expert',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 0,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('expert');
            });

            it('returns "user" as fallback when type key is missing', function (): void {
                $account = [
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 0,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('user');
            });

            it('IS_ADMIN takes priority over IS_OWNER when both are set', function (): void {
                $account = [
                    'type' => 'user',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 1,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 1,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 0,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('admin');
            });

            it('IS_OWNER takes priority over IS_MODERATOR when both are set', function (): void {
                $account = [
                    'type' => 'user',
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_ADMIN => 0,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_OWNER => 1,
                    \PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account::IS_MODERATOR => 1,
                ];
                $result = $this->method->invoke(null, $account);
                expect($result)->toBe('owner');
            });
        });
    });
}
