<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\BaseTest\BaseTest;
use PHPCraftdream\Garnet\Kernel\Core\GlobalReqParams\GlobalReqParams;
use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterDevFile;
use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;

describe('DevFileRouter', function (): void {
    it('returns correct directory and file values', function (): void {
        $tests = [
            ['directory/dir/file.html', ['directory', 'dir' . DS . 'file.html']],
            ['directory/file.html', ['directory', 'file.html']],
            ['directory/', ['directory', 'index.html']],
            ['file.html', ['', 'file.html']],
            ['', ['', 'index.html']],
        ];

        foreach ($tests as $test) {
            [$param, $result] = $test;

            expect(BaseTest::invoke(RouterDevFile::class, 'getRouteDirAndFile', [$param]))->toBe($result);
        }
    });

    it('adds files directories correctly and throws exceptions on errors', function (): void {
        $router = new RouterDevFile();
        $dir = __DIR__ . DS . 'Tools';

        $router->addFilesDir('files', $dir);

        expect(BaseTest::getPropertyValue($router, 'filesDirs'))->toBe(['files' => $dir]);

        expect(function () use ($router): void {
            $router->addFilesDir('invalid name', '/path/to/files');
        })->toThrow(new RouterException('Wrong directory name pattern'));

        expect(function () use ($router): void {
            $router->addFilesDir('files', '/path/to/other/files');
        })->toThrow(new RouterException('Files dir already exists'));

        expect(function () use ($router): void {
            $router->addFilesDir('new_files', '/non/existing/directory');
        })->toThrow(new RouterException('Wrong directory'));
    });

    it('tests tryFileByDir method', function (): void {
        $router = new RouterDevFile();
        $dir = __DIR__ . DS . 'Tools';
        $file = 'test_file.txt';
        BaseTest::invoke($router, 'addFilesDir', ['files', $dir]);

        /**
         * @var array{string, string}|null $result.
         */
        $result = BaseTest::invoke($router, 'tryFileByDir', ['files', $file]);

        expect($result[0])->toBe($dir . DS . $file);
        expect($result[1])->toBe($file);

        /**
         * @var array{string, string}|null $result.
         */
        $result = BaseTest::invoke($router, 'tryFileByDir', ['files', 'dir' . DS . $file]);

        expect($result[0])->toBe($dir . DS . 'dir' . DS . $file);
        expect($result[1])->toBe($file);
    });

    it('tests tryFile method', function (): void {
        $router = new RouterDevFile();
        $dir = __DIR__ . DS . 'Tools';

        $router->addFilesDir('files', $dir);

        $file = 'test_file.txt';

        expect(BaseTest::invoke($router, 'tryFile', [RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests($file))]))
            ->toBeNull();
        expect([$dir . DS . $file, $file])
            ->toBe(BaseTest::invoke($router, 'tryFile', [RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('files/' . $file))]));

        expect([$dir . DS . 'dir' . DS . $file, $file])
            ->toBe(BaseTest::invoke($router, 'tryFile', [RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('files/dir/' . $file))]));

        $router->addFilesDir('', $dir);

        expect([$dir . DS . $file, $file])
            ->toBe(BaseTest::invoke($router, 'tryFile', [RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests($file))]));

        expect([$dir . DS . 'dir' . DS . $file, $file])
            ->toBe(BaseTest::invoke($router, 'tryFile', [RouterUriParams::fromGlobals(GlobalReqParams::makeGet4Tests('dir/' . $file))]));
    });
});
