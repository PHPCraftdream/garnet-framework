<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Spec;

use Aura\SqlQuery\QueryFactory;
use Exception;
use Generator;
use PDO;
use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
use PHPCraftdream\Garnet\Kernel\Db\Query\QueryExPdo;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

describe('QueryExPdo Integration', function (): void {
    $dbAvailable = false;
    $queryFactory = null;
    $queryExPdo = null;

    beforeAll(function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        // Load database configuration
        $dbConfigPath = __DIR__ . '/../../../../TestsInit/TestConfig/db.ini';

        if (!file_exists($dbConfigPath)) {
            return;
        }

        $config = parse_ini_file($dbConfigPath);

        if (!isset($config['enabled']) || $config['enabled'] !== '1') {
            return;
        }

        IniConfig::defineDbIni($dbConfigPath);

        // Create PDO connection
        $iniConfig = IniConfig::db();
        $dsn = $iniConfig->param('dsn');
        $user = $iniConfig->param('user');
        $password = $iniConfig->param('password');

        try {
            $pdo = new ExtPDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $queryExPdo = QueryExPdo::get();
            $queryExPdo->setPDO($pdo);

            $queryFactory = new QueryFactory('mysql');
            $dbAvailable = true;

            // Create test table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL UNIQUE,
                    price DECIMAL(10,2) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $queryExPdo->ex($sql, []);
        } catch (Exception $e) {
            // Database not available, tests will be skipped
        }
    });

    describe('SELECT operations', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        beforeEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_%'", []);

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_products (name, price, description) VALUES (?, ?, ?)';
            $queryExPdo->exSimpleInsert($sql, ['test_product_1', 19.99, 'Description 1']);
            $queryExPdo->exSimpleInsert($sql, ['test_product_2', 29.99, 'Description 2']);
            $queryExPdo->exSimpleInsert($sql, ['test_product_3', 39.99, 'Description 3']);
        });

        afterEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_%'", []);
        });

        it('executes SELECT query with exSelect', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name LIKE ?', ['test_%'])
                ->orderBy(['price ASC']);

            $results = $queryExPdo->exSelect($select);

            expect($results)->toBeAn('array');
            expect(count($results))->toBe(3);
            expect($results[0]['name'])->toBe('test_product_1');
            expect($results[2]['name'])->toBe('test_product_3');
        });

        it('executes SELECT query with exFetch', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $sql = 'SELECT * FROM dbtest_test_products WHERE name LIKE ? ORDER BY price ASC';
            $results = $queryExPdo->exFetch($sql, ['test_%']);

            expect($results)->toBeAn('array');
            expect(count($results))->toBe(3);
            expect($results[0]['name'])->toBe('test_product_1');
        });

        it('executes SELECT query with exFetchItr generator', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $sql = 'SELECT * FROM dbtest_test_products WHERE name LIKE ? ORDER BY price ASC';
            $generator = $queryExPdo->exFetchItr($sql, ['test_%']);

            expect($generator)->toBeAnInstanceOf(Generator::class);

            $collected = iterator_to_array($generator);
            expect($collected)->toBeAn('array');
            expect(count($collected))->toBe(3);
        });

        it('counts records with selectCount', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $select = $queryFactory->newSelect();
            $select->from('dbtest_test_products')
                ->where('name LIKE ?', ['test_%']);

            $count = $queryExPdo->selectCount($select);

            expect($count)->toBe(3);
        });

        it('returns empty array when no results', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['nonexistent_product']);

            $results = $queryExPdo->exSelect($select);

            expect($results)->toBe([]);
        });

        it('uses FETCH_OBJ mode when configured', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $queryExPdo->setFetch(PDO::FETCH_OBJ);

            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['test_product_1']);

            $results = $queryExPdo->exSelect($select);

            expect(count($results))->toBe(1);
            expect($results[0])->toBeAn('object');
            expect($results[0]->name)->toBe('test_product_1');

            // Reset to default
            $queryExPdo->setFetch(PDO::FETCH_ASSOC);
        });
    });

    describe('INSERT operations', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        afterEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_%'", []);
        });

        it('inserts record with exInsert and returns ID', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $insert = $queryFactory->newInsert();
            $insert->into('dbtest_test_products')
                ->cols([
                    'name' => 'test_insert_product',
                    'price' => 49.99,
                    'description' => 'Test insert',
                ]);

            $insertId = $queryExPdo->exInsert($insert);

            expect($insertId)->toBeGreaterThan(0);

            // Verify insertion
            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('id = ?', [$insertId]);

            $results = $queryExPdo->exSelect($select);
            expect(count($results))->toBe(1);
            expect($results[0]['name'])->toBe('test_insert_product');
        });

        it('inserts record with exSimpleInsert and returns ID', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $sql = 'INSERT INTO dbtest_test_products (name, price, description) VALUES (?, ?, ?)';
            $insertId = $queryExPdo->exSimpleInsert($sql, ['test_simple_insert', 59.99, 'Simple insert']);

            expect($insertId)->toBeGreaterThan(0);

            // Verify insertion
            $results = $queryExPdo->exFetch('SELECT * FROM dbtest_test_products WHERE id = ?', [$insertId]);
            expect(count($results))->toBe(1);
            expect($results[0]['name'])->toBe('test_simple_insert');
        });

        it('inserts record with exInsertIgnore on duplicate', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // First insert
            $insert = $queryFactory->newInsert();
            $insert->into('dbtest_test_products')
                ->cols([
                    'name' => 'test_unique_product',
                    'price' => 69.99,
                    'description' => 'Unique product',
                ]);

            $insertId1 = $queryExPdo->exInsert($insert);
            expect($insertId1)->toBeGreaterThan(0);

            // Try to insert again (should be ignored)
            $insert2 = $queryFactory->newInsert();
            $insert2->into('dbtest_test_products')
                ->cols([
                    'name' => 'test_unique_product',
                    'price' => 79.99,
                    'description' => 'Should be ignored',
                ]);

            $queryExPdo->exInsertIgnore($insert2);

            // Verify only one record exists
            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['test_unique_product']);

            $results = $queryExPdo->exSelect($select);
            expect(count($results))->toBe(1);
            expect((float)$results[0]['price'])->toBe(69.99);
        });
    });

    describe('UPDATE operations', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        beforeEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_products (name, price, description) VALUES (?, ?, ?)';
            $queryExPdo->exSimpleInsert($sql, ['test_update_product', 89.99, 'Original description']);
        });

        afterEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_update_%'", []);
        });

        it('updates record with exUpdate', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $update = $queryFactory->newUpdate();
            $update->table('dbtest_test_products')
                ->cols([
                    'price' => 99.99,
                    'description' => 'Updated description',
                ])
                ->where('name = ?', ['test_update_product']);

            $result = $queryExPdo->exUpdate($update);

            expect($result)->toBe(true);

            // Verify update
            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['test_update_product']);

            $results = $queryExPdo->exSelect($select);
            expect(count($results))->toBe(1);
            expect($results[0]['price'])->toBe('99.99');
            expect($results[0]['description'])->toBe('Updated description');
        });

        it('updates record with ex() raw SQL', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $sql = 'UPDATE dbtest_test_products SET price = ? WHERE name = ?';
            $result = $queryExPdo->ex($sql, [109.99, 'test_update_product']);

            expect($result)->toBe(true);

            // Verify update
            $queryExPdo->setFetch(PDO::FETCH_ASSOC);
            $results = $queryExPdo->exFetch('SELECT * FROM dbtest_test_products WHERE name = ?', ['test_update_product']);
            expect($results[0]['price'])->toBe('109.99');
        });
    });

    describe('DELETE operations', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        beforeEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_products (name, price, description) VALUES (?, ?, ?)';
            $queryExPdo->exSimpleInsert($sql, ['test_delete_product', 119.99, 'To be deleted']);
        });

        afterEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_delete_%'", []);
        });

        it('deletes record with exDelete', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Verify record exists
            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['test_delete_product']);

            $results = $queryExPdo->exSelect($select);
            expect(count($results))->toBe(1);

            // Delete record
            $delete = $queryFactory->newDelete();
            $delete->from('dbtest_test_products')
                ->where('name = ?', ['test_delete_product']);

            $result = $queryExPdo->exDelete($delete);
            expect($result)->toBe(true);

            // Verify deletion
            $results = $queryExPdo->exSelect($select);
            expect(count($results))->toBe(0);
        });

        it('deletes record with ex() raw SQL', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Verify record exists
            $results = $queryExPdo->exFetch('SELECT * FROM dbtest_test_products WHERE name = ?', ['test_delete_product']);
            expect(count($results))->toBe(1);

            // Delete record
            $sql = 'DELETE FROM dbtest_test_products WHERE name = ?';
            $result = $queryExPdo->ex($sql, ['test_delete_product']);
            expect($result)->toBe(true);

            // Verify deletion
            $results = $queryExPdo->exFetch('SELECT * FROM dbtest_test_products WHERE name = ?', ['test_delete_product']);
            expect(count($results))->toBe(0);
        });
    });

    describe('Last query tracking', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
        beforeEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_last_%'", []);
        });

        afterEach(function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryExPdo->ex("DELETE FROM dbtest_test_products WHERE name LIKE 'test_last_%'", []);
        });

        it('tracks last SELECT query', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $select = $queryFactory->newSelect();
            $select->cols(['*'])
                ->from('dbtest_test_products')
                ->where('name = ?', ['test_last_select']);

            $queryExPdo->exSelect($select);

            $lastQuery = $queryExPdo->getLastQuery();
            expect($lastQuery)->not->toBeNull();
            expect($lastQuery->getSql())->toBeA('string');
            expect($lastQuery->getSql())->toContain('SELECT');
            expect($lastQuery->getParams())->toBeAn('array');
        });

        it('tracks last INSERT query', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $insert = $queryFactory->newInsert();
            $insert->into('dbtest_test_products')
                ->cols([
                    'name' => 'test_last_insert',
                    'price' => 129.99,
                ]);

            $queryExPdo->exInsert($insert);

            $lastQuery = $queryExPdo->getLastQuery();
            expect($lastQuery)->not->toBeNull();
            expect($lastQuery->getSql())->toContain('INSERT');
        });

        it('tracks last UPDATE query', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // First insert
            $sql = 'INSERT INTO dbtest_test_products (name, price) VALUES (?, ?)';
            $queryExPdo->exSimpleInsert($sql, ['test_last_update', 139.99]);

            // Then update
            $update = $queryFactory->newUpdate();
            $update->table('dbtest_test_products')
                ->cols(['price' => 149.99])
                ->where('name = ?', ['test_last_update']);

            $queryExPdo->exUpdate($update);

            $lastQuery = $queryExPdo->getLastQuery();
            expect($lastQuery)->not->toBeNull();
            expect($lastQuery->getSql())->toContain('UPDATE');
        });

        it('tracks last DELETE query', function () use (&$dbAvailable, &$queryFactory, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            // First insert
            $sql = 'INSERT INTO dbtest_test_products (name, price) VALUES (?, ?)';
            $queryExPdo->exSimpleInsert($sql, ['test_last_delete', 159.99]);

            // Then delete
            $delete = $queryFactory->newDelete();
            $delete->from('dbtest_test_products')
                ->where('name = ?', ['test_last_delete']);

            $queryExPdo->exDelete($delete);

            $lastQuery = $queryExPdo->getLastQuery();
            expect($lastQuery)->not->toBeNull();
            expect($lastQuery->getSql())->toContain('DELETE');
        });

        it('tracks last raw SQL query', function () use (&$dbAvailable, &$queryExPdo): void {
            if (!$dbAvailable) {
                return;
            }

            $sql = 'SELECT * FROM dbtest_test_products WHERE 1=0';
            $queryExPdo->ex($sql, []);

            $lastQuery = $queryExPdo->getLastQuery();
            expect($lastQuery)->not->toBeNull();
            expect($lastQuery->getSql())->toBe($sql);
        });
    });

    afterAll(function () use (&$dbAvailable, &$queryExPdo): void {
        // Tables left for debugging purposes
        if (!$dbAvailable) {
            return;
        }
    });
});
