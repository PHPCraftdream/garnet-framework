<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Router\Mime;

describe('Mime', function (): void {
    describe('getFileMime()', function (): void {
        it('returns mime types for common file extensions', function (): void {
            expect(Mime::getFileMime('test.jpg'))->toBe('image/jpeg');
            expect(Mime::getFileMime('file.png'))->toBe('image/png');
            expect(Mime::getFileMime('style.css'))->toBe('text/css');
            expect(Mime::getFileMime('script.js'))->toBe('text/javascript');
            expect(Mime::getFileMime('data.json'))->toBe('application/json');
            expect(Mime::getFileMime('page.html'))->toBe('text/html');
            expect(Mime::getFileMime('doc.pdf'))->toBe('application/pdf');
        });

        it('returns null for unknown extensions', function (): void {
            expect(Mime::getFileMime('test.unknown'))->toBe(null);
        });

        it('extracts extension from filename with multiple dots', function (): void {
            expect(Mime::getFileMime('file.name.with.jpg'))->toBe('image/jpeg');
        });

        it('returns null for files without extension', function (): void {
            expect(Mime::getFileMime('filename'))->toBe(null);
            expect(Mime::getFileMime('filename.'))->toBe(null);
        });

        it('is case sensitive for extensions', function (): void {
            expect(Mime::getFileMime('test.JPG'))->toBe(null);
            expect(Mime::getFileMime('test.jpg'))->toBe('image/jpeg');
        });

        it('handles audio file types', function (): void {
            expect(Mime::getFileMime('audio.mp3'))->toBe('audio/mpeg');
            expect(Mime::getFileMime('audio.wav'))->toBe('audio/wav');
            expect(Mime::getFileMime('audio.oga'))->toBe('audio/ogg');
            expect(Mime::getFileMime('audio.aac'))->toBe('audio/aac');
            expect(Mime::getFileMime('audio.mid'))->toContain('audio/midi');
        });

        it('handles video file types', function (): void {
            expect(Mime::getFileMime('video.mp4'))->toBe('video/mp4');
            expect(Mime::getFileMime('video.webm'))->toBe('video/webm');
            expect(Mime::getFileMime('video.avi'))->toBe('video/x-msvideo');
            expect(Mime::getFileMime('video.mpeg'))->toBe('video/mpeg');
            expect(Mime::getFileMime('video.3gp'))->toBe('video/3gpp');
            expect(Mime::getFileMime('video.3g2'))->toBe('video/3gpp2');
        });

        it('handles archive file types', function (): void {
            expect(Mime::getFileMime('archive.zip'))->toBe('application/zip');
            expect(Mime::getFileMime('archive.tar'))->toBe('application/x-tar');
            expect(Mime::getFileMime('archive.gz'))->toBe('application/gzip');
            expect(Mime::getFileMime('archive.rar'))->toBe('application/vnd.rar');
            expect(Mime::getFileMime('archive.7z'))->toBe('application/x-7z-compressed');
        });

        it('handles document file types', function (): void {
            expect(Mime::getFileMime('doc.doc'))->toBe('application/msword');
            expect(Mime::getFileMime('doc.docx'))->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            expect(Mime::getFileMime('sheet.xls'))->toBe('application/vnd.ms-excel');
            expect(Mime::getFileMime('sheet.xlsx'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            expect(Mime::getFileMime('pres.ppt'))->toBe('application/vnd.ms-powerpoint');
            expect(Mime::getFileMime('doc.odt'))->toBe('application/vnd.oasis.opendocument.text');
            expect(Mime::getFileMime('doc.ods'))->toBe('application/vnd.oasis.opendocument.spreadsheet');
            expect(Mime::getFileMime('doc.odp'))->toBe('application/vnd.oasis.opendocument.presentation');
        });

        it('handles font file types', function (): void {
            expect(Mime::getFileMime('font.ttf'))->toBe('font/ttf');
            expect(Mime::getFileMime('font.otf'))->toBe('font/otf');
            expect(Mime::getFileMime('font.woff'))->toBe('font/woff');
            expect(Mime::getFileMime('font.woff2'))->toBe('font/woff2');
        });

        it('handles image file types', function (): void {
            expect(Mime::getFileMime('img.gif'))->toBe('image/gif');
            expect(Mime::getFileMime('img.svg'))->toBe('image/svg+xml');
            expect(Mime::getFileMime('img.bmp'))->toBe('image/bmp');
            expect(Mime::getFileMime('img.webp'))->toBe('image/webp');
            expect(Mime::getFileMime('img.ico'))->toBe('image/vnd.microsoft.icon');
            expect(Mime::getFileMime('img.tif'))->toBe('image/tiff');
        });

        it('handles text-based file types', function (): void {
            expect(Mime::getFileMime('file.txt'))->toBe('text/plain');
            expect(Mime::getFileMime('file.xml'))->toBe('text/xml');
            expect(Mime::getFileMime('file.csv'))->toBe('text/csv');
            expect(Mime::getFileMime('file.htm'))->toBe('text/html');
            expect(Mime::getFileMime('file.ics'))->toBe('text/calendar');
        });

        it('handles application file types', function (): void {
            expect(Mime::getFileMime('file.php'))->toBe('application/x-httpd-php');
            expect(Mime::getFileMime('file.bin'))->toBe('application/octet-stream');
            expect(Mime::getFileMime('file.jsonld'))->toBe('application/ld+json');
            expect(Mime::getFileMime('file.jar'))->toBe('application/java-archive');
            expect(Mime::getFileMime('file.swf'))->toBe('application/x-shockwave-flash');
            expect(Mime::getFileMime('file.rtf'))->toBe('application/rtf');
            expect(Mime::getFileMime('file.epub'))->toBe('application/epub+zip');
        });
    });

    describe('getExtMime()', function (): void {
        it('returns mime types for common extensions', function (): void {
            expect(Mime::getExtMime('jpg'))->toBe('image/jpeg');
            expect(Mime::getExtMime('jpeg'))->toBe('image/jpeg');
            expect(Mime::getExtMime('png'))->toBe('image/png');
            expect(Mime::getExtMime('css'))->toBe('text/css');
            expect(Mime::getExtMime('gif'))->toBe('image/gif');
            expect(Mime::getExtMime('svg'))->toBe('image/svg+xml');
            expect(Mime::getExtMime('txt'))->toBe('text/plain');
        });

        it('returns null for unknown or invalid extensions', function (): void {
            expect(Mime::getExtMime('unknown'))->toBe(null);
            expect(Mime::getExtMime(''))->toBe(null);
            expect(Mime::getExtMime('.jpg'))->toBe(null);
        });

        it('handles tiff extension variants', function (): void {
            expect(Mime::getExtMime('tif'))->toBe('image/tiff');
            expect(Mime::getExtMime('tiff'))->toBe('image/tiff');
        });

        it('handles modern media formats', function (): void {
            expect(Mime::getExtMime('webp'))->toBe('image/webp');
            expect(Mime::getExtMime('ico'))->toBe('image/vnd.microsoft.icon');
            expect(Mime::getExtMime('webm'))->toBe('video/webm');
            expect(Mime::getExtMime('ogv'))->toBe('video/ogg');
            expect(Mime::getExtMime('weba'))->toBe('audio/webm');
            expect(Mime::getExtMime('mid'))->toContain('audio/midi');
        });
    });
});
