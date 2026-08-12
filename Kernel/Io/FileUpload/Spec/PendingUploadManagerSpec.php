<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use const DIRECTORY_SEPARATOR;

    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\PendingUploadManager;
    use ReflectionMethod;

    use function sys_get_temp_dir;
    use function uniqid;
    use function unlink;

    describe('PendingUploadManager', function (): void {
        $uploadDir = '';
        $sessionId = '';
        $accountId = 0;

        beforeEach(function (): void {
            // Create a temporary upload directory for testing
            $uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_pum_' . uniqid();
            mkdir($uploadDir . DIRECTORY_SEPARATOR . 'pending', 0o777, true);

            $sessionId = 'test-session-' . uniqid();
            $accountId = 42;

            // Make these available to the test context
            $this->uploadDir = $uploadDir;
            $this->sessionId = $sessionId;
            $this->accountId = $accountId;
        });

        afterEach(function (): void {
            if (!isset($this->uploadDir) || !is_dir($this->uploadDir)) {
                return;
            }

            // Clean up temporary files
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

            // Clean up any entity directories created during tests
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

            // Clean up subdirectories
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

        describe('::isSafeEntityDir (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(
                    PendingUploadManager::class,
                    'isSafeEntityDir'
                );
            });

            it('accepts empty string (uploads to baseDir)', function (): void {
                expect($this->fn->invoke(null, ''))->toBe(true);
            });

            it('accepts simple subdirectory', function (): void {
                expect($this->fn->invoke(null, 'courses'))->toBe(true);
                expect($this->fn->invoke(null, 'courses/42'))->toBe(true);
            });

            it('accepts paths with multiple segments', function (): void {
                expect($this->fn->invoke(null, 'courses/2024/spring'))->toBe(true);
                expect($this->fn->invoke(null, 'users/profile/images'))->toBe(true);
            });

            it('rejects paths containing .. (path traversal)', function (): void {
                expect($this->fn->invoke(null, '../'))->toBe(false);
                expect($this->fn->invoke(null, '..'))->toBe(false);
                expect($this->fn->invoke(null, 'courses/..'))->toBe(false);
                expect($this->fn->invoke(null, 'courses/../etc'))->toBe(false);
                expect($this->fn->invoke(null, '../../../etc'))->toBe(false);
            });

            it('rejects paths containing null bytes', function (): void {
                expect($this->fn->invoke(null, "safe\0evil"))->toBe(false);
                expect($this->fn->invoke(null, "../etc\0safe"))->toBe(false);
            });

            it('normalizes Windows path separators', function (): void {
                expect($this->fn->invoke(null, 'courses\\42'))->toBe(true);
                expect($this->fn->invoke(null, 'courses\\..'))->toBe(false);
            });
        });
    });
}
