<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use function count;

    use const DIRECTORY_SEPARATOR;

    use function file_exists;
    use function file_put_contents;
    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\FileUploadManager;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadRules;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionMethod;

    use function rmdir;
    use function str_ends_with;
    use function sys_get_temp_dir;
    use function tempnam;
    use function uniqid;
    use function unlink;

    describe('FileUploadManager', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_fum_' . uniqid();
            mkdir($this->tempDir, 0o777, true);
        });

        afterEach(function (): void {
            if (is_dir($this->tempDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($it as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($this->tempDir);
            }
        });

        describe('constructor', function (): void {
            it('creates the upload dir if missing', function (): void {
                $sub = $this->tempDir . DIRECTORY_SEPARATOR . 'fresh';
                expect(is_dir($sub))->toBe(false);

                new FileUploadManager($sub);
                expect(is_dir($sub))->toBe(true);
            });

            it('appends a normalised subDir when provided', function (): void {
                $m = new FileUploadManager($this->tempDir, 'support');

                // Subdir path is exposed via getPath() — should end with support/
                $path = $m->getPath('file.txt');
                expect($path)->toContain(DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR);
            });

            it('strips any slashes from subDir before appending', function (): void {
                $m = new FileUploadManager($this->tempDir, '/support/');
                $path = $m->getPath('file.txt');
                expect($path)->toContain(DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR);
                expect($path)->not->toContain('support//');
            });
        });

        describe('::delete + ::exists + ::getPath — path traversal protection', function (): void {
            beforeEach(function (): void {
                $this->m = new FileUploadManager($this->tempDir, 'sub');
                // Plant a "real" file so we can test exists() and delete()
                file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'real.txt', 'hello');
            });

            it('exists() returns true for an existing file', function (): void {
                expect($this->m->exists('real.txt'))->toBe(true);
            });

            it('exists() returns false for a missing file', function (): void {
                expect($this->m->exists('nope.txt'))->toBe(false);
            });

            it('delete() removes an existing file and returns true', function (): void {
                expect($this->m->exists('real.txt'))->toBe(true);
                expect($this->m->delete('real.txt'))->toBe(true);
                expect($this->m->exists('real.txt'))->toBe(false);
            });

            it('delete() returns false for a missing file (no throw)', function (): void {
                expect($this->m->delete('nope.txt'))->toBe(false);
            });

            it('basenames the input — `../escape.txt` cannot reach the parent dir', function (): void {
                // Plant a file in the PARENT of the upload dir that an attacker
                // might try to delete via ../
                $outside = $this->tempDir . DIRECTORY_SEPARATOR . 'outside.txt';
                file_put_contents($outside, 'untouchable');

                $this->m->delete('../outside.txt');
                expect(file_exists($outside))->toBe(true);  // still there
            });

            it('basenames the input — getPath collapses ../ segments', function (): void {
                $p = $this->m->getPath('../../etc/passwd');
                // The returned path should end in /passwd (basenamed), never with ../
                expect($p)->not->toContain('..');
                expect(str_ends_with($p, 'passwd'))->toBe(true);
            });

            it('basenames the input — exists() rejects ../ paths', function (): void {
                $outside = $this->tempDir . DIRECTORY_SEPARATOR . 'witness.txt';
                file_put_contents($outside, 'w');
                // exists('../witness.txt') would land inside the manager's dir as
                // witness.txt — which doesn't exist. The protection here is that
                // it can never reach the outside file.
                expect($this->m->exists('../witness.txt'))->toBe(false);
            });
        });

        describe('::normalizeFiles (private — via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(FileUploadManager::class, 'normalizeFiles');
            });

            it('returns empty array when name key is missing', function (): void {
                expect($this->fn->invoke(null, []))->toBe([]);
            });

            it('wraps a single-file entry (name is string) into a one-element array', function (): void {
                $single = [
                    'name' => 'a.txt', 'type' => 'text/plain',
                    'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10,
                ];
                $out = $this->fn->invoke(null, $single);
                expect(count($out))->toBe(1);
                expect($out[0]['name'])->toBe('a.txt');
            });

            it('returns empty when single-file has empty tmp_name', function (): void {
                $single = ['name' => 'a.txt', 'tmp_name' => ''];
                expect($this->fn->invoke(null, $single))->toBe([]);
            });

            it('flattens a multi-file entry (name is array) into a list of file entries', function (): void {
                $multi = [
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'error' => [0, 0],
                    'size' => [10, 20],
                ];
                $out = $this->fn->invoke(null, $multi);
                expect(count($out))->toBe(2);
                expect($out[0])->toBe([
                    'name' => 'a.txt', 'type' => 'text/plain',
                    'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 10,
                ]);
                expect($out[1]['name'])->toBe('b.txt');
            });

            it('skips multi-file entries with empty tmp_name', function (): void {
                $multi = [
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'tmp_name' => ['/tmp/a', ''],
                    'error' => [0, 0],
                    'size' => [10, 0],
                ];
                $out = $this->fn->invoke(null, $multi);
                expect(count($out))->toBe(1);
                expect($out[0]['name'])->toBe('a.txt');
            });

            it('defaults missing per-file keys to safe zeros', function (): void {
                $multi = [
                    'name' => ['a.txt'],
                    'tmp_name' => ['/tmp/a'],
                    // type/error/size missing entirely
                ];
                $out = $this->fn->invoke(null, $multi);
                expect($out[0]['type'])->toBe('');
                expect($out[0]['error'])->toBe(0);
                expect($out[0]['size'])->toBe(0);
            });
        });

        describe('::validateFile (protected — via reflection) — the is_uploaded_file gate', function (): void {
            // These 3 cases don't need is_uploaded_file() to return true, so
            // they exercise the real validateFile() gate directly — no mock
            // needed, since a tempnam() file genuinely isn't an HTTP upload
            // and is_uploaded_file() naturally (and correctly) returns false.
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(FileUploadManager::class, 'validateFile');
                $this->tempFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($this->tempFile, 'test content');
            });

            afterEach(function (): void {
                if (file_exists($this->tempFile ?? '')) {
                    unlink($this->tempFile);
                }
            });

            it('rejects file when is_uploaded_file returns false (real, unmocked check)', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'test.txt',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ];

                // No mock: a tempnam() file is never a real HTTP upload, so
                // is_uploaded_file() genuinely returns false here.
                $error = $this->fn->invoke(new FileUploadManager($this->tempDir), $file, $rules);
                expect($error)->toContain('Invalid upload');
            });

            it('rejects file with empty tmp_name', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'test.txt',
                    'tmp_name' => '',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ];

                $error = $this->fn->invoke(new FileUploadManager($this->tempDir), $file, $rules);
                expect($error)->toContain('Invalid upload');
            });

            it('rejects file with UPLOAD_ERR_* error code', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'test.txt',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_INI_SIZE,  // exceeds php.ini upload_max_filesize
                    'size' => 12,
                ];

                $error = $this->fn->invoke(new FileUploadManager($this->tempDir), $file, $rules);
                expect($error)->toContain('Upload error');
            });
        });

        describe('::validateFileRules (protected static — via reflection) — size/extension/MIME checks', function (): void {
            // is_uploaded_file() can only ever return true for a file the SAPI
            // actually received via POST — untestable in a CLI harness, and
            // Kahlan's monkey-patching of that specific builtin does not take
            // effect in this environment (verified: mocking it and asserting
            // toBe(true) still yields the real, unmocked 'Invalid upload'
            // result). validateFileRules() is the size/extension/MIME logic
            // extracted out of validateFile() specifically so it's testable
            // without needing to fake that gate.
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(FileUploadManager::class, 'validateFileRules');
                $this->tempFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($this->tempFile, 'test content');
            });

            afterEach(function (): void {
                if (file_exists($this->tempFile ?? '')) {
                    unlink($this->tempFile);
                }
            });

            it('accepts a valid file with allowed extension and MIME type', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'test.txt',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toBe(null);
            });

            it('rejects file with disallowed extension', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt', 'pdf'],
                );

                $file = [
                    'name' => 'evil.php',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toContain('File type not allowed');
                expect($error)->toContain('.php');
            });

            it('rejects file exceeding maxFileSize', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 10,  // 10 bytes
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'large.txt',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 1000,  // 1000 bytes
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toContain('File too large');
            });

            it('rejects file with disallowed MIME type (detected by finfo)', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['application/pdf'],
                    allowedExtensions: ['txt', 'pdf'],
                );

                // Create a file that's actually text/plain but claims .pdf
                $textFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($textFile, 'plain text content');

                $file = [
                    'name' => 'fake.pdf',
                    'tmp_name' => $textFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 18,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toContain('MIME type not allowed');
                expect($error)->toContain('text/plain');

                unlink($textFile);
            });

            it('accepts file when MIME prefix matches (text/* for text/plain)', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/'],  // prefix match
                    allowedExtensions: ['txt'],
                );

                $file = [
                    'name' => 'test.txt',
                    'tmp_name' => $this->tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 12,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toBe(null);
            });

            it('rejects .php extension even when content is plain text', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt', 'log'],  // no php
                );

                // PHP content but extension .php - should be blocked
                $phpFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($phpFile, '<?php echo "evil"; ?>');

                $file = [
                    'name' => 'exploit.php',
                    'tmp_name' => $phpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 22,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toContain('File type not allowed');
                expect($error)->toContain('.php');

                unlink($phpFile);
            });

            it('rejects .svg extension even when content is plain text', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt', 'pdf'],  // no svg
                );

                // SVG content but extension .svg - should be blocked
                $svgFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($svgFile, '<svg><script>alert(1)</script></svg>');

                $file = [
                    'name' => 'xss.svg',
                    'tmp_name' => $svgFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 39,
                ];

                $error = $this->fn->invoke(null, $file, $rules);
                expect($error)->toContain('File type not allowed');
                expect($error)->toContain('.svg');

                unlink($svgFile);
            });
        });

        describe('::storeAll — file count cap', function (): void {
            it('rejects when number of files exceeds maxFilesCount', function (): void {
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 2,  // only 2 files allowed
                    allowedTypes: ['text/plain'],
                    allowedExtensions: ['txt'],
                );

                // Simulate 3 files
                $filesArray = [
                    'name' => ['a.txt', 'b.txt', 'c.txt'],
                    'type' => ['text/plain', 'text/plain', 'text/plain'],
                    'tmp_name' => ['/tmp/a', '/tmp/b', '/tmp/c'],
                    'error' => [0, 0, 0],
                    'size' => [10, 10, 10],
                ];

                $manager = new FileUploadManager($this->tempDir);
                $result = $manager->storeAll($filesArray, $rules);

                expect($result->errors)->not->toBeEmpty();
                expect($result->errors[0])->toContain('Too many files');
                expect($result->errors[0])->toContain('max 2');
            });
        });

        describe('::validateFileRules — extension preservation validation passes', function (): void {
            it('accepts a valid PDF that would be stored with a preserved extension', function (): void {
                // Create a real temp file that's actually a PDF
                $pdfFile = tempnam(sys_get_temp_dir(), 'gtest_upload_');
                file_put_contents($pdfFile, '%PDF-1.4 fake pdf content');

                $file = [
                    'name' => 'document.pdf',
                    'tmp_name' => $pdfFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 24,
                ];

                $fnValidate = new ReflectionMethod(FileUploadManager::class, 'validateFileRules');
                $rules = new UploadRules(
                    maxFileSize: 1024 * 1024,
                    maxFilesCount: 5,
                    allowedTypes: ['application/pdf'],
                    allowedExtensions: ['pdf'],
                );

                $error = $fnValidate->invoke(null, $file, $rules);
                expect($error)->toBe(null);  // validation passed

                unlink($pdfFile);
            });
        });

        describe('::buildStoredName (protected static — via reflection) — extension preservation', function (): void {
            // storeSingle() itself needs move_uploaded_file() to succeed, which
            // requires a real HTTP upload — untestable in a CLI harness (see
            // the ::validateFileRules describe block above for the same
            // constraint on is_uploaded_file()). buildStoredName() is the
            // extension-preservation logic extracted out of storeSingle()
            // specifically so it's testable without needing a real upload.
            it('preserves the client-supplied extension in the stored filename', function (): void {
                $fn = new ReflectionMethod(FileUploadManager::class, 'buildStoredName');
                $storedName = $fn->invoke(null, 'document.pdf');

                expect(str_ends_with($storedName, '.pdf'))->toBe(true);
            });

            it('produces no extension when the original name has none', function (): void {
                $fn = new ReflectionMethod(FileUploadManager::class, 'buildStoredName');
                $storedName = $fn->invoke(null, 'no-extension-file');

                expect($storedName)->not->toContain('.');
            });

            it('lowercases the preserved extension', function (): void {
                $fn = new ReflectionMethod(FileUploadManager::class, 'buildStoredName');
                $storedName = $fn->invoke(null, 'IMAGE.PNG');

                expect(str_ends_with($storedName, '.png'))->toBe(true);
            });
        });
    });
}
