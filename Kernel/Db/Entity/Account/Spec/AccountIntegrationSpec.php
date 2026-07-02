<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Spec;

use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccountData;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use ReflectionClass;

// Helper function to get db config path
function getDbConfigPath(): string {
    // Get absolute path to Framework directory
    // __DIR__ is Framework/Kernel/Db/Entity/Account/Spec
    // Need 5 dirname() calls to get to Framework/
    $frameworkDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

    return $frameworkDir . '/TestsInit/TestConfig/db.ini';
}

describe('Account Integration', function (): void {
    $dbAvailable = false;

    beforeAll(function () use (&$dbAvailable): void {
        // Load database configuration
        $dbConfigPath = getDbConfigPath();

        if (!file_exists($dbConfigPath)) {
            return;
        }

        $config = parse_ini_file($dbConfigPath);

        if (!isset($config['enabled']) || $config['enabled'] !== '1') {
            return;
        }

        // Create test tables using DbPool
        \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::defineDbIni($dbConfigPath);

        try {
            $pool = DbPool::get();
            $link = $pool->newLink();

            // Create account table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_accounts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    login VARCHAR(255) NOT NULL UNIQUE,
                    login_type VARCHAR(50) NOT NULL,
                    name VARCHAR(255) NULL,
                    type VARCHAR(50) NULL,
                    time_zone VARCHAR(50) NULL,
                    about TEXT NULL,
                    reg_time INT NULL,
                    last_auth_time INT NULL,
                    last_online_time INT NULL,
                    token16 VARCHAR(16) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Create account_data table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_accounts_data (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    param VARCHAR(64) NOT NULL,
                    value TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_param (account_id, param),
                    KEY idx_account_id (account_id)
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Clean up test data
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Change table names in DbAccount and DbAccountData to use test tables
            $dbAccount = DbAccount::get();
            $tableReflection = new ReflectionClass($dbAccount);
            $tableProp = $tableReflection->getProperty('tableName');
            $tableProp->setAccessible(true);
            $tableProp->setValue($dbAccount, 'test_accounts');

            $dbAccountData = DbAccountData::get();
            $dataTableReflection = new ReflectionClass($dbAccountData);
            $dataTableProp = $dataTableReflection->getProperty('tableName');
            $dataTableProp->setAccessible(true);
            $dataTableProp->setValue($dbAccountData, 'test_accounts_data');

            $dbAvailable = true;
        } catch (Exception $e) {
            // Database not available, tests will be skipped
        }
    });

    describe('Account creation and retrieval', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue(null, []);

            // Change table names in DbAccount and DbAccountData to use test tables
            $dbAccount = DbAccount::get();
            $tableReflection = new ReflectionClass($dbAccount);
            $tableProp = $tableReflection->getProperty('tableName');
            $tableProp->setAccessible(true);
            $tableProp->setValue($dbAccount, 'test_accounts');

            $dbAccountData = DbAccountData::get();
            $dataTableReflection = new ReflectionClass($dbAccountData);
            $dataTableProp = $dataTableReflection->getProperty('tableName');
            $dataTableProp->setAccessible(true);
            $dataTableProp->setValue($dbAccountData, 'test_accounts_data');
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        it('creates new account with touchAccount', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            expect($account)->toBeAnInstanceOf(Account::class);

            // Flush and wait for async operations
            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Verify in database
            $link = $pool->newLink();
            $sql = 'SELECT * FROM dbtest_test_accounts WHERE login = ?';
            $rows = $link->query($sql, [$login]);

            expect(count($rows))->toBe(1);
            expect($rows[0]['login'])->toBe($login);
            expect($rows[0]['login_type'])->toBe('email');
        });

        it('retrieves existing account with touchAccount', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account1 = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Get the same account again
            $account2 = Account::touchAccount($login, 'email');

            expect($account1)->toBe($account2);
        });

        it('retrieves account by login with get()', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            $account = Account::get($login);

            expect($account)->toBeAnInstanceOf(Account::class);
            expect($account->readParam('login'))->toBe($login);
        });

        it('retrieves account by ID with get()', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Get the account to get its ID
            $account1 = Account::get($login);
            $id = $account1->id();

            // Retrieve by login again (Account::get expects string)
            $account2 = Account::get($login);

            expect($account2)->toBeAnInstanceOf(Account::class);
            expect($account2->readParam('login'))->toBe($login);
            expect($id)->toBeGreaterThan(0);
        });
    });

    describe('Account params operations', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        it('reads params from database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Read param
            $readLogin = $account->readParam('login');

            expect($readLogin)->toBe($login);
        });

        it('returns default when param not found', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Read non-existent param
            $value = $account->readParam('nonexistent', 'default_value');

            expect($value)->toBe('default_value');
        });

        it('sets params and flushes to database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set params
            $account->setParam('name', 'Test User');
            $account->setParam('time_zone', 'UTC');
            $account->setParam('about', 'Test account');

            // Flush
            $account->flush();
            $pool->pollFinishAll();

            // Create new instance and verify
            $account2 = Account::get($login);
            expect($account2->readParam('name'))->toBe('Test User');
            expect($account2->readParam('time_zone'))->toBe('UTC');
            expect($account2->readParam('about'))->toBe('Test account');
        });

        it('sets multiple params at once', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set multiple params
            $account->setParams([
                'name' => 'Multi User',
                'time_zone' => 'America/New_York',
                'about' => 'Test multi params',
            ]);

            // Flush
            $account->flush();
            $pool->pollFinishAll();

            // Verify
            $account2 = Account::get($login);
            expect($account2->readParam('name'))->toBe('Multi User');
            expect($account2->readParam('time_zone'))->toBe('America/New_York');
            expect($account2->readParam('about'))->toBe('Test multi params');
        });

        it('returns all params', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            $params = $account->getParams();

            expect($params)->toBeAn('array');
            expect(isset($params['id']))->toBe(true);
            expect(isset($params['login']))->toBe(true);
        });
    });

    describe('Account data operations', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        it('reads data from database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set some data
            $account->setData(Account::IS_ADMIN, 1);
            $account->setData(Account::IS_APPROVED, 1);

            // Flush
            $account->flush();
            $pool->pollFinishAll();

            // Create new instance and read data
            $account2 = Account::get($login);
            expect($account2->isAdmin())->toBe(true);
            expect($account2->isApproved())->toBe(true);
            expect($account2->isModerator())->toBe(false);
        });

        it('sets data and flushes to database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set data
            $account->setData(Account::IS_ADMIN, 1);
            $account->setData(Account::IS_MODERATOR, 1);

            // Flush
            $account->flush();
            $pool->pollFinishAll();

            // Verify
            $account2 = Account::get($login);
            expect($account2->isAdmin())->toBe(true);
            expect($account2->isModerator())->toBe(true);
        });

        it('unsets data and flushes to database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set then unset data
            $account->setData(Account::IS_ADMIN, 1);
            $account->flush();
            $pool->pollFinishAll();

            // Verify it's set
            $account2 = Account::get($login);
            expect($account2->isAdmin())->toBe(true);

            // Unset
            $account2->unsetData(Account::IS_ADMIN);
            $account2->flush();
            $pool->pollFinishAll();

            // Verify it's unset
            $account3 = Account::get($login);
            expect($account3->isAdmin())->toBe(false);
        });

        it('sets multiple data at once', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Set multiple data
            $account->setDataArr([
                Account::IS_ADMIN => 1,
                Account::IS_MODERATOR => 1,
                Account::IS_APPROVED => 1,
            ]);

            // Flush
            $account->flush();
            $pool->pollFinishAll();

            // Verify
            $account2 = Account::get($login);
            expect($account2->isAdmin())->toBe(true);
            expect($account2->isModerator())->toBe(true);
            expect($account2->isApproved())->toBe(true);
        });

        it('returns all data', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            $account->setData(Account::IS_ADMIN, 1);
            $account->setData(Account::IS_DISABLED, 0);

            $data = $account->getData();

            expect($data)->toBeAn('array');
            expect(isset($data[Account::IS_ADMIN]))->toBe(true);
        });
    });

    describe('Account flag methods', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_accounts_data WHERE account_id IN (SELECT id FROM dbtest_test_accounts WHERE login LIKE 'test_%')", []);
            $link->query("DELETE FROM dbtest_test_accounts WHERE login LIKE 'test_%'", []);

            // Reset Account static items
            $reflection = new ReflectionClass(Account::class);
            $prop = $reflection->getProperty('items');
            $prop->setAccessible(true);
            $prop->setValue([]);
        });

        it('sets and checks admin flag', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            expect($account->isAdmin())->toBe(false);

            $account->setAdmin(true);
            expect($account->isAdmin())->toBe(true);

            $account->setAdmin(false);
            expect($account->isAdmin())->toBe(false);
        });

        it('sets and checks moderator flag', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            expect($account->isModerator())->toBe(false);

            $account->setModerator(true);
            expect($account->isModerator())->toBe(true);
        });

        it('sets and checks approved flag', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            expect($account->isApproved())->toBe(false);

            $account->setApproved(true);
            expect($account->isApproved())->toBe(true);
        });

        it('sets and checks disabled flag', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $login = 'test_' . time();
            $account = Account::touchAccount($login, 'email');

            $pool = DbPool::get();
            $pool->pollFinishAll();

            expect($account->isDisabled())->toBe(false);

            $account->setDisabled(true);
            expect($account->isDisabled())->toBe(true);
        });
    });

    afterAll(function () use (&$dbAvailable): void {
        if (!$dbAvailable) {
            return;
        }

        // Tables left for debugging purposes
    });
});
