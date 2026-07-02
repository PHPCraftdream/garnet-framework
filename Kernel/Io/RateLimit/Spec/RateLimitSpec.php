<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\RateLimit\Spec;

use PHPCraftdream\Garnet\Kernel\Io\RateLimit\RateLimit;

// Creates an isolated temporary directory for each test
function makeTmpDir(): string {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rl_spec_' . uniqid('', true);
    mkdir($dir, 0o777, true);

    return $dir;
}

// Removes the temporary directory and all its files
function removeTmpDir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
        unlink($file);
    }
    rmdir($dir);
}

describe('RateLimit', function (): void {
    // -----------------------------------------------------------------------
    describe('hit()', function (): void {
        it('allows the first request', function (): void {
            $tmp = makeTmpDir();
            expect(RateLimit::hit('test:first', 3, 60, $tmp))->toBe(true);
            removeTmpDir($tmp);
        });

        it('allows requests up to and including maxHits', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:upto_max';

            for ($i = 0; $i < 5; $i++) {
                $result = RateLimit::hit($key, 5, 60, $tmp);
                expect($result)->toBe(true);
            }

            removeTmpDir($tmp);
        });

        it('blocks a request beyond maxHits', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:over_max';

            for ($i = 0; $i < 3; $i++) {
                RateLimit::hit($key, 3, 60, $tmp);
            }

            expect(RateLimit::hit($key, 3, 60, $tmp))->toBe(false);
            removeTmpDir($tmp);
        });

        it('a blocked request is not recorded (counter does not grow)', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:no_inc_when_blocked';

            for ($i = 0; $i < 2; $i++) {
                RateLimit::hit($key, 2, 60, $tmp);
            }

            // Three attempts beyond the limit
            RateLimit::hit($key, 2, 60, $tmp);
            RateLimit::hit($key, 2, 60, $tmp);
            RateLimit::hit($key, 2, 60, $tmp);

            // Limit is still 2 — did not drift
            expect(RateLimit::hit($key, 2, 60, $tmp))->toBe(false);
            removeTmpDir($tmp);
        });

        it('different keys are isolated from each other', function (): void {
            $tmp = makeTmpDir();

            for ($i = 0; $i < 3; $i++) {
                RateLimit::hit('key:A', 3, 60, $tmp);
            }

            // key:A is exhausted — key:B is unaffected
            expect(RateLimit::hit('key:A', 3, 60, $tmp))->toBe(false);
            expect(RateLimit::hit('key:B', 3, 60, $tmp))->toBe(true);
            removeTmpDir($tmp);
        });

        it('allows again after the window expires (window=1s)', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:window_expire';

            RateLimit::hit($key, 1, 1, $tmp);
            expect(RateLimit::hit($key, 1, 1, $tmp))->toBe(false);

            sleep(2); // wait for the window to close

            expect(RateLimit::hit($key, 1, 1, $tmp))->toBe(true);
            removeTmpDir($tmp);
        });

        it('maxHits=1: the second request is blocked immediately', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:max1';

            expect(RateLimit::hit($key, 1, 60, $tmp))->toBe(true);
            expect(RateLimit::hit($key, 1, 60, $tmp))->toBe(false);
            removeTmpDir($tmp);
        });

        it('uses sys_get_temp_dir() when tmpDir is empty', function (): void {
            $key = 'test:default_tmp_' . uniqid('', true);
            $result = RateLimit::hit($key, 5, 60);
            expect($result)->toBe(true);

            // Clean up after ourselves
            $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rl_' . md5($key) . '.json';

            if (file_exists($file)) {
                unlink($file);
            }
        });
    });

    // -----------------------------------------------------------------------
    describe('retryAfter()', function (): void {
        it('returns 0 when the limit is not exhausted', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:retry_not_limited';

            RateLimit::hit($key, 5, 60, $tmp);
            expect(RateLimit::retryAfter($key, 5, 60, $tmp))->toBe(0);
            removeTmpDir($tmp);
        });

        it('returns 0 when the file does not exist', function (): void {
            $tmp = makeTmpDir();
            expect(RateLimit::retryAfter('test:no_file', 3, 60, $tmp))->toBe(0);
            removeTmpDir($tmp);
        });

        it('returns a positive number after the limit is exhausted', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:retry_positive';

            for ($i = 0; $i < 3; $i++) {
                RateLimit::hit($key, 3, 60, $tmp);
            }

            $retry = RateLimit::retryAfter($key, 3, 60, $tmp);
            expect($retry > 0)->toBe(true);
            expect($retry <= 60)->toBe(true);
            removeTmpDir($tmp);
        });

        it('returns 0 after the window expires', function (): void {
            $tmp = makeTmpDir();
            $key = 'test:retry_after_expire';

            RateLimit::hit($key, 1, 1, $tmp);

            sleep(2);

            expect(RateLimit::retryAfter($key, 1, 1, $tmp))->toBe(0);
            removeTmpDir($tmp);
        });
    });
});
