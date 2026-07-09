<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Tools\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\Tools\DateTools;
    use DateTimeZone;

    describe('DateTools', function (): void {
        describe('formatForUser()', function (): void {
            it('formats a unix timestamp in the given timezone', function (): void {
                // 2024-01-01 00:00:00 UTC
                $ts = 1704067200;
                $result = DateTools::formatForUser($ts, 'UTC');
                expect($result)->toBe('2024-01-01 00:00');
            });

            it('applies the user timezone offset', function (): void {
                $ts = 1704067200; // 2024-01-01 00:00 UTC
                $result = DateTools::formatForUser($ts, 'Europe/Moscow'); // UTC+3
                expect($result)->toBe('2024-01-01 03:00');
            });

            it('returns empty string for ts <= 0', function (): void {
                expect(DateTools::formatForUser(0, 'UTC'))->toBe('');
                expect(DateTools::formatForUser(-1, 'UTC'))->toBe('');
            });

            it('falls back to UTC for null timezone', function (): void {
                $ts = 1704067200;
                $result = DateTools::formatForUser($ts, null);
                expect($result)->toBe('2024-01-01 00:00');
            });

            it('falls back to UTC for empty timezone', function (): void {
                $ts = 1704067200;
                $result = DateTools::formatForUser($ts, '');
                expect($result)->toBe('2024-01-01 00:00');
            });

            it('falls back to UTC for invalid timezone', function (): void {
                $ts = 1704067200;
                $result = DateTools::formatForUser($ts, 'Invalid/Zone');
                expect($result)->toBe('2024-01-01 00:00');
            });

            it('respects custom format string', function (): void {
                $ts = 1704067200;
                $result = DateTools::formatForUser($ts, 'UTC', 'd.m.Y');
                expect($result)->toBe('01.01.2024');
            });

            it('handles negative UTC offset timezone', function (): void {
                $ts = 1704067200; // 2024-01-01 00:00 UTC
                $result = DateTools::formatForUser($ts, 'America/New_York'); // UTC-5
                expect($result)->toBe('2023-12-31 19:00');
            });
        });

        describe('resolveZone()', function (): void {
            it('returns UTC for null', function (): void {
                $zone = DateTools::resolveZone(null);
                expect($zone->getName())->toBe('UTC');
            });

            it('returns UTC for empty string', function (): void {
                $zone = DateTools::resolveZone('');
                expect($zone->getName())->toBe('UTC');
            });

            it('returns the requested timezone for valid input', function (): void {
                $zone = DateTools::resolveZone('Asia/Jerusalem');
                expect($zone->getName())->toBe('Asia/Jerusalem');
            });

            it('returns UTC for invalid timezone', function (): void {
                $zone = DateTools::resolveZone('Fake/Timezone');
                expect($zone->getName())->toBe('UTC');
            });

            it('returns a DateTimeZone instance', function (): void {
                $zone = DateTools::resolveZone('Europe/London');
                expect($zone)->toBeAnInstanceOf(DateTimeZone::class);
            });
        });
    });
}
