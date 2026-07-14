<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\IoTools;

describe('IoTools', function (): void {
    beforeEach(function (): void {
        // Reset static benchmark state
        $reflection = new ReflectionClass(IoTools::class);
        $benchmarkProp = $reflection->getProperty('benchmark');
        $benchmarkProp->setValue(null, null);

        $benchmarkArrProp = $reflection->getProperty('benchmarkArr');
        $benchmarkArrProp->setValue(null, []);
    });

    describe('pr()', function (): void {
        it('formats value as HTML pre and escapes HTML', function (): void {
            $result = IoTools::pr('test value');
            expect($result)->toContain('<pre>');
            expect($result)->toContain('test value');
            expect($result)->toContain('</pre>');

            // HTML escaping
            $result = IoTools::pr('<script>alert("xss")</script>');
            expect($result)->toContain('&lt;script&gt;');

            // Arrays
            $result = IoTools::pr(['key' => 'value']);
            expect($result)->toContain('<pre>');
            expect($result)->toContain('key');
            expect($result)->toContain('value');
        });
    });

    describe('varDump()', function (): void {
        it('formats var_dump output as HTML with escaping', function (): void {
            $result = IoTools::varDump('test');
            expect($result)->toContain('<pre>');
            expect($result)->toContain('</pre>');

            // HTML escaping
            $result = IoTools::varDump('<html>test</html>');
            expect($result)->toContain('&lt;html&gt;');

            // Different types
            $result = IoTools::varDump(42);
            expect($result)->toContain('<pre>');
        });
    });

    describe('benchmarking', function (): void {
        it('returns empty array initially', function (): void {
            expect(IoTools::getBenchmarkArr())->toBe([]);
        });

        it('records benchmarks with time elapsed', function (): void {
            $result = IoTools::benchmark('first');
            expect($result)->toContain('first');
            expect($result)->toContain('-');

            usleep(1000);
            IoTools::benchmark('second');

            $arr = IoTools::getBenchmarkArr();
            expect(count($arr))->toBe(2);
            expect($arr[0])->toContain('first');
            expect($arr[1])->toContain('second');
        });

        it('formats time with 5 decimal places', function (): void {
            $result = IoTools::benchmark('test');
            expect(preg_match('/test - \d+\.\d{5}/', $result))->toBe(1);
        });

        it('tracks multiple benchmarks', function (): void {
            IoTools::benchmark('op1');
            IoTools::benchmark('op2');
            IoTools::benchmark('op3');

            $arr = IoTools::getBenchmarkArr();
            expect(count($arr))->toBe(3);
        });

        it('times increase with each call', function (): void {
            $time1 = IoTools::benchmark('first');
            usleep(5000);
            $time2 = IoTools::benchmark('second');

            // Extract time values
            preg_match('/first - ([\d.]+)/', $time1, $matches1);
            preg_match('/second - ([\d.]+)/', $time2, $matches2);

            expect(floatval($matches2[1]))->toBeGreaterThan(floatval($matches1[1]));
        });

        it('includes all benchmark names in array', function (): void {
            IoTools::benchmark('step1');
            IoTools::benchmark('step2');
            IoTools::benchmark('final');

            $arr = IoTools::getBenchmarkArr();
            expect($arr[0])->toContain('step1');
            expect($arr[1])->toContain('step2');
            expect($arr[2])->toContain('final');
        });
    });
});
