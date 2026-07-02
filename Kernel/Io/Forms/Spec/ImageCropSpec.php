<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    describe('ImageCrop', function (): void {
        describe('::fromPost()', function (): void {
            it('creates instance from post data', function (): void {
                $postData = [
                    'x' => '10',
                    'y' => '20',
                    'width' => '100',
                    'height' => '200',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->x)->toBe(10);
                expect($crop->y)->toBe(20);
                expect($crop->w)->toBe(100);
                expect($crop->h)->toBe(200);
            });

            it('defaults to zero for missing x', function (): void {
                $postData = [
                    'y' => '20',
                    'width' => '100',
                    'height' => '200',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->x)->toBe(0);
            });

            it('defaults to zero for missing y', function (): void {
                $postData = [
                    'x' => '10',
                    'width' => '100',
                    'height' => '200',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->y)->toBe(0);
            });

            it('defaults to 900 for missing width', function (): void {
                $postData = [
                    'x' => '10',
                    'y' => '20',
                    'height' => '200',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->w)->toBe(900);
            });

            it('defaults to 900 for missing height', function (): void {
                $postData = [
                    'x' => '10',
                    'y' => '20',
                    'width' => '100',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->h)->toBe(900);
            });

            it('handles empty post data', function (): void {
                $postData = [];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->x)->toBe(0);
                expect($crop->y)->toBe(0);
                expect($crop->w)->toBe(900);
                expect($crop->h)->toBe(900);
            });

            it('handles string values with intval', function (): void {
                $postData = [
                    'x' => '10.5',
                    'y' => '20.9',
                    'width' => '100.1',
                    'height' => '200.7',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->x)->toBe(10);
                expect($crop->y)->toBe(20);
                expect($crop->w)->toBe(100);
                expect($crop->h)->toBe(200);
            });

            it('handles negative coordinates', function (): void {
                $postData = [
                    'x' => '-10',
                    'y' => '-20',
                    'width' => '-100',
                    'height' => '-200',
                ];

                $crop = ImageCrop::fromPost($postData);

                expect($crop->x)->toBe(-10);
                expect($crop->y)->toBe(-20);
                expect($crop->w)->toBe(-100);
                expect($crop->h)->toBe(-200);
            });
        });

        describe('->scale()', function (): void {
            it('scales coordinates by factor', function (): void {
                $crop = new ImageCrop(10, 20, 100, 200);
                $crop->scale(2.0);

                expect($crop->x)->toBe(20);
                expect($crop->y)->toBe(40);
                expect($crop->w)->toBe(200);
                expect($crop->h)->toBe(400);
            });

            it('scales coordinates by half', function (): void {
                $crop = new ImageCrop(100, 200, 400, 800);
                $crop->scale(0.5);

                expect($crop->x)->toBe(50);
                expect($crop->y)->toBe(100);
                expect($crop->w)->toBe(200);
                expect($crop->h)->toBe(400);
            });

            it('rounds scaled values', function (): void {
                $crop = new ImageCrop(10, 20, 30, 40);
                $crop->scale(1.5);

                expect($crop->x)->toBe(15);
                expect($crop->y)->toBe(30);
                expect($crop->w)->toBe(45);
                expect($crop->h)->toBe(60);
            });

            it('handles scaling with float', function (): void {
                $crop = new ImageCrop(10, 20, 30, 40);
                $crop->scale(1.333333);

                expect($crop->x)->toBe(13);
                expect($crop->y)->toBe(27);
                expect($crop->w)->toBe(40);
                expect($crop->h)->toBe(53);
            });

            it('scales by one leaves values unchanged', function (): void {
                $crop = new ImageCrop(10, 20, 100, 200);
                $crop->scale(1.0);

                expect($crop->x)->toBe(10);
                expect($crop->y)->toBe(20);
                expect($crop->w)->toBe(100);
                expect($crop->h)->toBe(200);
            });

            it('scales by zero sets to zero', function (): void {
                $crop = new ImageCrop(10, 20, 100, 200);
                $crop->scale(0.0);

                expect($crop->x)->toBe(0);
                expect($crop->y)->toBe(0);
                expect($crop->w)->toBe(0);
                expect($crop->h)->toBe(0);
            });
        });

        describe('->json()', function (): void {
            it('serializes to json', function (): void {
                $crop = new ImageCrop(10, 20, 100, 200);
                $json = $crop->json();

                expect($json)->toContain('"x":10');
                expect($json)->toContain('"y":20');
                expect($json)->toContain('"w":100');
                expect($json)->toContain('"h":200');
            });

            it('returns valid json format', function (): void {
                $crop = new ImageCrop(10, 20, 100, 200);
                $json = $crop->json();

                $decoded = json_decode($json, true);
                expect($decoded)->toBeAn('array');
                expect($decoded['x'])->toBe(10);
                expect($decoded['y'])->toBe(20);
                expect($decoded['w'])->toBe(100);
                expect($decoded['h'])->toBe(200);
            });

            it('handles zero coordinates in json', function (): void {
                $crop = new ImageCrop(0, 0, 0, 0);
                $json = $crop->json();

                $decoded = json_decode($json, true);
                expect($decoded['x'])->toBe(0);
                expect($decoded['y'])->toBe(0);
                expect($decoded['w'])->toBe(0);
                expect($decoded['h'])->toBe(0);
            });
        });

        describe('->isEqual()', function (): void {
            it('compares equal instances', function (): void {
                $crop1 = new ImageCrop(10, 20, 100, 200);
                $crop2 = new ImageCrop(10, 20, 100, 200);

                expect($crop1->isEqual($crop2))->toBe(true);
            });

            it('detects different x', function (): void {
                $crop1 = new ImageCrop(10, 20, 100, 200);
                $crop2 = new ImageCrop(11, 20, 100, 200);

                expect($crop1->isEqual($crop2))->toBe(false);
            });

            it('detects different y', function (): void {
                $crop1 = new ImageCrop(10, 20, 100, 200);
                $crop2 = new ImageCrop(10, 21, 100, 200);

                expect($crop1->isEqual($crop2))->toBe(false);
            });

            it('detects different width', function (): void {
                $crop1 = new ImageCrop(10, 20, 100, 200);
                $crop2 = new ImageCrop(10, 20, 101, 200);

                expect($crop1->isEqual($crop2))->toBe(false);
            });

            it('detects different height', function (): void {
                $crop1 = new ImageCrop(10, 20, 100, 200);
                $crop2 = new ImageCrop(10, 20, 100, 201);

                expect($crop1->isEqual($crop2))->toBe(false);
            });
        });
    });
}
