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
            // never reach it via ../ tricks. CRITICAL: plant in tempDir's PARENT,
            // NOT in tempDir itself.
            $this->secretFile = dirname($this->tempDir) . DIRECTORY_SEPARATOR . 'SECRET.txt';
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

            // Clean up the secret file outside tempDir
            if (file_exists($this->secretFile ?? '')) {
                unlink($this->secretFile);
            }
        });

        describe('::serve — access control', function (): void {
            it('returns 403 JSON when accessCheck returns false', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'safe.pdf',
                    mimeType: 'application/pdf',
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
                    mimeType: 'application/pdf',
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
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                    inline: false,
                );

                expect($resp->getHeaderLine('Content-Disposition'))->toContain('attachment');
                expect($resp->getHeaderLine('Content-Disposition'))->toContain('My Document.pdf');
            });

            it('sends the correct Content-Length and full byte-identical body for a normal small file', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'safe.pdf',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getHeaderLine('Content-Length'))->toBe((string)strlen('%PDF-1.4 fake'));
                expect((string)$resp->getBody())->toBe('%PDF-1.4 fake');
            });

            it('always sends X-Content-Type-Options: nosniff', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'safe.pdf',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getHeaderLine('X-Content-Type-Options'))->toBe('nosniff');
            });
        });

        describe('::serve — stored MIME is the source of truth, not the display filename', function (): void {
            it('forces attachment for image/svg+xml even when inline=true is requested (stored XSS mitigation)', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'logo.svg',
                    mimeType: 'image/svg+xml',
                    accessCheck: fn () => true,
                    inline: true,
                );

                expect($resp->getHeaderLine('Content-Disposition'))->toContain('attachment');
                expect($resp->getHeaderLine('Content-Type'))->toBe('image/svg+xml');
            });

            it('forces attachment for text/html even when inline=true is requested', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'page.html',
                    mimeType: 'text/html',
                    accessCheck: fn () => true,
                    inline: true,
                );

                expect($resp->getHeaderLine('Content-Disposition'))->toContain('attachment');
            });

            it('serves using the stored MIME even when the display filename extension implies something else', function (): void {
                // Display name says .svg, but the validated stored mime is a real PDF —
                // the response must reflect the stored column, not a sniff of the fake name.
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'safe.pdf',
                    displayName: 'renamed-as-fake.svg',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                    inline: true,
                );

                expect($resp->getHeaderLine('Content-Type'))->toBe('application/pdf');
                expect($resp->getHeaderLine('Content-Disposition'))->toBe('inline');
            });
        });

        describe('::serve — path traversal protection', function (): void {
            it('returns 404 for `../SECRET.txt` (cannot escape the protected dir)', function (): void {
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: '../SECRET.txt',
                    displayName: 'innocent.pdf',
                    mimeType: 'application/pdf',
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
                    mimeType: 'application/pdf',
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
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
            });

            it('returns 404 when subDir contains a traversal segment — cannot read SECRET.txt in parent', function (): void {
                // FIXED: secret file is now in tempDir's parent, so this traversal
                // would actually reach it if isSafeSubDir() fails. The test fails
                // (200 + "classified" content) when isSafeSubDir() is neutered.
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: '../',
                    storedName: 'SECRET.txt',
                    displayName: 'innocent.pdf',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
                expect((string)$resp->getBody())->not->toContain('classified');
            });

            it('returns 404 when subDir contains an embedded traversal segment (support/../..)', function (): void {
                // FIXED: traversal goes up two levels, escaping tempDir entirely.
                // The secret file is at dirname(tempDir) and is reachable if guard fails.
                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support/../..',
                    storedName: 'SECRET.txt',
                    displayName: 'safe.pdf',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
                expect((string)$resp->getBody())->not->toContain('classified');
            });

            it('returns 404 for symlink inside upload dir pointing outside (cannot escape via symlink)', function (): void {
                // Plant a symlink inside the upload dir that points to the secret file
                $symlinkPath = $this->tempDir . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'evil_link.txt';
                $secretTarget = dirname($this->tempDir) . DIRECTORY_SEPARATOR . 'SECRET.txt';

                // Note: symlinks require admin rights on Windows; this test is best-effort.
                $symlinkCreated = @symlink($secretTarget, $symlinkPath);
                skipIf(!$symlinkCreated);

                $resp = SecureFileServing::serve(
                    uploadDir: $this->tempDir,
                    subDir: 'support',
                    storedName: 'evil_link.txt',
                    displayName: 'safe.pdf',
                    mimeType: 'application/pdf',
                    accessCheck: fn () => true,
                );

                expect($resp->getStatusCode())->toBe(404);
                // Symlink resolution happens AFTER realpath() on basePath, so the containment
                // check at line 73-77 in SecureFileServing.php MUST block the symlink escape.
                expect((string)$resp->getBody())->not->toContain('classified');
            });
        });

        describe('::isInlineSafe (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->fn = new ReflectionMethod(SecureFileServing::class, 'isInlineSafe');
            });

            it('returns true for image types (excluding SVG)', function (): void {
                expect($this->fn->invoke(null, 'image/jpeg'))->toBe(true);
                expect($this->fn->invoke(null, 'image/png'))->toBe(true);
            });

            it('returns true for PDF', function (): void {
                expect($this->fn->invoke(null, 'application/pdf'))->toBe(true);
            });

            it('returns true for text/* except text/html and text/xml', function (): void {
                expect($this->fn->invoke(null, 'text/plain'))->toBe(true);
                expect($this->fn->invoke(null, 'text/csv'))->toBe(true);
            });

            it('returns false for SVG, HTML and XML — active-content types are never inline-safe', function (): void {
                expect($this->fn->invoke(null, 'image/svg+xml'))->toBe(false);
                expect($this->fn->invoke(null, 'text/html'))->toBe(false);
                expect($this->fn->invoke(null, 'text/xml'))->toBe(false);
                expect($this->fn->invoke(null, 'application/xhtml+xml'))->toBe(false);
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
