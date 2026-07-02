<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkCallback;

describe('BenchmarkCallback', function (): void {
    beforeEach(function (): void {
        // Reset static state between tests
        $reflection = new ReflectionClass(BenchmarkCallback::class);
    });

    describe('setCallBack()', function (): void {
        it('callback receives iteration number', function (): void {
            $benchmark = new BenchmarkCallback(5);
            $iterations = [];

            $benchmark->setCallBack('capture', function ($j) use (&$iterations): void {
                $iterations[] = $j;
            });

            $benchmark->run();

            expect(count($iterations))->toBe(5);
            expect($iterations)->toBe([0, 1, 2, 3, 4]);
        });
    });

    describe('run()', function (): void {
        it('returns empty array when no callbacks registered', function (): void {
            $benchmark = new BenchmarkCallback();
            $result = $benchmark->run();
            expect($result)->toBe([]);
        });

        it('returns results for registered callbacks', function (): void {
            $benchmark = new BenchmarkCallback(10);
            $benchmark->setCallBack('fast', fn () => 1);

            $result = $benchmark->run();
            expect(isset($result['fast']))->toBe(true);
            expect($result['fast'] >= 0)->toBe(true);
        });

        it('measures time for multiple callbacks', function (): void {
            $benchmark = new BenchmarkCallback(5);
            $benchmark->setCallBack('op1', fn () => 1);
            $benchmark->setCallBack('op2', fn () => 2);

            $result = $benchmark->run();
            expect(count($result))->toBe(2);
            expect(isset($result['op1']))->toBe(true);
            expect(isset($result['op2']))->toBe(true);
        });

        it('returns 0 for callback with no iterations', function (): void {
            $benchmark = new BenchmarkCallback(0);
            $benchmark->setCallBack('test', fn () => 1);

            $result = $benchmark->run();
            expect($result['test'])->toBe(0);
        });

        it('respects experiment size', function (): void {
            $callCount = 0;
            $benchmark = new BenchmarkCallback(5);
            $benchmark->setCallBack('counter', function () use (&$callCount): void {
                $callCount++;
            });

            $benchmark->run();
            expect($callCount)->toBe(5);
        });
    });
});
