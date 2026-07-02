<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Tools\FsTools;

describe('FsTools', function (): void {
    describe('path building', function (): void {
        it('builds file paths from array components', function (): void {
            $result = FsTools::makeFilePath(['path', 'to', 'file']);
            expect($result)->toContain(DIRECTORY_SEPARATOR);

            expect(FsTools::makeFilePath([]))->toBe('');
            expect(FsTools::makeFilePath(['single']))->toBe('single');
        });

        it('normalizes and trims path separators', function (): void {
            $result = FsTools::makeFilePath(['path/to', 'file\\name']);
            expect($result)->not->toContain('/');
            expect($result)->toContain(DIRECTORY_SEPARATOR);

            $result = FsTools::makeFilePath(['path/', 'to\\', 'file']);
            expect($result)->not->toContain('path' . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        });

        it('builds directory paths with trailing separator', function (): void {
            $result = FsTools::makeDirPath(['path', 'to']);
            expect(substr($result, -1))->toBe(DIRECTORY_SEPARATOR);
            expect(FsTools::makeDirPath([]))->toBe(DIRECTORY_SEPARATOR);
        });
    });

    describe('unlinkFile()', function (): void {
        it('deletes existing file', function (): void {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_test_' . uniqid();
            mkdir($tempDir, 0o777, true);
            $file = $tempDir . DIRECTORY_SEPARATOR . 'test.txt';
            file_put_contents($file, 'content');

            expect(file_exists($file))->toBe(true);

            FsTools::unlinkFile($file);

            expect(file_exists($file))->toBe(false);

            // Cleanup
            rmdir($tempDir);
        });

        it('does not throw for non-existent file', function (): void {
            FsTools::unlinkFile('/nonexistent/file.txt');
            // Test passes if no exception is thrown
            expect(true)->toBe(true);
        });

        it('does not delete directories', function (): void {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_test_' . uniqid();
            mkdir($tempDir, 0o777, true);

            FsTools::unlinkFile($tempDir);

            expect(is_dir($tempDir))->toBe(true);

            // Cleanup
            rmdir($tempDir);
        });
    });

    describe('dumArray()', function (): void {
        it('exports simple indexed array', function (): void {
            $result = FsTools::dumArray(['a', 'b', 'c']);
            expect($result)->toBe("['a','b','c']");
        });

        it('exports associative array', function (): void {
            $result = FsTools::dumArray(['key' => 'value']);
            expect($result)->toBe("['key'=>'value']");
        });

        it('exports nested arrays', function (): void {
            $result = FsTools::dumArray(['outer' => ['inner' => 'value']]);
            expect($result)->toBe("['outer'=>['inner'=>'value']]");
        });
    });

    describe('exportArrToFile()', function (): void {
        it('creates valid PHP export string', function (): void {
            $result = FsTools::exportArrToFile(['key' => 'value']);
            expect($result)->toContain('<?php return ');
            expect($result)->toContain('[');
            expect($result)->toContain("'key'=>'value'");
            expect($result)->toContain('];');
        });
    });

    describe('copyDirectory()', function (): void {
        it('creates destination directory and copies files', function (): void {
            $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_src_' . uniqid();
            mkdir($source, 0o777, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'test.txt', 'content');

            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_dest_' . uniqid();

            FsTools::copyDirectory($source, $dest);

            expect(is_dir($dest))->toBe(true);

            $destFile = $dest . DIRECTORY_SEPARATOR . 'test.txt';
            expect(file_exists($destFile))->toBe(true);
            expect(file_get_contents($destFile))->toBe('content');

            // Cleanup
            unlink($destFile);
            rmdir($dest);
            unlink($source . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($source);
        });

        it('copies nested directories', function (): void {
            $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_src_' . uniqid();
            mkdir($source . DIRECTORY_SEPARATOR . 'subdir', 0o777, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt', 'nested');

            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_dest_' . uniqid();

            FsTools::copyDirectory($source, $dest);

            $nestedFile = $dest . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt';
            expect(file_exists($nestedFile))->toBe(true);

            // Cleanup
            unlink($nestedFile);
            rmdir($dest . DIRECTORY_SEPARATOR . 'subdir');
            rmdir($dest);
            unlink($source . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'nested.txt');
            rmdir($source . DIRECTORY_SEPARATOR . 'subdir');
            rmdir($source);
        });

        it('skips dot directories', function (): void {
            $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_src_' . uniqid();
            mkdir($source, 0o777, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'test.txt', 'content');

            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_dest_' . uniqid();

            FsTools::copyDirectory($source, $dest);

            // Verify the main file was copied
            expect(file_exists($dest . DIRECTORY_SEPARATOR . 'test.txt'))->toBe(true);

            // Cleanup
            unlink($dest . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($dest);
            unlink($source . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($source);
        });

        it('calls beforeCopy and afterCopy callbacks', function (): void {
            $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_src_' . uniqid();
            mkdir($source, 0o777, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'test.txt', 'content');

            $dest = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_dest_' . uniqid();

            $beforeCalled = false;
            $afterCalled = false;

            $beforeCopy = function ($src, $dest) use (&$beforeCalled) {
                $beforeCalled = true;

                return $dest;
            };

            $afterCopy = function ($src, $dest) use (&$afterCalled): void {
                $afterCalled = true;
            };

            FsTools::copyDirectory($source, $dest, afterCopy: $afterCopy, beforeCopy: $beforeCopy);

            expect($beforeCalled)->toBe(true);
            expect($afterCalled)->toBe(true);

            // Cleanup
            unlink($dest . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($dest);
            unlink($source . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($source);
        });

        it('respects replace parameter', function (): void {
            $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_src_' . uniqid();
            mkdir($source, 0o777, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'test.txt', 'new content');

            $destBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fstools_dest_' . uniqid();
            $dest = $destBase . DIRECTORY_SEPARATOR . 'sub';
            mkdir($dest, 0o777, true);
            file_put_contents($dest . DIRECTORY_SEPARATOR . 'test.txt', 'old content');

            FsTools::copyDirectory($source, $dest, afterCopy: null, beforeCopy: null, replace: true);

            expect(file_get_contents($dest . DIRECTORY_SEPARATOR . 'test.txt'))->toBe('new content');

            // Cleanup
            unlink($dest . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($dest);
            rmdir($destBase);
            unlink($source . DIRECTORY_SEPARATOR . 'test.txt');
            rmdir($source);
        });
    });
});
