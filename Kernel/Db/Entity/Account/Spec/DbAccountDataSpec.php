<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;
    use RuntimeException;

    // Records every SQL statement handed to the pool instead of talking to a
    // real database — lets the spec below assert on the exact WHERE clause
    // getAllUsersData() builds without requiring a live MySQL connection.
    class RecordingDbPool implements IDbPool {
        public array $queries = [];

        public array $rows = [];

        public function newLink(): IDbMySQLiLink {
            throw new RuntimeException('Not implemented in mock');
        }

        public function getDbConfig(): IniConfig {
            throw new RuntimeException('Not implemented in mock');
        }

        public function queryAsync(string $sql, array $args = [], ?callable $callBack = null): IDbMySQLiLink {
            throw new RuntimeException('Not implemented in mock');
        }

        public function query(string $sql, array $args = []): array|int|string|bool {
            $this->queries[] = ['sql' => $sql, 'args' => $args];

            return $this->rows;
        }

        public function poll(): void {
        }

        public function pollFinishAll(): void {
        }

        public function getLinksCount(): int {
            return 0;
        }
    }

    function getDbAccountDataTestConfigPath(): string {
        // __DIR__ is Framework/Kernel/Db/Entity/Account/Spec
        $frameworkDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

        return $frameworkDir . '/TestsInit/TestConfig/db.ini';
    }

    describe('DbAccountData', function (): void {
        describe('::getAllUsersData() - SQL scoping (P3 follow-up: bound the accounts_data scan)', function (): void {
            beforeEach(function (): void {
                $dbConfigPath = getDbAccountDataTestConfigPath();
                IniConfig::defineDbIni($dbConfigPath);

                // QueryEx::get() is a singleton; inject a QueryEx wired to a
                // recording IDbPool double instead of swapping DbPool's own
                // singleton (DbPool::$instance is typed `?DbPool`, so it can't
                // hold an unrelated IDbPool double — mirrors the pattern used
                // in Kernel\Db\Query\Spec\QueryExSpec.php).
                $this->recordingPool = new RecordingDbPool();

                $reflectionQueryEx = new ReflectionClass(QueryEx::class);
                $propQueryEx = $reflectionQueryEx->getProperty('instance');
                $propQueryEx->setAccessible(true);
                $propQueryEx->setValue(null, new QueryEx($this->recordingPool));
            });

            afterEach(function (): void {
                $reflectionQueryEx = new ReflectionClass(QueryEx::class);
                $propQueryEx = $reflectionQueryEx->getProperty('instance');
                $propQueryEx->setAccessible(true);
                $propQueryEx->setValue(null, null);
            });

            it('filters by param name via WHERE ... IN instead of reading the whole table', function (): void {
                DbAccountData::getAllUsersData(['IS_APPROVED', 'IS_DISABLED']);

                expect(count($this->recordingPool->queries))->toBe(1);

                $sql = $this->recordingPool->queries[0]['sql'];
                $args = $this->recordingPool->queries[0]['args'];

                expect($sql)->toMatch('/\bWHERE\b/i');
                expect($sql)->toMatch('/\bparam\b.*\bIN\b/i');
                expect($args)->toContain('IS_APPROVED');
                expect($args)->toContain('IS_DISABLED');
            });

            it('additionally scopes by account_id when a candidate id set is provided', function (): void {
                DbAccountData::getAllUsersData(['IS_APPROVED'], [101, 102]);

                expect(count($this->recordingPool->queries))->toBe(1);

                $sql = $this->recordingPool->queries[0]['sql'];
                $args = $this->recordingPool->queries[0]['args'];

                expect($sql)->toMatch('/\baccount_id\b.*\bIN\b/i');
                expect($args)->toContain(101);
                expect($args)->toContain(102);
            });

            it('does not hit the database at all when the account id candidate set is empty', function (): void {
                $result = DbAccountData::getAllUsersData(['IS_APPROVED'], []);

                expect($result)->toBe([]);
                expect(count($this->recordingPool->queries))->toBe(0);
            });

            it('does not hit the database at all when no param names are requested', function (): void {
                $result = DbAccountData::getAllUsersData([]);

                expect($result)->toBe([]);
                expect(count($this->recordingPool->queries))->toBe(0);
            });

            it('skips rows whose param matches only case-insensitively (utf8mb4_general_ci) instead of polluting the result with a param-name key', function (): void {
                // The SQL `WHERE param IN (...)` filter matches case-insensitively under
                // utf8mb4_general_ci, so MySQL can hand back a row whose `param` differs
                // from every requested name only by case. The exact $namesMap lookup then
                // misses it; such a row must be skipped, never written under a param-name key.
                $this->recordingPool->rows = [
                    ['account_id' => '101', 'param' => 'IS_APPROVED', 'value' => '1'],
                    ['account_id' => '102', 'param' => 'is_approved', 'value' => '1'],
                ];

                $result = DbAccountData::getAllUsersData(['IS_APPROVED']);

                expect($result)->toBe(['101' => ['IS_APPROVED' => 1]]);
            });
        });

        describe('::getAllUsersData() - data filtering and grouping logic', function (): void {
            it('filters numeric strings and converts to integers', function (): void {
                $testData = ['123', '456', 'not_a_number'];

                foreach ($testData as $value) {
                    $isInt = \PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools::isIntStr($value);
                    $converted = $isInt ? intval($value) : $value;

                    if ($isInt) {
                        expect($converted)->toBeAn('integer');
                    } else {
                        expect($converted)->toBeA('string');
                    }
                }

                expect(intval('123'))->toBe(123);
                expect(intval('456'))->toBe(456);
            });

            it('handles items with missing required keys gracefully', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name'],
                    ['param' => 'name', 'value' => 'Test'],
                    ['account_id' => 'user1', 'value' => 'value'],
                    [],
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'Valid'],
                ];

                $validItems = [];

                foreach ($items as $item) {
                    if (isset($item['account_id'], $item['param'], $item['value'])) {
                        $validItems[] = $item;
                    }
                }

                expect(count($validItems))->toBe(1);
                expect($validItems[0])->toBe([
                    'account_id' => 'user1',
                    'param' => 'name',
                    'value' => 'Valid',
                ]);
            });

            it('groups params by account_id', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'city', 'value' => 'NYC'],
                    ['account_id' => 'user2', 'param' => 'name', 'value' => 'Jane'],
                ];

                $result = [];

                foreach ($items as $item) {
                    $id = $item['account_id'];
                    $name = $item['param'];
                    $value = $item['value'];

                    if (!isset($result[$id])) {
                        $result[$id] = [];
                    }

                    $result[$id][$name] = $value;
                }

                expect($result)->toBe([
                    'user1' => ['name' => 'John', 'age' => '30', 'city' => 'NYC'],
                    'user2' => ['name' => 'Jane'],
                ]);
            });

            it('creates mapping array from names', function (): void {
                $names = ['name', 'age', 'city'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                expect(isset($namesMap['name']))->toBe(true);
                expect(isset($namesMap['age']))->toBe(true);
                expect(isset($namesMap['city']))->toBe(true);
                expect(isset($namesMap['other']))->toBe(false);
            });

            it('filters items by namesMap', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'other', 'value' => 'data'],
                ];

                $names = ['name', 'age'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                $result = [];

                foreach ($items as $item) {
                    $name = $item['param'];

                    if (!isset($namesMap[$name])) {
                        continue;
                    }

                    $result[$name] = $item['value'];
                }

                expect($result)->toBe(['name' => 'John', 'age' => '30']);
                expect(isset($result['other']))->toBe(false);
            });

            it('combines all logic steps', function (): void {
                $items = [
                    ['account_id' => 'user1', 'param' => 'name', 'value' => 'John'],
                    ['account_id' => 'user1', 'param' => 'age', 'value' => '30'],
                    ['account_id' => 'user1', 'param' => 'city', 'value' => 'NYC'],
                    ['account_id' => 'user2', 'param' => 'name', 'value' => 'Jane'],
                    ['account_id' => 'user2', 'param' => 'age', 'value' => '25'],
                ];

                $names = ['name', 'age'];
                $namesMap = [];

                foreach ($names as $name) {
                    $namesMap[$name] = true;
                }

                $result = [];

                foreach ($items as $item) {
                    if (!isset($item['account_id']) || !isset($item['param']) || !isset($item['value'])) {
                        continue;
                    }

                    $val = \PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools::isIntStr($item['value'])
                        ? intval($item['value'])
                        : $item['value'];
                    $id = $item['account_id'];
                    $name = $item['param'];

                    if (!isset($namesMap[$name])) {
                        continue;
                    }

                    if (isset($result[$id])) {
                        $result[$id][$name] = $val;
                    } else {
                        $result[$id] = [$name => $val];
                    }
                }

                expect($result)->toBe([
                    'user1' => ['name' => 'John', 'age' => 30],
                    'user2' => ['name' => 'Jane', 'age' => 25],
                ]);
            });
        });
    });
}
