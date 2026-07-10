<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Spec {
    use Closure;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Controllers\FwBalanceAdminController;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwAccountBalance;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwBalanceLedger;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // Concrete test-only table subclasses (no real DB — all methods overridden)
    // ---------------------------------------------------------------------------

    class TestBalanceLedger extends FwBalanceLedger {
        protected string $tableName = 'fw_balance_ledger_test';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        protected static function balanceTable(): FwAccountBalance {
            return TestAccountBalance::get();
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

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            throw new LogicException('init() must not be called in tests');
        }

        protected static function ledgerTable(): FwBalanceLedger {
            return TestBalanceLedger::get();
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

        public function updateByField(array $updateData, string $field, mixed $value, callable $callback = null): bool {
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

        public function selectAll(Closure $queryCallback = null): array {
            return array_values($this->rows);
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
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    function setupBalanceTables(): array {
        resetDbTableSingletons();

        $dbRef = new ReflectionClass(DbTable::class);
        $itemsProp = $dbRef->getProperty('items');
        $itemsProp->setAccessible(true);

        $ledgerRef = new ReflectionClass(TestBalanceLedger::class);
        $balanceRef = new ReflectionClass(TestAccountBalance::class);

        $ledgerObj = $ledgerRef->newInstanceWithoutConstructor();
        $balanceObj = $balanceRef->newInstanceWithoutConstructor();

        $itemsProp->setValue([
            TestBalanceLedger::class => $ledgerObj,
            TestAccountBalance::class => $balanceObj,
        ]);

        return [$ledgerObj, $balanceObj];
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
        describe('recalculate()', function (): void {
            beforeEach(function (): void {
                [$this->ledger, $this->balance] = setupBalanceTables();
            });

            it('inserts a new balance row when none exists yet', function (): void {
                $this->ledger->rows['1'] = [
                    'id' => '1', 'account_id' => 1,
                    'is_credit' => 1, 'amount' => 300, 'entry_type' => 'top_up',
                    'ref_type' => null, 'ref_id' => null, 'note' => null, 'created_at' => time(),
                ];

                // Override selectAll on ledger to handle the SUM-like query for recalculate
                // The real recalculate calls ledgerTable()->selectAll with a SUM col reset.
                // Our TestBalanceLedger::selectAll returns all rows; recalculate expects
                // the first row to have a 'bal' key. We seed a pre-aggregated row:
                $this->ledger->rows = [
                    '1' => ['bal' => 300],
                ];

                TestAccountBalance::recalculate(1);

                expect(count($this->balance->insertCalls))->toBeGreaterThan(0);
                expect($this->balance->insertCalls[0]['balance'])->toBe(300);
                expect($this->balance->insertCalls[0]['account_id'])->toBe(1);
            });

            it('updates existing balance row instead of inserting when row exists', function (): void {
                $this->balance->rows['1'] = ['id' => '1', 'account_id' => 1, 'balance' => 100, 'updated_at' => 0];
                $this->ledger->rows = [
                    '1' => ['bal' => 450],
                ];

                TestAccountBalance::recalculate(1);

                expect(count($this->balance->updateCalls))->toBeGreaterThan(0);
                expect($this->balance->updateCalls[0]['data']['balance'])->toBe(450);
                expect(count($this->balance->insertCalls))->toBe(0);
            });

            it('sets balance to 0 when ledger is empty (bal is null)', function (): void {
                $this->ledger->rows = [
                    '1' => ['bal' => null],
                ];

                TestAccountBalance::recalculate(99);

                expect($this->balance->insertCalls[0]['balance'])->toBe(0);
            });

            it('sets updated_at to approximately now', function (): void {
                $this->ledger->rows = ['1' => ['bal' => 50]];
                $before = time();
                TestAccountBalance::recalculate(3);
                $after = time();
                $updatedAt = $this->balance->insertCalls[0]['updated_at'];
                expect($updatedAt)->toBeGreaterThan($before - 1);
                expect($updatedAt)->toBeLessThan($after + 1);
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

            it('triggers balance recalculation after inserting the entry', function (): void {
                // Seed a pre-aggregated row for ledger's selectAll (used by recalculate)
                $this->ledger->rows = ['agg' => ['bal' => 0]];
                TestBalanceLedger::addEntry(1, true, 200, 'top_up');
                // recalculate inserts a balance row because none existed
                expect(count($this->balance->insertCalls))->toBeGreaterThan(0);
            });
        });

        // -----------------------------------------------------------------------
        describe('entryTypeEnum()', function (): void {
            it('includes top_up in enum definition', function (): void {
                // Access via reflection since entryTypeEnum is protected
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $method->setAccessible(true);
                $enum = $method->invoke(null);
                expect($enum)->toContain('top_up');
            });

            it('includes booking_refund in enum definition', function (): void {
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $method->setAccessible(true);
                $enum = $method->invoke(null);
                expect($enum)->toContain('booking_refund');
            });

            it('includes manual in enum definition', function (): void {
                $ref = new ReflectionClass(TestBalanceLedger::class);
                $method = $ref->getMethod('entryTypeEnum');
                $method->setAccessible(true);
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
                $this->method->setAccessible(true);
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
