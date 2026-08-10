<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec {
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;

    // Regression coverage for the async-callback-leak bug: an async query
    // that fails left $this->callBack attached to the pooled DbMySQLiLink
    // (only $this->sql was cleared in the catch block of poll()). The link
    // then returned to the pool, and if the NEXT caller reused it without
    // passing a callback of its own, queryAsync()'s old "if ($callBack) {...}"
    // conditional assignment meant the stale callback from the FAILED query
    // stayed attached — so it fired with the SECOND, unrelated query's
    // result data. Fixed by (1) clearing $this->callBack in the poll()
    // error catch block, and (2) making queryAsync()'s callback assignment
    // unconditional so a callback-less caller always clears any leftover.
    describe('DbPool async callback isolation across a pooled link', function (): void {
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

            // Force a single-link pool so the second queryAsync() call is
            // guaranteed to reuse the exact same DbMySQLiLink instance that
            // the first (failing) call used.
            $reflection = new ReflectionClass(DbPool::class);
            $prop = $reflection->getProperty('instance');
            $prop->setValue(null, null);

            $pool = DbPool::get();
            $link = $pool->newLink();

            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_cb_leak (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tag VARCHAR(64) NOT NULL UNIQUE
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $link->query($sql, []);
            $link->query("DELETE FROM dbtest_test_cb_leak WHERE tag LIKE 'test_%'", []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();
            $link = $pool->newLink();
            $link->query("DELETE FROM dbtest_test_cb_leak WHERE tag LIKE 'test_%'", []);
        });

        it('never invokes query A\'s callback with query B\'s result after A fails on the same pooled link', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();

            expect($pool->getLinksCount())->toBe(1);

            $callbackAInvocations = [];

            // Query A: deliberately broken SQL, with a callback attached.
            // If the bug is present, this callback stays attached to the
            // link after the error.
            $pool->queryAsync(
                'INSERT INTO dbtest_this_table_does_not_exist_at_all (tag) VALUES (?)',
                ['test_a'],
                function ($result) use (&$callbackAInvocations): void {
                    $callbackAInvocations[] = $result;
                }
            );

            $threw = false;

            try {
                $pool->pollFinishAll();
            } catch (DbException $e) {
                $threw = true;
            }

            expect($threw)->toBe(true);
            expect($pool->getLinksCount())->toBe(1);

            // Query B: valid SQL, on the SAME (now-freed) pooled link, with
            // NO callback of its own.
            $tag = 'test_b_' . bin2hex(random_bytes(6));
            $pool->queryAsync(
                'INSERT INTO dbtest_test_cb_leak (tag) VALUES (?)',
                [$tag]
            );
            $pool->pollFinishAll();

            // Query A's callback must never have fired — neither with A's
            // own failure nor with B's unrelated result.
            expect($callbackAInvocations)->toBe([]);

            // Sanity: B's insert actually happened.
            $link = $pool->newLink();
            $rows = $link->query('SELECT tag FROM dbtest_test_cb_leak WHERE tag = ?', [$tag]);
            expect($rows)->toBeAn('array');
            expect(count($rows))->toBe(1);
        });

        it('invokes query B\'s own callback with B\'s own result when B supplies one after A fails', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $pool = DbPool::get();

            // Query A: fails, no callback.
            $pool->queryAsync('INSERT INTO dbtest_this_table_does_not_exist_at_all (tag) VALUES (?)', ['test_a']);

            $threw = false;

            try {
                $pool->pollFinishAll();
            } catch (DbException $e) {
                $threw = true;
            }

            expect($threw)->toBe(true);

            // Query B: valid SQL, WITH its own callback, on the same reused link.
            $tag = 'test_b_own_cb_' . bin2hex(random_bytes(6));
            $callbackBResult = null;

            $pool->queryAsync(
                'INSERT INTO dbtest_test_cb_leak (tag) VALUES (?)',
                [$tag],
                function ($result) use (&$callbackBResult): void {
                    $callbackBResult = $result;
                }
            );
            $pool->pollFinishAll();

            expect($callbackBResult)->not->toBeNull();
            expect($callbackBResult)->toBeGreaterThan(0);
        });
    });
}
