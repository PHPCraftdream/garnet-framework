<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkCallback;
use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;

describe('BenchmarkCallback', function (): void {
    beforeEach(function (): void {
        BenchmarkLog::init('test_init');
    });

    describe('run()', function (): void {
        it('executes registered callbacks', function (): void {
            $benchmark = new BenchmarkCallback(10);

            $benchmark->setCallBack('callback1', function ($i): void {});
            $benchmark->setCallBack('callback2', function ($i): void {});

            $result = $benchmark->run();

            expect(array_key_exists('callback1', $result))->toBe(true);
            expect(array_key_exists('callback2', $result))->toBe(true);
            expect($result['callback1'] >= 0)->toBe(true);
        });

        it('passes iteration index to callback', function (): void {
            $benchmark = new BenchmarkCallback(5);
            $indices = [];

            $benchmark->setCallBack('collector', function ($i) use (&$indices): void {
                $indices[] = $i;
            });

            $benchmark->run();
            expect($indices)->toBe([0, 1, 2, 3, 4]);
        });

        it('returns empty array when no callbacks set', function (): void {
            $benchmark = new BenchmarkCallback(10);
            $result = $benchmark->run();
            expect($result)->toBe([]);
        });

        it('uses experiment size from constructor', function (): void {
            $benchmark = new BenchmarkCallback(2);
            $count = 0;

            $benchmark->setCallBack('counter', function () use (&$count): void {
                $count++;
            });

            $benchmark->run();
            expect($count)->toBe(2);
        });
    });
});

describe('BenchmarkLog', function (): void {
    beforeEach(function (): void {
        $reflection = new ReflectionClass(BenchmarkLog::class);
        $itemsProp = $reflection->getProperty('items');
        $itemsProp->setAccessible(true);
        $itemsProp->setValue([]);
    });

    describe('init()', function (): void {
        it('initializes log with first entry', function (): void {
            BenchmarkLog::init('Start');

            $result = BenchmarkLog::printItems();
            expect($result)->toContain('Start');
        });
    });

    describe('log()', function (): void {
        it('adds log entry with elapsed time', function (): void {
            BenchmarkLog::init('Start');
            usleep(10000);
            BenchmarkLog::log('Step 1');

            $result = BenchmarkLog::printItems();
            expect($result)->toContain('Start');
            expect($result)->toContain('Step 1');
        });
    });

    describe('printItems()', function (): void {
        it('returns formatted log entries', function (): void {
            BenchmarkLog::init('Start');
            BenchmarkLog::log('Event');

            $result = BenchmarkLog::printItems();
            $lines = explode(PHP_EOL, $result);

            expect(count($lines))->toBe(2);
            expect($lines[0])->toContain(' - Start');
            expect($lines[1])->toContain(' - Event');
        });
    });

    describe('last()', function (): void {
        it('returns last logged time', function (): void {
            BenchmarkLog::init('Start');
            usleep(10000);
            BenchmarkLog::log('Event');

            $last = BenchmarkLog::last();
            expect($last)->toBeGreaterThan(0);
            expect($last)->toBeLessThan(1);
        });

        it('updates with each log call', function (): void {
            BenchmarkLog::init('Start');

            usleep(10000);
            BenchmarkLog::log('Event 1');
            $time1 = BenchmarkLog::last();

            usleep(10000);
            BenchmarkLog::log('Event 2');
            $time2 = BenchmarkLog::last();

            expect($time2)->toBeGreaterThan($time1);
        });
    });
});
