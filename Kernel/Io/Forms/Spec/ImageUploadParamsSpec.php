<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms\Spec;

use PHPCraftdream\Garnet\Kernel\Io\Forms\ImageUploadParams;

describe('ImageUploadParams', function (): void {
    describe('constructor', function (): void {
        it('creates params with required fields', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
            );

            expect($params->uploadDir)->toBe('/uploads');
            expect($params->fileNameField)->toBe('photo');
        });

        it('creates params with all fields', function (): void {
            $cropParams = new \PHPCraftdream\Garnet\Kernel\Io\Forms\ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
            );

            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
                uploadTmpFile: '/tmp/upload.jpg',
                prevFileName: 'old_photo.jpg',
                cropParams: $cropParams,
            );

            expect($params->uploadDir)->toBe('/uploads');
            expect($params->fileNameField)->toBe('photo');
            expect($params->uploadTmpFile)->toBe('/tmp/upload.jpg');
            expect($params->prevFileName)->toBe('old_photo.jpg');
            expect($params->cropParams)->toBe($cropParams);
        });

        it('stores uploadDir as readonly', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
            );

            expect($params->uploadDir)->toBe('/uploads');
        });

        it('stores fileNameField as readonly', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'my_photo',
            );

            expect($params->fileNameField)->toBe('my_photo');
        });

        it('defaults uploadTmpFile to null', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
            );

            expect($params->uploadTmpFile)->toBeNull();
        });

        it('defaults prevFileName to null', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
            );

            expect($params->prevFileName)->toBeNull();
        });

        it('defaults cropParams to null', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
            );

            expect($params->cropParams)->toBeNull();
        });

        it('accepts empty string for prevFileName', function (): void {
            $params = new ImageUploadParams(
                uploadDir: '/uploads',
                fileNameField: 'photo',
                prevFileName: '',
            );

            expect($params->prevFileName)->toBe('');
        });
    });
});
