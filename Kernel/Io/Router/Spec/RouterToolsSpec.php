<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Router\RouterTools;

describe('RouterTools', function (): void {
    describe('makeDirPath', function (): void {
        it('returns correct path with trailing slash', function (): void {
            $pathItems = ['path', 'to', 'file.ext'];
            $expectedPath = 'path' . DIRECTORY_SEPARATOR . 'to' . DIRECTORY_SEPARATOR . 'file.ext' . DIRECTORY_SEPARATOR;

            $actualPath = RouterTools::makeDirPath($pathItems);
            expect($actualPath)->toBe($expectedPath);
        });

        it('returns separator for empty input', function (): void {
            $actualPath = RouterTools::makeDirPath([]);
            expect($actualPath)->toBe(DIRECTORY_SEPARATOR);
        });

        it('handles single item', function (): void {
            $actualPath = RouterTools::makeDirPath(['single']);
            expect($actualPath)->toBe('single' . DIRECTORY_SEPARATOR);
        });

        it('normalizes path separators and removes trailing slashes from items', function (): void {
            // Forward slashes
            $actualPath = RouterTools::makeDirPath(['path/to', 'file']);
            expect($actualPath)->toContain(DIRECTORY_SEPARATOR);

            // Backslashes
            $actualPath = RouterTools::makeDirPath(['path\\to', 'file']);
            expect($actualPath)->toContain(DIRECTORY_SEPARATOR);

            // Mixed separators with trailing slashes
            $actualPath = RouterTools::makeDirPath(['path/', 'to\\', 'file']);
            expect($actualPath)->not->toContain('path' . DIRECTORY_SEPARATOR . '/');
        });

        it('converts non-string items to strings', function (): void {
            $actualPath = RouterTools::makeDirPath([123, 456]);
            expect($actualPath)->toBe('123' . DIRECTORY_SEPARATOR . '456' . DIRECTORY_SEPARATOR);
        });
    });

    describe('makeFilePath', function (): void {
        it('returns correct path without trailing slash', function (): void {
            $pathItems = ['path', 'to', 'file.ext'];
            $expectedPath = 'path' . DIRECTORY_SEPARATOR . 'to' . DIRECTORY_SEPARATOR . 'file.ext';

            $actualPath = RouterTools::makeFilePath($pathItems);
            expect($actualPath)->toBe($expectedPath);
        });

        it('returns empty string for empty input', function (): void {
            $actualPath = RouterTools::makeFilePath([]);
            expect($actualPath)->toBe('');
        });

        it('handles single item', function (): void {
            $actualPath = RouterTools::makeFilePath(['single.txt']);
            expect($actualPath)->toBe('single.txt');
        });

        it('normalizes path separators and removes trailing slashes', function (): void {
            // Forward slashes
            $actualPath = RouterTools::makeFilePath(['path/to', 'file']);
            expect($actualPath)->toContain(DIRECTORY_SEPARATOR);

            // Backslashes
            $actualPath = RouterTools::makeFilePath(['path\\to', 'file']);
            expect($actualPath)->toContain(DIRECTORY_SEPARATOR);

            // Trailing slash in input
            $actualPath = RouterTools::makeFilePath(['path', 'to', 'file.ext/']);
            expect($actualPath)->toBe('path' . DIRECTORY_SEPARATOR . 'to' . DIRECTORY_SEPARATOR . 'file.ext');
        });

        it('converts non-string items to strings', function (): void {
            $actualPath = RouterTools::makeFilePath([123, 456]);
            expect($actualPath)->toBe('123' . DIRECTORY_SEPARATOR . '456');
        });
    });

    describe('checkUriPathFile', function (): void {
        it('rejects paths with null bytes', function (): void {
            expect(RouterTools::checkUriPathFile("path\0file"))->toBe(false);
            expect(RouterTools::checkUriPathFile("\0"))->toBe(false);
        });

        it('rejects paths with parent directory references', function (): void {
            expect(RouterTools::checkUriPathFile('../path'))->toBe(false);
            expect(RouterTools::checkUriPathFile('path/../file'))->toBe(false);
            expect(RouterTools::checkUriPathFile('http://example.com/../../path'))->toBe(false);
            expect(RouterTools::checkUriPathFile('/path?query=../'))->toBe(false);
        });

        it('rejects complex path traversal attempts', function (): void {
            expect(RouterTools::checkUriPathFile('http://example.com/..../path/to/file.php'))->toBe(false);
            expect(RouterTools::checkUriPathFile('..../path/to/file.php'))->toBe(false);
            expect(RouterTools::checkUriPathFile('..../pa!th/..//..../to/fi!le.php'))->toBe(false);
        });

        it('accepts valid URIs', function (): void {
            expect(RouterTools::checkUriPathFile('/main/?q=request'))->toBe(true);
            expect(RouterTools::checkUriPathFile('hello/world/~method/a/b/c/'))->toBe(true);
            expect(RouterTools::checkUriPathFile('/'))->toBe(true);
            expect(RouterTools::checkUriPathFile('path/to/file'))->toBe(true);
            expect(RouterTools::checkUriPathFile(''))->toBe(true);
        });

        it('accepts paths with special characters and unicode', function (): void {
            expect(RouterTools::checkUriPathFile('path/to/file!@#$%.php'))->toBe(true);
            expect(RouterTools::checkUriPathFile('path/with-dashes_and_underscores'))->toBe(true);
            expect(RouterTools::checkUriPathFile('path/with.dots'))->toBe(true);
            expect(RouterTools::checkUriPathFile('path/to/中文'))->toBe(true);
        });

        it('accepts tilde method notation', function (): void {
            expect(RouterTools::checkUriPathFile('hello/world/~method'))->toBe(true);
            expect(RouterTools::checkUriPathFile('hello/world/~method/'))->toBe(true);
            expect(RouterTools::checkUriPathFile('/~method'))->toBe(true);
        });
    });
});
