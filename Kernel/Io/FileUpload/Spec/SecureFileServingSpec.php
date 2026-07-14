<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use const DIRECTORY_SEPARATOR;

    use function file_put_contents;
    use function is_dir;
    use function mkdir;

    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\SecureFileServing;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use ReflectionMethod;

    use function rmdir;
    use function sys_get_temp_dir;
    use function uniqid;
    use function unlink;

    describe('SecureFileServing', function (): void {
        beforeEach(function (): void {
            $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'gtest_sfs_' . uniqid();
            mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'support', 0o777, true);
            // Plant a real file inside the protected dir.
            $this->safeFile = $this->tempDir . DIRECTORY_SEPARATOR
                . 'support' . DIRECTORY_SEPARATOR . 'safe.pdf';
            file_put_contents($this->safeFile, '%PDF-1.4 fake');

            // Plant a file OUTSIDE the protected dir — the access path must
            // never reach it via ../ tricks.
            $this->secretFile = $this->tempDir . DIRECTORY_SEPARATOR . 'SECRET.txt';
            file_put_contents($this->secretFile, 'classified');
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

        describe('::serve — access control', function (): void {
            it('returns 403 JSON when accessCheck returns false', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'safe.pdf',
                    accessCheck: fn () => false,
                );

                expect($resp->getStatusCode())->toBe(403);
                expect((string)$resp->getBody())->toContain('Access denied');
            });

            it('serves the file with Content-Type and inline disposition when accessCheck returns true', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'safe.pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(200);
                expect($resp->getHeaderLine('Content-Type'))->toBe('application/pdf');
                expect($resp->getHeaderLine('Content-Disposition'))->toBe('inline');
                expect((string)$resp->getBody())->toBe('%PDF-1.4 fake');
            });

            it('uses attachment disposition when inline=false is requested', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'My Document.pdf',
                    accessCheck: fn () => true,
                    inline: false,
                );

                expect($resp->getHeaderLine('Content-Disposition'))->toContain('attachment');
                expect($resp->getHeaderLine('Content-Disposition'))->toContain('My Document.pdf');
            });
        });

        describe('::serve — path traversal protection', function (): void {
            it('returns 404 for `../SECRET.txt` (cannot escape the protected dir)', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: '../SECRET.txt',
                    displayName: 'innocent.pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
                // And critically: the secret file content was never read.
                expect((string)$resp->getBody())->not->toContain('classified');
            });

            it('returns 404 for a missing file (existing protected dir but no file)', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'nonexistent.pdf',
                    displayName: 'nonexistent.pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
            });

            it('returns 404 even when accessCheck would pass — file not in tree wins', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: '../SECRET.txt',
                    displayName: 'safe.pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
            });
        });

        describe('::isInlineSafe (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(SecureFileServing::class, 'isInlineSafe');
            });

            it('returns true for image types', function (): void {
                expect($this->fn->invoke(null, 'image/jpeg'))->toBe(true);
                expect($this->fn->invoke(null, 'image/png'))->toBe(true);
                expect($this->fn->invoke(null, 'image/svg+xml'))->toBe(true);
            });

            it('returns true for PDF', function (): void {
                expect($this->fn->invoke(null, 'application/pdf'))->toBe(true);
            });

            it('returns true for text/* (e.g. text/plain, text/html)', function (): void {
                expect($this->fn->invoke(null, 'text/plain'))->toBe(true);
                expect($this->fn->invoke(null, 'text/html'))->toBe(true);
            });

            it('returns false for executable / archive / binary types', function (): void {
                expect($this->fn->invoke(null, 'application/octet-stream'))->toBe(false);
                expect($this->fn->invoke(null, 'application/zip'))->toBe(false);
                expect($this->fn->invoke(null, 'application/x-executable'))->toBe(false);
            });
        });

        describe('::sanitizeFilename (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(SecureFileServing::class, 'sanitizeFilename');
            });

            it('preserves ordinary filenames', function (): void {
                expect($this->fn->invoke(null, 'report-2026.pdf'))->toBe('report-2026.pdf');
                expect($this->fn->invoke(null, 'My Document.docx'))->toBe('My Document.docx');
            });

            it('strips characters outside the safe set', function (): void {
                $out = $this->fn->invoke(null, 'evil"name<>?.pdf');
                expect($out)->not->toContain('"');
                expect($out)->not->toContain('<');
                expect($out)->not->toContain('>');
                expect($out)->not->toContain('?');
            });

            it('preserves Unicode letters (Japanese, accented)', function (): void {
                $out = $this->fn->invoke(null, 'ドキュメント.pdf');
                expect($out)->toContain('ドキュメント.pdf');

                $out2 = $this->fn->invoke(null, 'résumé.pdf');
                expect($out2)->toContain('résumé');
            });

            it('falls back to "download" when sanitisation yields an empty string', function (): void {
                // A string composed only of disallowed chars sanitises to underscores
                // (replace_all rule), which is non-empty — so the fallback only triggers
                // when the input is genuinely empty.
                expect($this->fn->invoke(null, ''))->toBe('download');
            });
        });
    });
}
