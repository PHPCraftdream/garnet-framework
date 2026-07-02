<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms\Spec;

use PHPCraftdream\Garnet\Kernel\Io\Forms\ImageCrop;
use PHPCraftdream\Garnet\Kernel\Io\Forms\ImageCropParams;

describe('ImageCropParams', function (): void {
    describe('constructor', function (): void {
        it('creates params with required fields', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
            );

            expect($params->fileNameField)->toBe('crop_photo');
            expect($params->infoField)->toBe('crop_info');
        });

        it('creates params with all fields', function (): void {
            $imgCrop = new ImageCrop(10, 20, 100, 100);
            $prevImgCrop = new ImageCrop(5, 10, 50, 50);

            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
                imgCrop: $imgCrop,
                prevImgCrop: $prevImgCrop,
                prevFileName: 'old_crop.jpg',
            );

            expect($params->fileNameField)->toBe('crop_photo');
            expect($params->infoField)->toBe('crop_info');
            expect($params->imgCrop)->toBe($imgCrop);
            expect($params->prevImgCrop)->toBe($prevImgCrop);
            expect($params->prevFileName)->toBe('old_crop.jpg');
        });

        it('stores fileNameField as readonly', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'photo_crop',
                infoField: 'crop_data',
            );

            expect($params->fileNameField)->toBe('photo_crop');
        });

        it('stores infoField as readonly', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'photo_crop',
                infoField: 'my_crop_data',
            );

            expect($params->infoField)->toBe('my_crop_data');
        });

        it('defaults imgCrop to null', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
            );

            expect($params->imgCrop)->toBeNull();
        });

        it('defaults prevImgCrop to null', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
            );

            expect($params->prevImgCrop)->toBeNull();
        });

        it('defaults prevFileName to null', function (): void {
            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
            );

            expect($params->prevFileName)->toBeNull();
        });

        it('accepts ImageCrop for imgCrop', function (): void {
            $imgCrop = new ImageCrop(10, 20, 100, 100);

            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
                imgCrop: $imgCrop,
            );

            expect($params->imgCrop)->toBe($imgCrop);
        });

        it('accepts ImageCrop for prevImgCrop', function (): void {
            $prevImgCrop = new ImageCrop(5, 10, 50, 50);

            $params = new ImageCropParams(
                fileNameField: 'crop_photo',
                infoField: 'crop_info',
                prevImgCrop: $prevImgCrop,
            );

            expect($params->prevImgCrop)->toBe($prevImgCrop);
        });
    });
});
