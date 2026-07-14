<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use function count;

    use const DIRECTORY_SEPARATOR;

    use function file_exists;
    use function file_put_contents;
    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\FileUploadManager;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionMethod;

    use function rmdir;
    use function str_ends_with;
    use function sys_get_temp_dir;
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
    });
}
