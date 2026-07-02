<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Tools\RuntimeParams;

describe('RuntimeParams', function (): void {
    beforeEach(function (): void {
        // Reset singleton instance before each test
        $reflection = new ReflectionClass(RuntimeParams::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null);
    });

    describe('init()', function (): void {
        it('returns singleton instance', function (): void {
            $instance1 = RuntimeParams::init();
            $instance2 = RuntimeParams::init();

            expect($instance1)->toBe($instance2);
        });
    });

    describe('set() and get()', function (): void {
        it('stores and retrieves array parameters', function (): void {
            $params = RuntimeParams::init();
            $params->set('test', ['a' => 1]);

            expect($params->get('test'))->toBe(['a' => 1]);
        });

        it('stores callable and overwrites existing parameters', function (): void {
            $params = RuntimeParams::init();
            $callable = fn () => ['result' => 'value'];
            $params->set('lazy', $callable);
            expect($params->get('lazy'))->toBe(['result' => 'value']);

            $params->set('test', ['a' => 1]);
            $params->set('test', ['b' => 2]);
            expect($params->get('test'))->toBe(['b' => 2]);
        });
    });

    describe('get()', function (): void {
        it('returns stored array parameters and empty array for non-existent', function (): void {
            $params = RuntimeParams::init();
            $params->set('test', ['key' => 'value']);

            expect($params->get('test'))->toBe(['key' => 'value']);
            expect($params->get('nonexistent'))->toBe([]);
        });

        it('executes callable once and caches result', function (): void {
            $params = RuntimeParams::init();
            $callCount = 0;

            $callable = function () use (&$callCount) {
                $callCount++;

                return ['result' => 'lazy'];
            };

            $params->set('lazy', $callable);

            $result1 = $params->get('lazy');
            $result2 = $params->get('lazy');

            expect($result1)->toBe(['result' => 'lazy']);
            expect($result2)->toBe(['result' => 'lazy']);
            expect($callCount)->toBe(1);
        });

        it('appends and merges values', function (): void {
            $params = RuntimeParams::init();

            // Simple append
            $params->set('test', ['a' => 1]);
            expect($params->get('test', ['b' => 2]))->toBe(['a' => 1, 'b' => 2]);

            // Recursive merge
            $params->set('config', ['db' => ['host' => 'localhost']]);
            $result = $params->get('config', ['db' => ['port' => 3306]]);
            expect(isset($result['db']['host']))->toBe(true);
            expect(isset($result['db']['port']))->toBe(true);

            // Non-recursive append
            $params->set('arr', [1, 2]);
            expect($params->get('arr', [3, 4], recursiveAppend: false))->toBe([1, 2, 3, 4]);
        });
    });
});
