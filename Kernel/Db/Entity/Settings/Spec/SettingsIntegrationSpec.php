<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Spec;

use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Settings;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\SettingsTable;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use ReflectionClass;

// Helper function to get db config path
function getDbConfigPath(): string {
    // Get absolute path to Framework directory
    // __DIR__ is Framework/Kernel/Db/Entity/Settings/Spec
    // Need 5 dirname() calls to get to Framework/
    $frameworkDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

    return $frameworkDir . '/TestsInit/TestConfig/db.ini';
}

describe('Settings Integration', function (): void {
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

        // Create test table using QueryExPdo
        \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::defineDbIni($dbConfigPath);

        try {
            $pool = DbPool::get();
            $link = $pool->newLink();

            // Create test table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    param VARCHAR(255) NOT NULL UNIQUE,
                    value TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);

            // Clean up test data
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);

            // Change table name in SettingsTable to use test table
            $settingsTable = SettingsTable::get();
            $tableReflection = new ReflectionClass($settingsTable);
            $tableProp = $tableReflection->getProperty('tableName');
            $tableProp->setValue($settingsTable, 'test_settings');

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
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);

            // Create a fresh Settings instance
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('data');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('changed');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('changedData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('unsetData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('originalData');
            $prop->setValue($settings, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        it('returns default value when param does not exist', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $value = $settings->getValue('test_nonexistent', 'default_value');

            expect($value)->toBe('default_value');
        });

        it('sets and retrieves value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param1', 'value1');

            // Value should be available in memory
            expect($settings->getValue('test_param1'))->toBe('value1');
        });

        it('sets multiple values and retrieves them', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param1', 'value1');
            $settings->setValue('test_param2', 'value2');
            $settings->setValue('test_param3', 'value3');

            expect($settings->getValue('test_param1'))->toBe('value1');
            expect($settings->getValue('test_param2'))->toBe('value2');
            expect($settings->getValue('test_param3'))->toBe('value3');
        });

        it('updates existing value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param', 'original_value');
            expect($settings->getValue('test_param'))->toBe('original_value');

            $settings->setValue('test_param', 'updated_value');
            expect($settings->getValue('test_param'))->toBe('updated_value');
        });

        it('does not track changes when value is same', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param', 'value1');

            // Set same value again
            $settings->setValue('test_param', 'value1');

            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('changedData');
            $changedData = $prop->getValue($settings);

            // Should not be in changedData since value didn't change
            expect(isset($changedData['test_param']))->toBe(false);
        });

        it('handles empty string values', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_empty', '');

            expect($settings->getValue('test_empty'))->toBe('');
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
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);

            // Create a fresh Settings instance
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('data');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('changed');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('changedData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('unsetData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('originalData');
            $prop->setValue($settings, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        it('removes value from memory', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param', 'value');
            expect($settings->getValue('test_param'))->toBe('value');

            $settings->unsetValue('test_param');
            expect($settings->getValue('test_param'))->toBe('');
        });

        it('does not throw error when unsetting non-existent value', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            expect(function () use ($settings): void {
                $settings->unsetValue('test_nonexistent');
            })->not->toThrow();
        });

        it('removes value from changedData if it was set', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param', 'value');

            // Should be in changedData
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('changedData');
            $changedData = $prop->getValue($settings);
            expect(isset($changedData['test_param']))->toBe(true);

            // Unset
            $settings->unsetValue('test_param');

            // Should not be in changedData anymore
            $prop = $reflection->getProperty('changedData');
            $changedData = $prop->getValue($settings);
            expect(isset($changedData['test_param']))->toBe(false);

            // Should be in unsetData
            $prop = $reflection->getProperty('unsetData');
            $unsetData = $prop->getValue($settings);
            expect(isset($unsetData['test_param']))->toBe(true);
        });

        it('sets changed flag to false when no changedData left', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param', 'value');

            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('changed');

            // Should be changed
            expect($prop->getValue($settings))->toBe(true);

            // Unset
            $settings->unsetValue('test_param');

            // Should not be changed anymore
            expect($prop->getValue($settings))->toBe(false);
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
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);

            // Create a fresh Settings instance
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('data');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('changed');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('changedData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('unsetData');
            $prop->setValue($settings, []);
            $prop = $reflection->getProperty('originalData');
            $prop->setValue($settings, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        it('returns all data as array', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_param1', 'value1');
            $settings->setValue('test_param2', 'value2');
            $settings->setValue('test_param3', 'value3');

            $allData = $settings->getAllData();

            expect($allData)->toBeAn('array');
            expect($allData['test_param1'])->toBe('value1');
            expect($allData['test_param2'])->toBe('value2');
            expect($allData['test_param3'])->toBe('value3');
        });

        it('returns empty array when no data', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $allData = $settings->getAllData();

            expect($allData)->toBe([]);
        });
    });

    describe('read() from database', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        it('reads values from database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Insert test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $sql = 'INSERT INTO dbtest_test_settings (param, value) VALUES (?, ?)';
            $link->query($sql, ['test_read1', 'value1']);
            $link->query($sql, ['test_read2', 'value2']);
            $link->query($sql, ['test_read3', 'value3']);

            // Create a fresh Settings instance
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);

            // Read from database
            $settings->read();

            expect($settings->getValue('test_read1'))->toBe('value1');
            expect($settings->getValue('test_read2'))->toBe('value2');
            expect($settings->getValue('test_read3'))->toBe('value3');
        });

        it('only reads once (caching)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Insert test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $sql = 'INSERT INTO dbtest_test_settings (param, value) VALUES (?, ?)';
            $link->query($sql, ['test_cache', 'original_value']);

            // Create Settings instance and read
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);

            $settings->read();
            expect($settings->getValue('test_cache'))->toBe('original_value');

            // Update database directly
            $sql = 'UPDATE dbtest_test_settings SET value = ? WHERE param = ?';
            $link->query($sql, ['updated_value', 'test_cache']);

            // Read again - should use cache
            $settings->read();
            expect($settings->getValue('test_cache'))->toBe('original_value');
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
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);

            // Create a fresh Settings instance
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);
            $prop = $reflection->getProperty('data');
            $prop->setValue($settings, []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_settings WHERE param LIKE 'test_%'", []);
        });

        it('saves changed values to database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $settings = Settings::get();
            $settings->setValue('test_flush1', 'value1');
            $settings->setValue('test_flush2', 'value2');

            // Flush changes
            $settings->flush();

            // Wait for async operations
            $pool = DbPool::get();
            $pool->pollFinishAll();

            // Verify in database
            $link = $pool->newLink();
            $sql = "SELECT * FROM dbtest_test_settings WHERE param IN ('test_flush1', 'test_flush2')";
            $rows = $link->query($sql, []);

            expect(count($rows))->toBe(2);
        });

        it('deletes unset values from database', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Insert initial data
            $pool = DbPool::get();
            $link = $pool->newLink();
            $sql = 'INSERT INTO dbtest_test_settings (param, value) VALUES (?, ?)';
            $link->query($sql, ['test_unsync', 'value_to_delete']);

            // Create fresh Settings instance and read
            $settings = Settings::get();
            $reflection = new ReflectionClass($settings);
            $prop = $reflection->getProperty('read');
            $prop->setValue($settings, false);

            $settings->read();

            // Unset value
            $settings->unsetValue('test_unsync');

            // Flush
            $settings->flush();

            // Wait for async operations
            $pool->pollFinishAll();

            // Verify deleted from database
            $sql = 'SELECT * FROM dbtest_test_settings WHERE param = ?';
            $rows = $link->query($sql, ['test_unsync']);

            expect(count($rows))->toBe(0);
        });
    });

    afterAll(function () use (&$dbAvailable): void {
        if (!$dbAvailable) {
            return;
        }

        // Tables left for debugging purposes
    });
});
