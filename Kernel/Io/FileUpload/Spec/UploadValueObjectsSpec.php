<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload\Spec {
    use Error;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\PendingUpload;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadedFileInfo;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadResult;

    describe('UploadedFileInfo', function (): void {
        it('captures storedName / originalName / mimeType / size / subDir', function (): void {
            $info = new UploadedFileInfo(
                storedName: 'abc.jpg',
                originalName: 'My Pic.jpg',
                mimeType: 'image/jpeg',
                size: 12345,
                subDir: 'support',
            );

            expect($info->storedName)->toBe('abc.jpg');
            expect($info->originalName)->toBe('My Pic.jpg');
            expect($info->mimeType)->toBe('image/jpeg');
            expect($info->size)->toBe(12345);
            expect($info->subDir)->toBe('support');
        });

        it('fields are readonly', function (): void {
            $info = new UploadedFileInfo('x', 'y', 'z', 1, 's');
            $ex = null;

            try {
                // @phpstan-ignore-next-line — intentional write to readonly
                $info->storedName = 'other';
            } catch (Error $e) {
                $ex = $e;
            }
            expect($ex)->toBeAnInstanceOf(Error::class);
        });
    });

    describe('UploadResult', function (): void {
        it('returns hasErrors=false / hasFiles=false on ::empty()', function (): void {
            $r = UploadResult::empty();
            expect($r->files)->toBe([]);
            expect($r->errors)->toBe([]);
            expect($r->hasErrors)->toBe(false);
            expect($r->hasFiles)->toBe(false);
        });

        it('returns hasErrors=true and a single error message on ::error()', function (): void {
            $r = UploadResult::error('Too big');
            expect($r->files)->toBe([]);
            expect($r->errors)->toBe(['Too big']);
            expect($r->hasErrors)->toBe(true);
            expect($r->hasFiles)->toBe(false);
        });

        it('reports hasFiles=true when files are present', function (): void {
            $info = new UploadedFileInfo('a', 'b', 'c', 1, 'd');
            $r = new UploadResult(files: [$info], errors: []);
            expect($r->files)->toBe([$info]);
            expect($r->hasFiles)->toBe(true);
            expect($r->hasErrors)->toBe(false);
        });

        it('supports a mixed partial-success outcome', function (): void {
            $info = new UploadedFileInfo('a', 'b', 'c', 1, 'd');
            $r = new UploadResult(files: [$info], errors: ['File #2: too large']);
            expect($r->hasFiles)->toBe(true);
            expect($r->hasErrors)->toBe(true);
        });
    });

    describe('PendingUpload', function (): void {
        it('captures every constructor field', function (): void {
            $p = new PendingUpload(
                id: 42,
                sessionId: 'sess-abc',
                accountId: 7,
                storedName: 'token.bin',
                originalName: 'doc.pdf',
                mimeType: 'application/pdf',
                size: 9001,
                createdAt: 1_700_000_000,
            );

            expect($p->id)->toBe(42);
            expect($p->sessionId)->toBe('sess-abc');
            expect($p->accountId)->toBe(7);
            expect($p->storedName)->toBe('token.bin');
            expect($p->originalName)->toBe('doc.pdf');
            expect($p->mimeType)->toBe('application/pdf');
            expect($p->size)->toBe(9001);
            expect($p->createdAt)->toBe(1_700_000_000);
        });
    });
}
