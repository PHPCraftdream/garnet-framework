<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use function basename;

    use const DIRECTORY_SEPARATOR;

    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\PendingUploadManager;

    use function sys_get_temp_dir;

    use Throwable;

    use function uniqid;
    use function unlink;

    // Helper function to get db config path
    function getDbConfigPath(): string {
        $frameworkDir = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

        return $frameworkDir . '/TestsInit/TestConfig/db.ini';
    }

    describe('PendingUploadManager Integration', function (): void {
        $dbAvailable = false;
        $uploadDir = '';
        $sessionId = '';
        $accountId = 0;
        $pendingId = 0;
        $testFileName = '';

        afterAll(function (): void {
            DbPool::closeAll();
        });

        beforeAll(function () use (&$dbAvailable): void {
            $dbConfigPath = getDbConfigPath();

            if (!file_exists($dbConfigPath)) {
                return;
            }

            $config = parse_ini_file($dbConfigPath);

            if (!isset($config['enabled']) || $config['enabled'] !== '1') {
                return;
            }

            \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::defineDbIni($dbConfigPath);

            try {
                $pool = DbPool::get();
                $link = $pool->newLink();

                $sql = '
                    CREATE TABLE IF NOT EXISTS pending_uploads (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        session_id VARCHAR(64) NOT NULL,
                        account_id INT NOT NULL,
                        stored_name VARCHAR(255) NOT NULL,
                        original_name VARCHAR(255) NOT NULL,
                        mime_type VARCHAR(100) NOT NULL,
                        size INT NOT NULL DEFAULT 0,
                        created_at INT NOT NULL DEFAULT 0,
                        KEY idx_session_id (session_id),
                        KEY idx_account_id (account_id),
                        KEY idx_created_at (created_at)
                    ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
                ';
                $link->query($sql, []);

                $link->query("DELETE FROM pending_uploads WHERE session_id LIKE 'test_commit_%'", []);

                $dbAvailable = true;
            } catch (Throwable $e) {
                // DB not available for this test
            }
        });

        beforeEach(function () use (&$uploadDir, &$sessionId, &$accountId, &$testFileName): void {
            $uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_pum_int_' . uniqid();
            mkdir($uploadDir . DIRECTORY_SEPARATOR . 'pending', 0o777, true);

            $sessionId = 'test-commit-' . uniqid();
            $accountId = 42;
            $testFileName = 'test_file_' . uniqid() . '.txt';

            $this->uploadDir = $uploadDir;
            $this->sessionId = $sessionId;
            $this->accountId = $accountId;
            $this->testFileName = $testFileName;
        });

        afterEach(function (): void {
            if (!isset($this->uploadDir) || !is_dir($this->uploadDir)) {
                return;
            }

            $pendingDir = $this->uploadDir . DIRECTORY_SEPARATOR . 'pending';

            if (is_dir($pendingDir)) {
                $files = glob($pendingDir . DIRECTORY_SEPARATOR . '*');

                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                @rmdir($pendingDir);
            }

            $dirs = glob($this->uploadDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            foreach ($dirs as $dir) {
                if (basename($dir) !== 'pending') {
                    $files = glob($dir . DIRECTORY_SEPARATOR . '*');

                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                    @rmdir($dir);
                }
            }

            $subdirs = glob($this->uploadDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            foreach ($subdirs as $subdir) {
                if (is_dir($subdir)) {
                    $files = glob($subdir . DIRECTORY_SEPARATOR . '*');

                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                    @rmdir($subdir);
                }
            }

            @rmdir($this->uploadDir);
        });

        describe('commit() — path traversal protection (with DB)', function (): void {
            beforeEach(function () use (&$dbAvailable, &$pendingId): void {
                if (!$dbAvailable) {
                    skipIf(true);
                }

                $pool = DbPool::get();
                $link = $pool->newLink();

                $sql = "DELETE FROM pending_uploads WHERE session_id LIKE 'test_commit_%'";
                $link->query($sql, []);
            });

            it('returns null for entityDir containing .. segment (prevents traversal)', function () use (&$dbAvailable, &$pendingId): void {
                if (!$dbAvailable) {
                    skipIf(true);
                }

                $pool = DbPool::get();
                $link = $pool->newLink();

                $pendingDir = $this->uploadDir . DIRECTORY_SEPARATOR . 'pending';
                $testFilePath = $pendingDir . DIRECTORY_SEPARATOR . $this->testFileName;
                file_put_contents($testFilePath, 'test content');

                $now = time();
                $sql = 'INSERT INTO pending_uploads (session_id, account_id, stored_name, original_name, mime_type, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)';
                $link->query($sql, [
                    $this->sessionId,
                    $this->accountId,
                    $this->testFileName,
                    'original.txt',
                    'text/plain',
                    12,
                    $now,
                ]);

                $pendingId = (int)$link->insertId();

                $manager = new PendingUploadManager(
                    $this->uploadDir,
                    $this->sessionId,
                    $this->accountId
                );

                $result = $manager->commit($pendingId, '../../../etc');

                expect($result)->toBe(null);

                $outsideFile = $this->uploadDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . $this->testFileName;
                expect(file_exists($outsideFile))->toBe(false);
                expect(file_exists($testFilePath))->toBe(true);
            });

            it('returns null for entityDir with null byte injection', function () use (&$dbAvailable, &$pendingId): void {
                if (!$dbAvailable) {
                    skipIf(true);
                }

                $pool = DbPool::get();
                $link = $pool->newLink();

                $pendingDir = $this->uploadDir . DIRECTORY_SEPARATOR . 'pending';
                $testFilePath = $pendingDir . DIRECTORY_SEPARATOR . $this->testFileName;
                file_put_contents($testFilePath, 'test content');

                $now = time();
                $sql = 'INSERT INTO pending_uploads (session_id, account_id, stored_name, original_name, mime_type, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)';
                $link->query($sql, [
                    $this->sessionId,
                    $this->accountId,
                    $this->testFileName,
                    'original.txt',
                    'text/plain',
                    12,
                    $now,
                ]);

                $pendingId = (int)$link->insertId();

                $manager = new PendingUploadManager(
                    $this->uploadDir,
                    $this->sessionId,
                    $this->accountId
                );

                $result = $manager->commit($pendingId, "safe\0evil");

                expect($result)->toBe(null);
                expect(file_exists($testFilePath))->toBe(true);
            });

            it('returns null for Windows-style traversal with backslashes', function () use (&$dbAvailable, &$pendingId): void {
                if (!$dbAvailable) {
                    skipIf(true);
                }

                $pool = DbPool::get();
                $link = $pool->newLink();

                $pendingDir = $this->uploadDir . DIRECTORY_SEPARATOR . 'pending';
                $testFilePath = $pendingDir . DIRECTORY_SEPARATOR . $this->testFileName;
                file_put_contents($testFilePath, 'test content');

                $now = time();
                $sql = 'INSERT INTO pending_uploads (session_id, account_id, stored_name, original_name, mime_type, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)';
                $link->query($sql, [
                    $this->sessionId,
                    $this->accountId,
                    $this->testFileName,
                    'original.txt',
                    'text/plain',
                    12,
                    $now,
                ]);

                $pendingId = (int)$link->insertId();

                $manager = new PendingUploadManager(
                    $this->uploadDir,
                    $this->sessionId,
                    $this->accountId
                );

                $result = $manager->commit($pendingId, 'courses\\..');

                expect($result)->toBe(null);
                expect(file_exists($testFilePath))->toBe(true);
            });

            it('succeeds for valid entityDir and moves file correctly', function () use (&$dbAvailable, &$pendingId): void {
                if (!$dbAvailable) {
                    skipIf(true);
                }

                $pool = DbPool::get();
                $link = $pool->newLink();

                $pendingDir = $this->uploadDir . DIRECTORY_SEPARATOR . 'pending';
                $testFilePath = $pendingDir . DIRECTORY_SEPARATOR . $this->testFileName;
                file_put_contents($testFilePath, 'test content');

                $now = time();
                $sql = 'INSERT INTO pending_uploads (session_id, account_id, stored_name, original_name, mime_type, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)';
                $link->query($sql, [
                    $this->sessionId,
                    $this->accountId,
                    $this->testFileName,
                    'original.txt',
                    'text/plain',
                    12,
                    $now,
                ]);

                $pendingId = (int)$link->insertId();

                $manager = new PendingUploadManager(
                    $this->uploadDir,
                    $this->sessionId,
                    $this->accountId
                );

                $result = $manager->commit($pendingId, 'courses/42');

                expect($result)->not->toBe(null);
                expect($result->storedName)->toBe($this->testFileName);
                expect($result->subDir)->toBe('courses/42');

                $targetPath = $this->uploadDir . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . '42' . DIRECTORY_SEPARATOR . $this->testFileName;
                expect(file_exists($targetPath))->toBe(true);
                expect(file_get_contents($targetPath))->toBe('test content');
                expect(file_exists($testFilePath))->toBe(false);
            });
        });
    });
}
