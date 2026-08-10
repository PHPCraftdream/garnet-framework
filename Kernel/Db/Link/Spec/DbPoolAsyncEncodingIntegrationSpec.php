<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec {
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    // Regression coverage for DbPool::queryAsync() building SQL via string
    // interpolation (QueryTools::buildSql() -> escapeSqlParam()) instead of
    // real bound parameters (mysqli has no async prepared-statement
    // execution). Before the fix, escapeSqlParam() stripped Unicode
    // combining marks / format characters (\p{M}, \p{Cf}) via an
    // allowed-character regex and collapsed whitespace runs, silently
    // corrupting written data. The sync path (DbMySQLiLink::query(), using
    // mysqli::prepare()/execute()) never had this bug and is exercised here
    // as a control.
    describe('DbPool async write path — encoding round-trip', function (): void {
        $dbAvailable = false;

        beforeAll(function () use (&$dbAvailable): void {
            $dbConfigPath = __DIR__ . '/../../../../TestsInit/TestConfig/db.ini';

            if (!file_exists($dbConfigPath)) {
                return;
            }

            $config = parse_ini_file($dbConfigPath);

            if (!isset($config['enabled']) || $config['enabled'] !== '1') {
                return;
            }

            IniConfig::defineDbIni($dbConfigPath);

            try {
                $pool = DbPool::get();
                $link = $pool->newLink();
                $result = $link->query('SELECT 1', []);

                if ($result) {
                    $dbAvailable = true;
                }
            } catch (Exception $e) {
                // Database not available, tests will be skipped
            }
        });

        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();

            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_encoding (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tag VARCHAR(64) NOT NULL UNIQUE,
                    payload TEXT NOT NULL
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);
            $link->query("DELETE FROM dbtest_test_encoding WHERE tag LIKE 'test_%'", []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_encoding WHERE tag LIKE 'test_%'", []);
        });

        // Hebrew niqqud + Arabic harakat + Devanagari matras/virama + Thai
        // vowel marks + a ZWJ-emoji sequence + intentional double-spaces.
        $payload = 'שָׁלוֹם  مَرْحَبًا  नमस्ते  สวัสดี  '
            . "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"
            . '  double  spaced';

        it('round-trips the payload byte-for-byte through the ASYNC write path', function () use (&$dbAvailable, $payload): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $tag = 'test_async_' . bin2hex(random_bytes(6));

            $pool->queryAsync(
                'INSERT INTO dbtest_test_encoding (tag, payload) VALUES (?, ?)',
                [$tag, $payload]
            );
            $pool->pollFinishAll();

            $link = $pool->newLink();
            $rows = $link->query('SELECT payload FROM dbtest_test_encoding WHERE tag = ?', [$tag]);

            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(1);
            expect($rows[0]['payload'])->toBe($payload);
        });

        it('round-trips the payload byte-for-byte through the SYNC write path (control)', function () use (&$dbAvailable, $payload): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $tag = 'test_sync_' . bin2hex(random_bytes(6));

            $link->query(
                'INSERT INTO dbtest_test_encoding (tag, payload) VALUES (?, ?)',
                [$tag, $payload]
            );

            $rows = $link->query('SELECT payload FROM dbtest_test_encoding WHERE tag = ?', [$tag]);

            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(1);
            expect($rows[0]['payload'])->toBe($payload);
        });

        it('round-trips a payload containing a single quote and backslash via ASYNC (injection-shape regression)', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $tag = 'test_async_inj_' . bin2hex(random_bytes(6));
            $injectionPayload = "O'Reilly \\ backslash \" quote'; DROP TABLE dbtest_test_encoding; --";

            $pool->queryAsync(
                'INSERT INTO dbtest_test_encoding (tag, payload) VALUES (?, ?)',
                [$tag, $injectionPayload]
            );
            $pool->pollFinishAll();

            $link = $pool->newLink();
            $rows = $link->query('SELECT payload FROM dbtest_test_encoding WHERE tag = ?', [$tag]);

            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(1);
            expect($rows[0]['payload'])->toBe($injectionPayload);

            // The table must still exist — proves the payload never broke out
            // of the string literal.
            $countRows = $link->query('SELECT COUNT(*) as cnt FROM dbtest_test_encoding', []);
            expect($countRows[0]['cnt'])->toBeGreaterThan(0);
        });
    });
}
