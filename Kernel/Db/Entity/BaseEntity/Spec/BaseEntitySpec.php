<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity {
    class TestableBaseEntity extends BaseEntity {
        public function __construct() {
        }

        public function testFilterKeys(array $src, ?array $keys = null): array {
            return $this->filterKeys($src, $keys);
        }

        public function testSaveOne(array $postData, array $fields, ?SaveFilesParams $saveFiles = null): SaveEntityResult {
            return $this->saveOne($postData, $fields, $saveFiles);
        }

        public function getFieldsInfo(array $fields = null): array {
            return [];
        }

        public function patchItem(array &$item): array {
            return $item;
        }
    }

    describe('BaseEntity', function (): void {
        describe('::filterKeys()', function (): void {
            it('returns original array when keys is null', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['a' => 1, 'b' => 2, 'c' => 3];
                $result = $mock->testFilterKeys($src, null);

                expect($result)->toBe($src);
            });

            it('returns original array when keys is empty', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['a' => 1, 'b' => 2, 'c' => 3];
                $result = $mock->testFilterKeys($src, []);

                expect($result)->toBe($src);
            });

            it('filters array by specified keys', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['a' => 1, 'b' => 2, 'c' => 3];
                $keys = ['a', 'c'];
                $result = $mock->testFilterKeys($src, $keys);

                expect($result)->toBe(['a' => 1, 'c' => 3]);
            });

            it('handles keys that do not exist in source', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['a' => 1, 'b' => 2];
                $keys = ['a', 'x', 'y'];
                $result = $mock->testFilterKeys($src, $keys);

                expect($result)->toBe(['a' => 1]);
            });

            it('preserves original keys and values', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['name' => 'John', 'age' => 30, 'city' => 'NYC'];
                $keys = ['name', 'city'];
                $result = $mock->testFilterKeys($src, $keys);

                expect($result['name'])->toBe('John');
                expect($result['city'])->toBe('NYC');
            });

            it('returns empty array when no keys match', function (): void {
                $mock = new TestableBaseEntity();
                $src = ['a' => 1, 'b' => 2];
                $keys = ['x', 'y'];
                $result = $mock->testFilterKeys($src, $keys);

                expect($result)->toBe([]);
            });
        });

        describe('::saveOne()', function (): void {
            it('creates SaveEntityResult with update from postData', function (): void {
                $mock = new TestableBaseEntity();

                $postData = ['name' => 'test'];
                $fields = ['id'];
                $saveFiles = new SaveFilesParams([], '/tmp');

                $result = $mock->testSaveOne($postData, $fields, $saveFiles);

                expect($result->update)->toBeAnInstanceOf(\PHPCraftdream\Garnet\Kernel\Io\Forms\Updater::class);
            });

            it('works with null SaveFilesParams', function (): void {
                $mock = new TestableBaseEntity();

                $postData = [];
                $fields = ['id'];

                $result = $mock->testSaveOne($postData, $fields, null);

                expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            });
        });
    });

    describe('SaveFilesParams', function (): void {
        it('stores files and baseDir correctly', function (): void {
            $files = ['file' => ['tmp_name' => '/tmp/test']];
            $baseDir = '/uploads';

            $params = new SaveFilesParams($files, $baseDir);

            expect($params->files)->toBe($files);
            expect($params->baseDir)->toBe($baseDir);
            expect($params->prevData)->toBe([]);
        });

        it('stores prevData when provided', function (): void {
            $files = [];
            $baseDir = '/uploads';
            $prevData = ['photo' => 'old.jpg'];

            $params = new SaveFilesParams($files, $baseDir, $prevData);

            expect($params->prevData)->toBe($prevData);
        });

        it('static factory method creates instance correctly', function (): void {
            $files = [];
            $baseDir = '/uploads';
            $prevData = ['test' => 'value'];

            $params = SaveFilesParams::make($files, $baseDir, $prevData);

            expect($params->files)->toBe($files);
            expect($params->baseDir)->toBe($baseDir);
            expect($params->prevData)->toBe($prevData);
        });
    });

    describe('SaveEntityResult', function (): void {
        it('stores update and addData correctly', function (): void {
            $updater1 = new \PHPCraftdream\Garnet\Kernel\Io\Forms\Updater([]);
            $updater2 = new \PHPCraftdream\Garnet\Kernel\Io\Forms\Updater([]);

            $result = new SaveEntityResult($updater1, $updater2);

            expect($result->update)->toBe($updater1);
            expect($result->addData)->toBe($updater2);
        });

        it('addData defaults to null', function (): void {
            $updater = new \PHPCraftdream\Garnet\Kernel\Io\Forms\Updater([]);

            $result = new SaveEntityResult($updater);

            expect($result->update)->toBe($updater);
            expect($result->addData)->toBe(null);
        });
    });
}
