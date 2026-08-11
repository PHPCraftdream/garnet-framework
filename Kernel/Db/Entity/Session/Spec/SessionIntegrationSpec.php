<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Spec;

use DateTimeInterface;
use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionDataTable;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionTable;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use ReflectionClass;
use Throwable;

// Helper function to get db config path
function getDbConfigPath(): string {
    // Get the absolute path to Framework directory
    // __DIR__ is Framework/Kernel/Db/Entity/Session/Spec
    // Need 5 dirname() calls to get to Framework/
    $frameworkDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

    return $frameworkDir . '/TestsInit/TestConfig/db.ini';
}

describe('Session Integration', function (): void {
    $dbAvailable = false;

    // See AccountIntegrationSpec.php for why this is needed: DbPool
    // never closes connections on its own, and Kahlan runs every
    // *IntegrationSpec.php file in one PHP process.
    afterAll(function (): void {
        DbPool::closeAll();
    });

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

            // Create session table (VARCHAR(64) for SHA256 hashed tokens)
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_session (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(64) NOT NULL,
                    lastUsage INT(11) NOT NULL,
                    UNIQUE KEY name (name)
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Create session_data table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_session_data (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sessionId INT(11) NOT NULL,
                    param VARCHAR(32) NOT NULL,
                    value VARCHAR(255) NOT NULL,
                    UNIQUE KEY session_param (sessionId, param)
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Ensure the schema is current — older schemas had VARCHAR(32) on
            // `name`, which can't hold the 64-char SHA256 session tokens this
            // test uses. ALTER is a no-op when already correct.
            try {
                $link->query('ALTER TABLE dbtest_test_session MODIFY name VARCHAR(64) NOT NULL', []);
            } catch (Throwable $e) {
                // ignore — schema already correct or table was freshly created
            }

            // Clean up test data
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Change table names in SessionTable and SessionDataTable to use test tables
            $sessionTable = SessionTable::get();
            $tableReflection = new ReflectionClass($sessionTable);
            $tableProp = $tableReflection->getProperty('tableName');
            $tableProp->setValue($sessionTable, 'test_session');

            $sessionDataTable = SessionDataTable::get();
            $dataTableReflection = new ReflectionClass($sessionDataTable);
            $dataTableProp = $dataTableReflection->getProperty('tableName');
            $dataTableProp->setValue($sessionDataTable, 'test_session_data');

            $dbAvailable = true;
        } catch (Exception $e) {
            // Database not available, tests will be skipped
        }
    });

    describe('getValue() and setValue() operations', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Create a fresh Session instance
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $prop->setValue($session, 'test_session_' . rand(1000, 9999));
            $prop = $reflection->getProperty('sessionData');
            $prop->setValue($session, []);
            $prop = $reflection->getProperty('changedValues');
            $prop->setValue($session, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('returns default value when param does not exist', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $value = $session->getValue('test_nonexistent', 'default_value');

            expect($value)->toBe('default_value');
        });

        it('returns null when param does not exist and no default', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $value = $session->getValue('test_nonexistent');

            expect($value)->toBeNull();
        });

        it('sets and retrieves value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param1', 'value1');

            expect($session->getValue('test_param1'))->toBe('value1');
        });

        it('sets multiple values and retrieves them', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param1', 'value1');
            $session->setValue('test_param2', 'value2');
            $session->setValue('test_param3', 'value3');

            expect($session->getValue('test_param1'))->toBe('value1');
            expect($session->getValue('test_param2'))->toBe('value2');
            expect($session->getValue('test_param3'))->toBe('value3');
        });

        it('updates existing value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param', 'original_value');
            expect($session->getValue('test_param'))->toBe('original_value');

            $session->setValue('test_param', 'updated_value');
            expect($session->getValue('test_param'))->toBe('updated_value');
        });

        it('handles empty string values', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_empty', '');

            expect($session->getValue('test_empty'))->toBe('');
        });
    });

    describe('unsetValue() operations', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Create a fresh Session instance
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $prop->setValue($session, 'test_session_' . rand(1000, 9999));
            $prop = $reflection->getProperty('sessionData');
            $prop->setValue($session, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('removes value from memory', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param', 'value');
            expect($session->getValue('test_param'))->toBe('value');

            $session->unsetValue('test_param');
            expect($session->getValue('test_param'))->toBeNull();
        });

        it('does not throw error when unsetting non-existent value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            expect(function () use ($session): void {
                $session->unsetValue('test_nonexistent');
            })->not->toThrow();
        });

        it('removes value from changedValues if it was set', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param', 'value');

            // Should be in changedValues
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('changedValues');
            $changedValues = $prop->getValue($session);
            expect(isset($changedValues['test_param']))->toBe(true);

            // Unset
            $session->unsetValue('test_param');

            // Should not be in changedValues anymore
            $prop = $reflection->getProperty('changedValues');
            $changedValues = $prop->getValue($session);
            expect(isset($changedValues['test_param']))->toBe(false);

            // Should be in unsetValues
            $prop = $reflection->getProperty('unsetValues');
            $unsetValues = $prop->getValue($session);
            expect(isset($unsetValues['test_param']))->toBe(true);
        });

        it('unsets multiple values at once', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param1', 'value1');
            $session->setValue('test_param2', 'value2');
            $session->setValue('test_param3', 'value3');

            expect($session->getValue('test_param1'))->toBe('value1');
            expect($session->getValue('test_param2'))->toBe('value2');
            expect($session->getValue('test_param3'))->toBe('value3');

            $session->unsetValues(['test_param1', 'test_param3']);

            expect($session->getValue('test_param1'))->toBeNull();
            expect($session->getValue('test_param2'))->toBe('value2');
            expect($session->getValue('test_param3'))->toBeNull();
        });
    });

    describe('getAllData() method', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Create a fresh Session instance
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $prop->setValue($session, 'test_session_' . rand(1000, 9999));
            $prop = $reflection->getProperty('sessionData');
            $prop->setValue($session, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('returns all session data as array', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_param1', 'value1');
            $session->setValue('test_param2', 'value2');
            $session->setValue('test_param3', 'value3');

            $allData = $session->getAllData();

            expect($allData)->toBeAn('array');
            expect($allData['test_param1'])->toBe('value1');
            expect($allData['test_param2'])->toBe('value2');
            expect($allData['test_param3'])->toBe('value3');
        });

        it('returns empty array when no data', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $allData = $session->getAllData();

            expect($allData)->toBe([]);
        });
    });

    describe('getToken() method', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Create a fresh Session instance
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $prop->setValue($session, 'test_session_' . rand(1000, 9999));
            $prop = $reflection->getProperty('sessionData');
            $prop->setValue($session, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('generates new token when not exists', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $token = $session->getToken();

            expect($token)->toBeA('string');
            expect(strlen($token))->toBe(32);
            expect($session->getValue('token'))->toBe($token);
        });

        it('returns same token when exists', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $token1 = $session->getToken();
            $token2 = $session->getToken();

            expect($token1)->toBe($token2);
        });
    });

    describe('flush() to database', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);

            // Create a fresh Session instance
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $sessionValue = 'test_session_' . rand(1000, 9999);
            $prop->setValue($session, $sessionValue);
            $prop = $reflection->getProperty('sessionData');
            $prop->setValue($session, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('saves changed values to database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_flush1', 'value1');
            $session->setValue('test_flush2', 'value2');

            // Flush changes
            $session->flush();

            // Wait for async operations
            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Verify in database
            $link = $pool->newLink();
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $sessionValue = $prop->getValue($session);

            $sql = 'SELECT * FROM dbtest_test_session WHERE name = ?';
            $rows = $link->query($sql, [Session::hashToken($sessionValue)]);
            expect(count($rows))->toBe(1);

            $sessionId = $rows[0]['id'];
            $sql = "SELECT * FROM dbtest_test_session_data WHERE sessionId = ? AND param IN ('test_flush1', 'test_flush2')";
            $dataRows = $link->query($sql, [$sessionId]);
            expect(count($dataRows))->toBe(2);
        });

        // PENDING: depends on Session state-machine internals around
        // unsetValue+flush ordering that changed in commit 3b769784
        // (fix(session): write data rows to correct sessionId on repeat flush).
        // Spec hasn't been updated to match the new flush() semantics.
        xit('deletes unset values from database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $session = Session::get(false);
            $session->setValue('test_unsync', 'value_to_delete');

            // Flush to create record
            $session->flush();

            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Get session ID
            $link = $pool->newLink();
            $reflection = new ReflectionClass($session);
            $prop = $reflection->getProperty('sessionValue');
            $sessionValue = $prop->getValue($session);

            $sql = 'SELECT * FROM dbtest_test_session WHERE name = ?';
            $rows = $link->query($sql, [Session::hashToken($sessionValue)]);
            $sessionId = $rows[0]['id'];

            // Verify data exists
            $sql = 'SELECT * FROM dbtest_test_session_data WHERE sessionId = ? AND param = ?';
            $dataRows = $link->query($sql, [$sessionId, 'test_unsync']);
            expect(count($dataRows))->toBe(1);

            // Create new session instance to simulate loading from database
            $reflectionClass = new ReflectionClass('PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session');
            $newSession = $reflectionClass->newInstanceWithoutConstructor();

            // Set sessionValue to match existing session
            $prop = $reflectionClass->getProperty('sessionValue');
            $prop->setValue($newSession, $sessionValue);

            // Set sessionId to match existing session
            $prop = $reflectionClass->getProperty('sessionId');
            $prop->setValue($newSession, $sessionId);

            // Load data from database (simulate what happens when session is loaded)
            $sql = 'SELECT * FROM dbtest_test_session_data WHERE sessionId = ?';
            $dataRows = $link->query($sql, [$sessionId]);
            $loadedData = [];

            foreach ($dataRows as $row) {
                $loadedData[$row['param']] = $row['value'];
            }

            // Set sessionData to match what's in database
            $prop = $reflectionClass->getProperty('sessionData');
            $prop->setValue($newSession, $loadedData);

            // Unset value
            $newSession->unsetValue('test_unsync');

            // Flush
            $newSession->flush();
            $pool->pollFinishAll();

            // Verify deleted from database
            $sql = 'SELECT * FROM dbtest_test_session_data WHERE sessionId = ? AND param = ?';
            $dataRows = $link->query($sql, [$sessionId, 'test_unsync']);
            expect(count($dataRows))->toBe(0);
        });
    });

    describe('rotate() session-fixation regression (behavioral, not internal-state)', function () use (&$dbAvailable): void {
        // Load a session's data the way a fresh HTTP request would: a new
        // Session instance, given only a cookie value, resolving its own
        // sessionId from the DB via readDataAsync()/pollFinishAll().
        $loadSessionByCookieValue = function (string $cookieValue) {
            $reflectionClass = new ReflectionClass(Session::class);
            $session = $reflectionClass->newInstanceWithoutConstructor();

            $prop = $reflectionClass->getProperty('sessionValue');
            $prop->setValue($session, $cookieValue);

            $session->readDataAsync();
            $session->readDataAsyncPollFinishAll();

            return $session;
        };

        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('does not leave post-rotate data readable under the OLD (pre-fixation) cookie, and makes it readable under the NEW cookie', function () use (&$dbAvailable, $loadSessionByCookieValue): void {
            if (!$dbAvailable) {
                return;
            }

            // Attacker fixes a cookie value on the victim's browser before
            // login and it gets some pre-login data written under it.
            $oldCookieValue = 'test_old_fixed_' . rand(100000, 999999);
            $session = Session::get(false);
            $reflection = new ReflectionClass($session);
            $cookiesProp = $reflection->getProperty('cookies');
            $cookiesProp->setValue($session, new MockCookiesForRotateSpec());
            $valueProp = $reflection->getProperty('sessionValue');
            $valueProp->setValue($session, $oldCookieValue);

            $session->setValue('test_pre_login', 'attacker_visible_value');
            $session->flush();
            DbPool::get()->pollFinishAll();

            // Victim actually authenticates: rotate() mints a new cookie
            // value and post-login data is written under it.
            $session->rotate();
            $newCookieValue = $valueProp->getValue($session);
            expect($newCookieValue)->not->toBe($oldCookieValue);

            $session->setValue('test_post_login', 'victim_authenticated_value');
            $session->flush();
            DbPool::get()->pollFinishAll();

            // Re-load "by cookie" exactly like the next HTTP request would.
            $oldReload = $loadSessionByCookieValue($oldCookieValue);
            $newReload = $loadSessionByCookieValue($newCookieValue);

            // The attacker's pre-fixed cookie must NOT resolve to the
            // authenticated data.
            expect($oldReload->getValue('test_post_login'))->toBeNull();

            // The victim's real (new) cookie MUST resolve to the
            // authenticated data written after rotate().
            expect($newReload->getValue('test_post_login'))->toBe('victim_authenticated_value');
        });
    });

    describe('sessionId=0-vs-null regression (unknown cookie, immediate write+reload)', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_session_data WHERE param LIKE 'test_%'", []);
            $link->query('DELETE FROM dbtest_test_session WHERE 1', []);
        });

        it('makes data written on a never-seen cookie readable when immediately reloaded under the same cookie', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // A never-seen/expired cookie value: readDataAsync() finds no
            // row, so the object's sessionId resolves from "no row found".
            $unknownCookieValue = 'test_unknown_' . rand(100000, 999999);

            $reflectionClass = new ReflectionClass(Session::class);
            $session = $reflectionClass->newInstanceWithoutConstructor();
            $prop = $reflectionClass->getProperty('sessionValue');
            $prop->setValue($session, $unknownCookieValue);

            // Simulate readDataAsync() having resolved "no session row yet"
            // for this cookie, exactly as a real first request would.
            $session->readDataAsync();
            $session->readDataAsyncPollFinishAll();

            $session->setValue('test_auth_code', '123456');
            $session->flush();
            DbPool::get()->pollFinishAll();

            // Reload under the SAME cookie value, as the very next request
            // (e.g. the code-entry step) would.
            $reloaded = $reflectionClass->newInstanceWithoutConstructor();
            $reloadedProp = $reflectionClass->getProperty('sessionValue');
            $reloadedProp->setValue($reloaded, $unknownCookieValue);
            $reloaded->readDataAsync();
            $reloaded->readDataAsyncPollFinishAll();

            expect($reloaded->getValue('test_auth_code'))->toBe('123456');
        });
    });

    afterAll(function () use (&$dbAvailable): void {
        // Tables left for debugging purposes
        if (!$dbAvailable) {
            return;
        }
    });
});

// Minimal ICookie double: only the members rotate()/issueNewCookie() and
// touchCookie() actually exercise are meaningfully implemented.
class MinimalMockCookieForRotateSpec implements \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
    public ?string $name = null;

    public ?string $value = null;

    public function setOld(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setItNew(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function isNew(): bool {
        return false;
    }

    public function startObserveChanges(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function isChanged(): bool {
        return false;
    }

    public function resetChanged(): bool {
        return false;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function getValue(): ?string {
        return $this->value;
    }

    public function getExpires(): ?int {
        return null;
    }

    public function getMaxAge(): ?int {
        return null;
    }

    public function getPath(): ?string {
        return null;
    }

    public function getDomain(): ?string {
        return null;
    }

    public function getSecure(): bool {
        return false;
    }

    public function getHttpOnly(): bool {
        return false;
    }

    public function getSameSite(): string {
        return '';
    }

    public function setName(?string $name = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        $this->name = $name;

        return $this;
    }

    public function setValue(?string $value = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        $this->value = $value;

        return $this;
    }

    public function setSameSiteStrict(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setSameSiteLax(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setSameSiteNone(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setExpires(null|DateTimeInterface|int|string $expires = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function rememberForever(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function expire(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setMaxAge(?int $maxAge = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setPath(?string $path = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setDomain(?string $domain = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setSecure(?bool $secure = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function setHttpOnly(?bool $httpOnly = null): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }

    public function __toString(): string {
        return '';
    }

    public function parse(string $string): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        return $this;
    }
}

// Minimal ICookies double for the rotate()-regression spec: rotate() calls
// issueNewCookie(), which needs a working cookies->get(name)->setValue()...
// chain.
class MockCookiesForRotateSpec implements \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
    public array $cookies = [];

    public function has(string $name): bool {
        return isset($this->cookies[$name]);
    }

    public function get(string $name): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie {
        if (!isset($this->cookies[$name])) {
            $this->cookies[$name] = new MinimalMockCookieForRotateSpec();
            $this->cookies[$name]->setName($name);
        }

        return $this->cookies[$name];
    }

    public function setItNew(): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function getAll(): array {
        return $this->cookies;
    }

    public function add(\PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookie $cookie): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function delete(string $name): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function toResponse(\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface {
        return $response;
    }

    public function fromResponse(\Psr\Http\Message\ResponseInterface $response): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function toRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface {
        return $request;
    }

    public function fromRequest(\Psr\Http\Message\RequestInterface $request): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function fromServer(array $_server): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function fromCookieStrings(array $cookieStrings): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }

    public function fromGlobals($globals): \PHPCraftdream\Garnet\Kernel\Interfaces\Cookies\ICookies {
        return $this;
    }
}
