<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\AppInit\InitTools;

describe('InitTools', function (): void {
    describe('makeMethod()', function (): void {
        it('generates method code with default name', function (): void {
            $result = InitTools::makeMethod('testMethod', 'file.txt');

            expect($result)->toContain('public static function testMethod()');
            expect($result)->toContain("return 'file.txt'");
            expect($result)->toContain('{');
            expect($result)->toContain('}');
        });

        it('generates method with underscores in name', function (): void {
            $result = InitTools::makeMethod('my_method_name', 'path/to/file.js');

            expect($result)->toContain('public static function my_method_name()');
            expect($result)->toContain("return 'path/to/file.js'");
        });

        it('generates method with path containing special characters', function (): void {
            $result = InitTools::makeMethod('method1', 'assets/image.png');

            expect($result)->toContain("return 'assets/image.png'");
        });

        it('generates correctly indented method', function (): void {
            $result = InitTools::makeMethod('methodName', 'file.css');

            expect($result)->toContain('        public static function');
            expect($result)->toContain("}\n");
        });
    });

    describe('makeMethodsFromFiles()', function (): void {
        it('processes empty file array', function (): void {
            $result = InitTools::makeMethodsFromFiles([], '/assets/', '/web/');

            expect($result)->toBe([]);
        });

        it('removes asset directory prefix from file paths', function (): void {
            $files = ['/assets/css/style.css'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/');

            expect(count($result))->toBe(1);
        });

        it('converts file paths to method names', function (): void {
            $files = ['/assets/js/app.js'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect(count($result))->toBe(1);
            expect($result[0])->toContain('public static function');
        });

        it('handles multiple files', function (): void {
            $files = [
                '/assets/css/style.css',
                '/assets/js/app.js',
                '/assets/img/logo.png',
            ];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect(count($result))->toBe(3);
        });

        it('filters out .keep files', function (): void {
            $files = [
                '/assets/css/style.css',
                '/assets/.keep',
                '/assets/js/app.js',
            ];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect(count($result))->toBe(2);
        });

        it('filters out .KEEP files (case insensitive)', function (): void {
            $files = [
                '/assets/css/style.css',
                '/assets/.KEEP',
                '/assets/js/app.js',
            ];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect(count($result))->toBe(2);
        });

        it('replaces forward slashes in paths', function (): void {
            $files = ['/assets/subdir/file.js'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect(count($result))->toBe(1);
        });

        it('sorts methods alphabetically', function (): void {
            $files = [
                '/assets/zzz.css',
                '/assets/aaa.css',
                '/assets/mid.css',
            ];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            // Check that aaa comes before zzz
            $aaaPos = -1;
            $zzzPos = -1;

            foreach ($result as $i => $methodCode) {
                if (str_contains($methodCode, 'aaa_css')) {
                    $aaaPos = $i;
                }

                if (str_contains($methodCode, 'zzz_css')) {
                    $zzzPos = $i;
                }
            }

            expect($aaaPos)->toBeLessThan($zzzPos);
        });

        it('replaces special characters with underscores in method names', function (): void {
            $files = ['/assets/my-custom-file.js'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect($result[0])->toContain('my_custom_file_js');
        });

        it('converts backslashes in paths', function (): void {
            $files = ['assets\\subdir\\file.js'];
            $result = InitTools::makeMethodsFromFiles($files, 'assets\\', '/web/assets/');

            expect(count($result))->toBe(1);
        });
    });

    describe('saveAssetsClass integration', function (): void {
        it('generates methods that include web path', function (): void {
            $files = ['/assets/js/app.js'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            expect($result[0])->toContain('/web/assets/js/app.js');
        });

        it('preserves file extensions in return value', function (): void {
            $files = ['/assets/style.css', '/assets/script.js', '/assets/image.png'];
            $result = InitTools::makeMethodsFromFiles($files, '/assets/', '/web/assets/');

            // Check that each extension exists somewhere in the result
            $allMethods = join("\n", $result);
            expect($allMethods)->toContain('.css');
            expect($allMethods)->toContain('.js');
            expect($allMethods)->toContain('.png');
        });
    });
});
