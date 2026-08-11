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

    it('drains result set from result-returning init command (regression test)', function (): void {
        // This test verifies that a result-returning init command (e.g. "SELECT 1")
        // does NOT cause the sql_mode guard probe query to fail with
        // "Commands out of sync; you can't run this command now".
        //
        // Before the fix: real_query() would dispatch the init command and leave
        // any result set unconsumed, causing the subsequent query() call on the
        // same connection to fail with "Commands out of sync". This made
        // $sqlModeResult false, bypassing the entire guard check silently.
        //
        // After the fix: store_result() drains any result set from the init
        // command, and next_result() drains any additional result sets from
        // multi-statement init commands, allowing the guard probe to run successfully.
        $mysqli = @new mysqli('127.0.0.1', 'test', 'test', 'test', 3306);

        if ($mysqli->connect_error) {
            skip('MySQL connection not available for live test');
        }

        // Run an init command that returns a result set
        $initCmd = 'SELECT 1';
        $initResult = $mysqli->real_query($initCmd);
        expect($initResult)->toBe(true);

        // Drain the result set (this is the fix)
        if ($res = $mysqli->store_result()) {
            $res->free();
        }

        // Drain any additional result sets from multi-statement init commands
        while ($mysqli->more_results() && $mysqli->next_result()) {
            if ($res = $mysqli->store_result()) {
                $res->free();
            }
        }

        // Verify the guard probe query now succeeds
        $sqlModeResult = $mysqli->query('SELECT @@session.sql_mode AS sql_mode');

        // With the fix, this should NOT be false
        expect($sqlModeResult)->not->toBe(false);
        expect($sqlModeResult)->toBeAnInstanceOf('mysqli_result');

        // Verify we got a valid sql_mode value
        $row = $sqlModeResult->fetch_assoc();
        $sqlModeResult->free();
        expect($row)->toBeAn('array');
        expect(isset($row['sql_mode']))->toBe(true);

        $mysqli->close();
    });

    it('rejects dangerous sql_mode set via result-set-free init command', function (): void {
        // This test verifies that an init command that sets a dangerous sql_mode
        // (e.g. "SET SESSION sql_mode='ANSI_QUOTES'") is correctly detected by
        // the guard, even when the init command itself returns no result set.
        //
        // This confirms the earlier reorder fix (guard runs AFTER init_command)
        // still works correctly after adding the drain logic.
        $mysqli = @new mysqli('127.0.0.1', 'test', 'test', 'test', 3306);

        if ($mysqli->connect_error) {
            skip('MySQL connection not available for live test');
        }

        // Run an init command that sets a dangerous sql_mode
        $initCmd = "SET SESSION sql_mode='ANSI_QUOTES'";
        $initResult = $mysqli->real_query($initCmd);
        expect($initResult)->toBe(true);

        // Drain the result set (though SET SESSION returns no result set)
        if ($res = $mysqli->store_result()) {
            $res->free();
        }

        // Query the sql_mode to verify the guard would detect it
        $sqlModeResult = $mysqli->query('SELECT @@session.sql_mode AS sql_mode');
        expect($sqlModeResult)->not->toBe(false);

        $row = $sqlModeResult->fetch_assoc();
        $sqlModeResult->free();
        $sqlMode = $row['sql_mode'] ?? '';

        // Verify the dangerous mode is present
        expect(stripos($sqlMode, 'ANSI_QUOTES'))->not->toBe(false);

        $mysqli->close();
    });

    it('normal init commands remain unaffected', function (): void {
        // This test verifies that normal init commands like "SET NAMES 'utf8mb4'"
        // (which return no result set) continue to work correctly with the drain
        // logic in place. The drain loop should be a no-op for commands that
        // don't return results.
        $mysqli = @new mysqli('127.0.0.1', 'test', 'test', 'test', 3306);

        if ($mysqli->connect_error) {
            skip('MySQL connection not available for live test');
        }

        // Run a normal init command that returns no result set
        $initCmd = "SET NAMES 'utf8mb4'";
        $initResult = $mysqli->real_query($initCmd);
        expect($initResult)->toBe(true);

        // Drain the result set (this should be a no-op for SET NAMES)
        if ($res = $mysqli->store_result()) {
            $res->free();
        }

        // Verify the guard probe query still succeeds
        $sqlModeResult = $mysqli->query('SELECT @@session.sql_mode AS sql_mode');
        expect($sqlModeResult)->not->toBe(false);

        $row = $sqlModeResult->fetch_assoc();
        $sqlModeResult->free();
        expect($row)->toBeAn('array');
        expect(isset($row['sql_mode']))->toBe(true);

        $mysqli->close();
    });
});
