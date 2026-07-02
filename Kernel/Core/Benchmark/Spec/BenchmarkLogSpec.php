<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;

describe('BenchmarkLog', function (): void {
    beforeEach(function (): void {
        // Reset static state between tests
        $reflection = new ReflectionClass(BenchmarkLog::class);

        $startProp = $reflection->getProperty('start');
        $startProp->setAccessible(true);
        $startProp->setValue(0.0);

        $lastProp = $reflection->getProperty('last');
        $lastProp->setAccessible(true);
        $lastProp->setValue(0.0);

        $itemsProp = $reflection->getProperty('items');
        $itemsProp->setAccessible(true);
        $itemsProp->setValue([]);
    });

    describe('init()', function (): void {
        it('initializes with start message', function (): void {
            BenchmarkLog::init('Start');

            $reflection = new ReflectionClass(BenchmarkLog::class);
            $itemsProp = $reflection->getProperty('items');
            $itemsProp->setAccessible(true);
            $items = $itemsProp->getValue();

            expect(count($items))->toBe(1);
            expect($items[0])->toBe([0, 'Start']);
        });

        it('sets start time', function (): void {
            BenchmarkLog::init('Test');

            $reflection = new ReflectionClass(BenchmarkLog::class);
            $startProp = $reflection->getProperty('start');
            $startProp->setAccessible(true);
            $start = $startProp->getValue();

            expect($start)->toBeGreaterThan(0);
            expect($start)->toBeLessThan(microtime(true) + 1);
        });
    });

    describe('log()', function (): void {
        it('records log entry with time elapsed', function (): void {
            BenchmarkLog::init('Start');
            usleep(1000); // Small delay
            BenchmarkLog::log('Step 1');

            $reflection = new ReflectionClass(BenchmarkLog::class);
            $itemsProp = $reflection->getProperty('items');
            $itemsProp->setAccessible(true);
            $items = $itemsProp->getValue();

            expect(count($items))->toBe(2);
            expect($items[1][1])->toBe('Step 1');
            expect($items[1][0] >= 0)->toBe(true);
        });

        it('records multiple log entries', function (): void {
            BenchmarkLog::init('Start');
            BenchmarkLog::log('Step 1');
            BenchmarkLog::log('Step 2');
            BenchmarkLog::log('Step 3');

            $reflection = new ReflectionClass(BenchmarkLog::class);
            $itemsProp = $reflection->getProperty('items');
            $itemsProp->setAccessible(true);
            $items = $itemsProp->getValue();

            expect(count($items))->toBe(4);
        });

        it('sets last value', function (): void {
            BenchmarkLog::init('Start');
            BenchmarkLog::log('Step 1');

            $last = BenchmarkLog::last();
            expect($last >= 0)->toBe(true);
        });
    });

    describe('last()', function (): void {
        it('returns last logged time', function (): void {
            BenchmarkLog::init('Start');
            usleep(1000);
            BenchmarkLog::log('Step 1');

            $last = BenchmarkLog::last();
            expect($last)->toBeGreaterThan(0);
        });

        it('returns 0 before any log', function (): void {
            $last = BenchmarkLog::last();
            expect($last)->toBe(0.0);
        });
    });

    describe('printItems()', function (): void {
        it('returns formatted string with items', function (): void {
            BenchmarkLog::init('Start');
            BenchmarkLog::log('Step 1');
            BenchmarkLog::log('Step 2');

            $output = BenchmarkLog::printItems();

            expect($output)->toContain('Start');
            expect($output)->toContain('Step 1');
            expect($output)->toContain('Step 2');
        });

        it('separates items with newlines', function (): void {
            BenchmarkLog::init('A');
            BenchmarkLog::log('B');

            $output = BenchmarkLog::printItems();
            expect($output)->toContain(PHP_EOL);
        });

        it('includes time values', function (): void {
            BenchmarkLog::init('Start');
            BenchmarkLog::log('Step');

            $output = BenchmarkLog::printItems();
            expect($output)->toContain('-');
            expect($output)->toContain('Start');
        });

        it('handles empty items', function (): void {
            $output = BenchmarkLog::printItems();
            expect($output)->toBe('');
        });
    });
});
