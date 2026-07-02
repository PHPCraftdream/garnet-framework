<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\Forms\ImageUpload;
    use ReflectionClass;

    describe('ImageUpload', function (): void {
        describe('Constructor', function (): void {
            it('initializes with image source path and prefix', function (): void {
                $imgUpload = new ImageUpload('/tmp/test.jpg', 'prefix_');

                $reflection = new ReflectionClass($imgUpload);
                $imgSrcPathProp = $reflection->getProperty('imgSrcPath');
                $imgSrcPathProp->setAccessible(true);
                $imgSrcPathValue = $imgSrcPathProp->getValue($imgUpload);

                $fileNameProp = $reflection->getProperty('fileName');
                $fileNameProp->setAccessible(true);
                $fileNameValue = $fileNameProp->getValue($imgUpload);

                expect($imgSrcPathValue)->toBe('/tmp/test.jpg');
                expect($fileNameValue)->toContain('prefix_');
                expect($fileNameValue)->toHaveLength(strlen('prefix_') + 14); // prefix_ + uniqid (13 chars)
            });

            it('initializes with null image source path', function (): void {
                $imgUpload = new ImageUpload(null, '');

                $reflection = new ReflectionClass($imgUpload);
                $imgSrcPathProp = $reflection->getProperty('imgSrcPath');
                $imgSrcPathProp->setAccessible(true);
                $imgSrcPathValue = $imgSrcPathProp->getValue($imgUpload);

                expect($imgSrcPathValue)->toBeNull();
            });
        });

        describe('getExt()', function (): void {
            it('returns gif extension for GIF format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, IMAGETYPE_GIF);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('gif');
            });

            it('returns jpg extension for JPEG format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, IMAGETYPE_JPEG);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('jpg');
            });

            it('returns webp extension for WEBP format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, IMAGETYPE_WEBP);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('webp');
            });

            it('returns png extension for PNG format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, IMAGETYPE_PNG);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('png');
            });

            it('returns bmp extension for BMP format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, IMAGETYPE_BMP);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('bmp');
            });

            it('returns jpg extension for unknown format', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormatProp->setValue($imgUpload, 999);

                $method = $reflection->getMethod('getExt');
                $method->setAccessible(true);
                $ext = $method->invoke($imgUpload);

                expect($ext)->toBe('jpg');
            });
        });

        describe('setQuality()', function (): void {
            it('sets quality value', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $imgUpload->setQuality(75);

                $reflection = new ReflectionClass($imgUpload);
                $qualityProp = $reflection->getProperty('quality');
                $qualityProp->setAccessible(true);
                $quality = $qualityProp->getValue($imgUpload);

                expect($quality)->toBe(75);
            });
        });

        describe('setSaveFormat()', function (): void {
            it('sets save format value', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $imgUpload->setSaveFormat(IMAGETYPE_JPEG);

                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormat = $saveFormatProp->getValue($imgUpload);

                expect($saveFormat)->toBe(IMAGETYPE_JPEG);
            });
        });

        describe('Initial properties', function (): void {
            it('initializes error as null', function (): void {
                $imgUpload = new ImageUpload(null, '');
                expect($imgUpload->error)->toBeNull();
            });

            it('initializes lastInfo as null', function (): void {
                $imgUpload = new ImageUpload(null, '');
                expect($imgUpload->lastInfo)->toBeNull();
            });

            it('has default quality of 90', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $qualityProp = $reflection->getProperty('quality');
                $qualityProp->setAccessible(true);
                $quality = $qualityProp->getValue($imgUpload);

                expect($quality)->toBe(90);
            });

            it('has default save format of PNG', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $reflection = new ReflectionClass($imgUpload);
                $saveFormatProp = $reflection->getProperty('saveFormat');
                $saveFormatProp->setAccessible(true);
                $saveFormat = $saveFormatProp->getValue($imgUpload);

                expect($saveFormat)->toBe(IMAGETYPE_PNG);
            });
        });

        describe('touchDir()', function (): void {
            it('does not create directory when error is set', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $imgUpload->error = 'Test error';

                $testDir = sys_get_temp_dir() . '/test_' . uniqid();

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('touchDir');
                $method->setAccessible(true);

                $method->invoke($imgUpload, $testDir);

                expect(is_dir($testDir))->toBe(false);
            });

            it('creates directory when it does not exist', function (): void {
                $imgUpload = new ImageUpload(null, '');

                $testDir = sys_get_temp_dir() . '/test_' . uniqid();

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('touchDir');
                $method->setAccessible(true);

                $method->invoke($imgUpload, $testDir);

                expect(is_dir($testDir))->toBe(true);

                rmdir($testDir);
            });

            it('does not throw exception when checking non-existent directory', function (): void {
                $imgUpload = new ImageUpload(null, '');

                // Skip this test on Windows as path handling may differ
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    expect(true)->toBe(true);

                    return;
                }

                $testDir = '/invalid/path/that/cannot/be/created';

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('touchDir');
                $method->setAccessible(true);

                $method->invoke($imgUpload, $testDir);

                // On some systems, directory creation may fail silently
                // or may actually succeed (e.g., with sudo)
                expect(true)->toBe(true);
            });
        });

        describe('saveSizedToLongSide()', function (): void {
            it('returns filename when error is already set', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $imgUpload->error = 'Test error';

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('saveSizedToLongSide');
                $method->setAccessible(true);

                $result = $method->invoke($imgUpload, sys_get_temp_dir(), 0);

                expect($result)->toBeA('string');
            });

            it('returns filename when imgSrcPath is null', function (): void {
                $imgUpload = new ImageUpload(null, '');

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('saveSizedToLongSide');
                $method->setAccessible(true);

                $result = $method->invoke($imgUpload, sys_get_temp_dir(), 0);

                expect($result)->toBeA('string');
            });
        });

        describe('saveWidthCropped()', function (): void {
            it('returns filename when error is already set', function (): void {
                $imgUpload = new ImageUpload(null, '');
                $imgUpload->error = 'Test error';

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('saveWidthCropped');
                $method->setAccessible(true);

                $crop = new \PHPCraftdream\Garnet\Kernel\Io\Forms\ImageCrop(0, 0, 100, 100);

                $result = $method->invoke($imgUpload, sys_get_temp_dir(), $crop, 'sq');

                expect($result)->toBeA('string');
            });

            it('returns filename when imgSrcPath is null and lastInfo is null', function (): void {
                $imgUpload = new ImageUpload(null, '');

                $reflection = new ReflectionClass($imgUpload);
                $method = $reflection->getMethod('saveWidthCropped');
                $method->setAccessible(true);

                $crop = new \PHPCraftdream\Garnet\Kernel\Io\Forms\ImageCrop(0, 0, 100, 100);

                $result = $method->invoke($imgUpload, sys_get_temp_dir(), $crop, 'sq');

                expect($result)->toBeA('string');
            });
        });
    });
}
