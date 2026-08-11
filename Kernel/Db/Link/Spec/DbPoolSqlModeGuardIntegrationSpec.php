<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec;

use mysqli;

/**
 * M1/M2 integration tests for sql_mode guard.
 *
 * These tests verify the sql_mode guard against a live MySQL server
 * to confirm the current server's sql_mode is safe (does not contain
 * NO_BACKSLASH_ESCAPES or ANSI_QUOTES).
 *
 * This file follows the *IntegrationSpec.php naming convention so it is
 * EXCLUDED from the "composer test:kernel" (no-DB) job and ONLY runs
 * via "composer test:kernel-integration" (DB-backed), where MySQL is
 * available. See kahlan-config.php lines 20-43.
 */
describe('DbPool sql_mode guard integration (M1/M2 live server)', function (): void {
    it('verifies current server sql_mode is safe', function (): void {
        // Connect to the test MySQL instance
        $mysqli = @new mysqli('127.0.0.1', 'test', 'test', 'test', 3306);

        if ($mysqli->connect_error) {
            skip('MySQL connection not available for live test');
        }

        // Query the actual session sql_mode
        $result = $mysqli->query('SELECT @@session.sql_mode AS sql_mode');

        if (!$result) {
            $mysqli->close();

            skip('Could not query sql_mode');
        }

        $row = $result->fetch_assoc();
        $result->free();
        $sqlMode = $row['sql_mode'] ?? '';

        // Verify the guard logic would accept this mode
        $dangerousModes = ['NO_BACKSLASH_ESCAPES', 'ANSI_QUOTES'];
        $found = false;

        foreach ($dangerousModes as $mode) {
            if (stripos($sqlMode, $mode) !== false) {
                $found = true;

                break;
            }
        }

        $mysqli->close();

        expect($found)->toBe(false);
        expect($sqlMode)->not->toContain('NO_BACKSLASH_ESCAPES');
        expect($sqlMode)->not->toContain('ANSI_QUOTES');
    });
});
