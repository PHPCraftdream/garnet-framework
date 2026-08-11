<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use Exception;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use ReflectionClass;

/**
 * M1/M2 integration tests for sql_mode guard — real end-to-end tests.
 *
 * These tests verify the sql_mode guard in DbPool::newLink() by calling
 * the ACTUAL production code with db.ini configs whose MYSQL_ATTR_INIT_COMMAND
 * sets dangerous modes via SET SESSION. The guard runs AFTER the init command,
 * so this exercises the exact code path production would hit.
 *
 * This file follows the *IntegrationSpec.php naming convention so it is
 * EXCLUDED from the "composer test:kernel" (no-DB) job and ONLY runs
 * via "composer test:kernel-integration" (DB-backed), where MySQL is
 * available. See kahlan-config.php lines 20-43.
 */
describe('DbPool sql_mode guard integration (M1/M2 end-to-end)', function (): void {
    $dbAvailable = false;
    $originalDbIniPath = null;

    // Close all DbPool connections after all tests to avoid leaking links
    // into subsequent integration spec files (they run in the same PHP process).
    afterAll(function (): void {
        DbPool::closeAll();
    });

    beforeAll(function () use (&$dbAvailable, &$originalDbIniPath): void {
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

        // Save the original db.ini path for restoration
        $originalDbIniPath = $dbConfigPath;

        // Define the database config for DbPool
        IniConfig::defineDbIni($dbConfigPath);

        // Test database connection with safe config
        try {
            $pool = DbPool::get();
            $link = $pool->newLink();
            $result = $link->query('SELECT 1 AS result_val', []);

            if ($result) {
                $dbAvailable = true;
            }
        } catch (DbException $e) {
            // Database not available, tests will be skipped
            echo 'Database connection failed: ' . $e->getMessage() . "\n";
        }
    });

    // Helper: clear the cached IniConfig instance so defineDbIni() takes effect
    $clearIniConfigCache = function (): void {
        $reflection = new ReflectionClass(IniConfig::class);
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);
        $itemsProperty->setValue([]);
    };

    // Helper: create a temporary db.ini file with a custom init command
    // and point DbPool to it. Returns the temp file path for cleanup.
    $createTempDbIni = function (string $initCommand): string {
        $originalPath = __DIR__ . '/../../../../TestsInit/TestConfig/db.ini';
        $originalContent = file_get_contents($originalPath);

        // Replace the init command line
        $newContent = preg_replace(
            '/options\[MYSQL_ATTR_INIT_COMMAND\] = ".*"/',
            'options[MYSQL_ATTR_INIT_COMMAND] = "' . $initCommand . '"',
            $originalContent
        );

        // Create a temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'garnet_db_test_');
        unlink($tempPath); // remove the file so we can recreate it with .ini extension
        $tempIniPath = $tempPath . '.ini';

        file_put_contents($tempIniPath, $newContent);

        return $tempIniPath;
    };

    // Helper: restore DbPool singleton state for next test
    $resetDbPool = function () use ($clearIniConfigCache): void {
        DbPool::closeAll();
        $clearIniConfigCache();
    };

    describe('ANSI_QUOTES dangerous mode detection (M1)', function () use (
        &$dbAvailable,
        $createTempDbIni,
        $resetDbPool
    ): void {
        it('throws DbException when init command sets ANSI_QUOTES', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Create temp db.ini with ANSI_QUOTES init command
            $tempIniPath = $createTempDbIni("SET SESSION sql_mode='ANSI_QUOTES'");

            try {
                // Point DbPool to the temp config
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                // This SHOULD throw DbException due to the guard
                $exceptionThrown = false;
                $exceptionMessage = '';

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                // Clean up temp file
                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                // Verify the exception was thrown
                expect($exceptionThrown)->toBe(true);
                expect($exceptionMessage)->toMatch('/ANSI_QUOTES/i');
            } finally {
                $resetDbPool();
            }
        });

        it('throws DbException when ANSI_QUOTES is combined with other modes', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Create temp db.ini with ANSI_QUOTES among other modes
            $tempIniPath = $createTempDbIni(
                "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ANSI_QUOTES'"
            );

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                $exceptionThrown = false;
                $exceptionMessage = '';

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                expect($exceptionThrown)->toBe(true);
                expect($exceptionMessage)->toMatch('/ANSI_QUOTES/i');
            } finally {
                $resetDbPool();
            }
        });
    });

    describe('NO_BACKSLASH_ESCAPES dangerous mode detection (M2)', function () use (
        &$dbAvailable,
        $createTempDbIni,
        $resetDbPool
    ): void {
        it('throws DbException when init command sets NO_BACKSLASH_ESCAPES', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Create temp db.ini with NO_BACKSLASH_ESCAPES init command
            $tempIniPath = $createTempDbIni("SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'");

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                $exceptionThrown = false;
                $exceptionMessage = '';

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                expect($exceptionThrown)->toBe(true);
                expect($exceptionMessage)->toMatch('/NO_BACKSLASH_ESCAPES/i');
            } finally {
                $resetDbPool();
            }
        });

        it('throws DbException when NO_BACKSLASH_ESCAPES is combined with other modes', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            $tempIniPath = $createTempDbIni(
                "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES,NO_ENGINE_SUBSTITUTION'"
            );

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                $exceptionThrown = false;
                $exceptionMessage = '';

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                expect($exceptionThrown)->toBe(true);
                expect($exceptionMessage)->toMatch('/NO_BACKSLASH_ESCAPES/i');
            } finally {
                $resetDbPool();
            }
        });
    });

    describe('safe init commands succeed', function () use (
        &$dbAvailable,
        $createTempDbIni,
        $resetDbPool,
        &$originalDbIniPath
    ): void {
        it('succeeds with normal SET NAMES init command', function () use (
            &$dbAvailable
            ,
            $resetDbPool,
            &$originalDbIniPath
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Restore the original safe config
            IniConfig::defineDbIni($originalDbIniPath);

            $pool = DbPool::get();

            // This should succeed without throwing
            $link = $pool->newLink();
            expect($link)->not->toBeNull();

            // Verify the link actually works
            $result = $link->query('SELECT 1 AS result_val', []);
            expect($result[0]['result_val'])->toBe(1);

            $resetDbPool();
        });

        it('succeeds with safe sql_mode init command', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Create temp db.ini with a safe sql_mode
            $tempIniPath = $createTempDbIni(
                "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'"
            );

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                // This should succeed
                $link = $pool->newLink();
                expect($link)->not->toBeNull();

                $result = $link->query('SELECT 1 AS result_val', []);
                expect($result[0]['result_val'])->toBe(1);

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }
            } finally {
                $resetDbPool();
            }
        });
    });

    describe('result-returning init commands drain correctly', function () use (
        &$dbAvailable,
        $createTempDbIni,
        $resetDbPool
    ): void {
        it('result-returning init command alone succeeds (drain consumed result)', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // Create temp db.ini with a result-returning init command
            $tempIniPath = $createTempDbIni('SELECT 1');

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                // The drain logic should consume the SELECT 1 result,
                // allowing the guard probe to run successfully.
                $exceptionThrown = false;

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                }

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                // Should NOT throw — the drain worked
                expect($exceptionThrown)->toBe(false);
            } finally {
                $resetDbPool();
            }
        });
    });

    describe('guard probe query failure handling', function () use (
        &$dbAvailable,
        $createTempDbIni,
        $resetDbPool
    ): void {
        it('throws DbException when guard detects dangerous mode', function () use (
            &$dbAvailable,
            $createTempDbIni,
            $resetDbPool
        ): void {
            if (!$dbAvailable) {
                skip('MySQL connection not available for live test');
            }

            $resetDbPool();

            // This is already tested by ANSI_QUOTES and NO_BACKSLASH_ESCAPES tests above.
            // This test explicitly verifies that DbException (not a raw mysqli exception)
            // is thrown with the correct message format.
            $tempIniPath = $createTempDbIni("SET SESSION sql_mode='ANSI_QUOTES'");

            try {
                IniConfig::defineDbIni($tempIniPath);

                $pool = DbPool::get();

                $exceptionThrown = false;
                $isDbException = false;
                $exceptionMessage = '';

                try {
                    $link = $pool->newLink();
                } catch (DbException $e) {
                    $exceptionThrown = true;
                    $isDbException = true;
                    $exceptionMessage = $e->getMessage();
                } catch (Exception $e) {
                    $exceptionThrown = true;
                    $exceptionMessage = $e->getMessage();
                }

                if (file_exists($tempIniPath)) {
                    unlink($tempIniPath);
                }

                expect($exceptionThrown)->toBe(true);
                expect($isDbException)->toBe(true);
                expect($exceptionMessage)->toMatch('/sql_mode/i');
            } finally {
                $resetDbPool();
            }
        });
    });
});
