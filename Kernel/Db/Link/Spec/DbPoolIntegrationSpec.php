<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

describe('DbPool Integration', function (): void {
    $dbAvailable = false;

    beforeAll(function () use (&$dbAvailable): void {
        // Load database configuration
        $dbConfigPath = __DIR__ . '/../../../../TestsInit/TestConfig/db.ini';

        if (!file_exists($dbConfigPath)) {
            echo "db.ini not found at {$dbConfigPath}\n";

            return;
        }

        $config = parse_ini_file($dbConfigPath);

        if (!isset($config['enabled']) || $config['enabled'] !== '1') {
            echo "enabled != 1 in db.ini\n";

            return;
        }

        IniConfig::defineDbIni($dbConfigPath);

        // Test database connection
        try {
            echo "Attempting to connect to database...\n";
            $pool = DbPool::get();
            $link = $pool->newLink();
            $result = $link->query('SELECT 1', []);

            if ($result) {
                echo "Database connection successful, setting dbAvailable = true\n";
                $dbAvailable = true;
            }
        } catch (Exception $e) {
            // Database not available, tests will be skipped
            echo 'Database connection failed: ' . $e->getMessage() . "\n";
            echo 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    });

    describe('Database connection', function () use (&$dbAvailable): void {
        it('creates connection pool', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            expect($pool)->toBeAnInstanceOf(IDbPool::class);
        });

        it('returns same instance (singleton)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool1 = DbPool::get();
            $pool2 = DbPool::get();
            expect($pool1)->toBe($pool2);
        });

        it('gets database link', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            expect($link)->toBeAnInstanceOf(IDbMySQLiLink::class);
        });

        it('poll method creates links', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $pool->poll();

            $link = $pool->newLink();
            expect($link)->toBeAnInstanceOf(IDbMySQLiLink::class);
        });
    });

    describe('Query execution', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Create test table if not exists
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Clean up test data
            $link->query("DELETE FROM dbtest_test_users WHERE email LIKE 'test_%'", []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Clean up test data
            $link->query("DELETE FROM dbtest_test_users WHERE email LIKE 'test_%'", []);
        });

        it('executes INSERT query', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $result = $link->query($sql, ['Test User', 'test_1@example.com']);

            expect($result)->toBeGreaterThan(0);

            // Verify insertion
            $sql = 'SELECT * FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, ['test_1@example.com']);
            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(1);
            expect($rows[0]['name'])->toBe('Test User');
        });

        it('executes SELECT query', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $link->query($sql, ['User 1', 'test_select_1@example.com']);
            $link->query($sql, ['User 2', 'test_select_2@example.com']);

            // Select data
            $sql = 'SELECT * FROM dbtest_test_users WHERE email LIKE ? ORDER BY name';
            $rows = $link->query($sql, ['test_select_%']);

            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(2);
            expect($rows[0]['name'])->toBe('User 1');
            expect($rows[1]['name'])->toBe('User 2');
        });

        it('executes UPDATE query', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $link->query($sql, ['Original Name', 'test_update@example.com']);

            // Update data
            $sql = 'UPDATE dbtest_test_users SET name = ? WHERE email = ?';
            $link->query($sql, ['Updated Name', 'test_update@example.com']);

            // Verify update
            $sql = 'SELECT name FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, ['test_update@example.com']);
            expect($rows[0]['name'])->toBe('Updated Name');
        });

        it('executes DELETE query', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Insert test data
            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $link->query($sql, ['To Delete', 'test_delete@example.com']);

            // Verify exists
            $sql = 'SELECT COUNT(*) as cnt FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, ['test_delete@example.com']);
            expect($rows[0]['cnt'])->toBe(1);

            // Delete data
            $sql = 'DELETE FROM dbtest_test_users WHERE email = ?';
            $link->query($sql, ['test_delete@example.com']);

            // Verify deleted
            $sql = 'SELECT COUNT(*) as cnt FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, ['test_delete@example.com']);
            expect($rows[0]['cnt'])->toBe(0);
        });

        it('returns empty array for no results', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            $sql = 'SELECT * FROM dbtest_test_users WHERE email LIKE ?';
            $rows = $link->query($sql, ['nonexistent_%']);

            expect($rows)->toBe([]);
        });

        it('escapes parameters properly', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Insert with special characters
            $specialName = "O'Connor";
            $specialEmail = 'test_escape@example.com';

            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $result = $link->query($sql, [$specialName, $specialEmail]);

            expect($result)->toBeGreaterThan(0);

            // Verify correct insertion
            $sql = 'SELECT name FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, [$specialEmail]);
            expect($rows[0]['name'])->toBe($specialName);
        });

        it('handles NULL values', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            // Add nullable column
            $sql = 'ALTER TABLE dbtest_test_users ADD COLUMN bio TEXT NULL';
            $link->query($sql, []);

            // Insert with NULL
            $sql = 'INSERT INTO dbtest_test_users (name, email, bio) VALUES (?, ?, NULL)';
            $result = $link->query($sql, ['Null Bio User', 'test_null@example.com']);

            expect($result)->toBeGreaterThan(0);

            // Verify NULL
            $sql = 'SELECT bio FROM dbtest_test_users WHERE email = ?';
            $rows = $link->query($sql, ['test_null@example.com']);
            expect($rows[0]['bio'])->toBeNull();

            // Clean up - remove nullable column
            $link->query('ALTER TABLE dbtest_test_users DROP COLUMN bio', []);
        });

        it('handles multiple inserts and returns correct IDs', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            $sql = 'INSERT INTO dbtest_test_users (name, email) VALUES (?, ?)';
            $id1 = $link->query($sql, ['User 1', 'test_multi_1@example.com']);
            $id2 = $link->query($sql, ['User 2', 'test_multi_2@example.com']);
            $id3 = $link->query($sql, ['User 3', 'test_multi_3@example.com']);

            expect($id1)->toBeGreaterThan(0);
            expect($id2)->toBe($id1 + 1);
            expect($id3)->toBe($id2 + 1);
        });
    });
});
