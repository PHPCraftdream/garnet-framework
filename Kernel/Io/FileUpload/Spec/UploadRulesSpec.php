<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use Error;

    use function in_array;

    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadRules;

    use function str_starts_with;

    describe('UploadRules', function (): void {
        describe('constructor', function (): void {
            it('uses sane defaults (5 MB, 5 files, common image+pdf+txt types)', function (): void {
                $r = new UploadRules();
                expect($r->maxFileSize)->toBe(5 * 1024 * 1024);
                expect($r->maxFilesCount)->toBe(5);
                expect($r->allowedTypes)->toContain('image/jpeg');
                expect($r->allowedTypes)->toContain('application/pdf');
                expect($r->allowedExtensions)->toContain('jpg');
                expect($r->allowedExtensions)->toContain('pdf');
            });

            it('accepts custom max size + count + lists', function (): void {
                $r = new UploadRules(
                    maxFileSize: 10 * 1024 * 1024,
                    maxFilesCount: 3,
                    allowedTypes: ['text/csv'],
                    allowedExtensions: ['csv'],
                );
                expect($r->maxFileSize)->toBe(10 * 1024 * 1024);
                expect($r->maxFilesCount)->toBe(3);
                expect($r->allowedTypes)->toBe(['text/csv']);
                expect($r->allowedExtensions)->toBe(['csv']);
            });

            it('properties are readonly', function (): void {
                $r = new UploadRules();
                $ex = null;

                try {
                    // @phpstan-ignore-next-line — intentional write to readonly
                    $r->maxFileSize = 1;
                } catch (Error $e) {
                    $ex = $e;
                }
                expect($ex)->toBeAnInstanceOf(Error::class);
            });
        });

        describe('::imagesOnly', function (): void {
            it('restricts mime + extension lists to image-only entries', function (): void {
                $r = UploadRules::imagesOnly();

                foreach ($r->allowedTypes as $t) {
                    expect(str_starts_with($t, 'image/'))->toBe(true);
                }

                foreach ($r->allowedExtensions as $ext) {
                    expect(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))->toBe(true);
                }
            });

            it('raises maxFilesCount to 10', function (): void {
                expect(UploadRules::imagesOnly()->maxFilesCount)->toBe(10);
            });

            it('honours a custom max size', function (): void {
                expect(UploadRules::imagesOnly(2 * 1024 * 1024)->maxFileSize)->toBe(2 * 1024 * 1024);
            });
        });

        describe('::documentsAndImages', function (): void {
            it('includes the default doc+image type set', function (): void {
                $r = UploadRules::documentsAndImages();
                expect($r->allowedTypes)->toContain('application/pdf');
                expect($r->allowedTypes)->toContain('image/jpeg');
            });
        });

        describe('::lessonMaterials', function (): void {
            it('caps at one file (one lesson upload at a time)', function (): void {
                expect(UploadRules::lessonMaterials()->maxFilesCount)->toBe(1);
            });

            it('defaults maxFileSize to 20 MB', function (): void {
                expect(UploadRules::lessonMaterials()->maxFileSize)->toBe(20 * 1024 * 1024);
            });

            it('covers Office, ODF, PDF, EPUB and image types', function (): void {
                $types = UploadRules::lessonMaterials()->allowedTypes;
                expect($types)->toContain('application/pdf');
                expect($types)->toContain('application/epub+zip');
                expect($types)->toContain('application/msword');
                expect($types)->toContain('application/vnd.oasis.opendocument.text');
                expect($types)->toContain('image/jpeg');
            });
        });
    });
}
