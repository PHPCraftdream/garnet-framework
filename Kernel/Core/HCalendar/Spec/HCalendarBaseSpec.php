<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarBase;

describe('HCalendarBase', function (): void {
    describe('fromDateTime()', function (): void {
        it('converts to Hebrew date', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->getHYear())->toBeGreaterThan(0);
            expect($calendar->getHDay())->toBeGreaterThan(0);
        });

        it('sets week day', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->getWeekDay())->toBe(2); // Monday = 2
        });

        it('handles current date', function (): void {
            $date = new DateTime();
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->getHYear())->toBeGreaterThan(0);
        });
    });

    describe('fromStrMonthHDate()', function (): void {
        it('creates from Hebrew month name', function (): void {
            $calendar = HCalendarBase::fromStrMonthHDate(5784, 'tishrei', 1);

            expect($calendar->getHYear())->toBe(5784);
            expect($calendar->getHDay())->toBe(1);
        });

        it('creates with nissan month', function (): void {
            $calendar = HCalendarBase::fromStrMonthHDate(5784, 'nissan', 15);

            expect($calendar->getHMonthName())->toContain('Nissan');
            expect($calendar->getHDay())->toBe(15);
        });

        it('throws exception for unknown month', function (): void {
            expect(function (): void {
                HCalendarBase::fromStrMonthHDate(5784, 'unknown', 1);
            })->toThrow();
        });
    });

    describe('addDays()', function (): void {
        it('adds positive days', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            $calendar->addDays(7);

            expect($calendar->toStr())->toContain('2024-01-08');
        });

        it('adds negative days', function (): void {
            $date = new DateTime('2024-01-08');
            $calendar = HCalendarBase::fromDateTime($date);

            $calendar->addDays(-7);

            expect($calendar->toStr())->toBe('2024-01-01');
        });

        it('updates Hebrew date after adding days', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            $calendar->addDays(30);

            expect($calendar->getHYear())->toBeGreaterThan(0);
            expect($calendar->getHMonthNum())->toBeGreaterThan(0);
        });
    });

    describe('toStrH()', function (): void {
        it('returns Hebrew date string', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);
            $result = $calendar->toStrH();

            expect($result)->toContain($calendar->getHDay());
            expect($result)->toContain($calendar->getHMonthName());
            expect($result)->toContain((string)$calendar->getHYear());
        });

        it('contains day, month name, and year', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);
            $result = $calendar->toStrH();

            expect($result)->toContain((string)$calendar->getHDay());
            expect($result)->toContain($calendar->getHMonthName());
            expect($result)->toContain((string)$calendar->getHYear());
        });
    });

    describe('toStr()', function (): void {
        it('returns Gregorian date string', function (): void {
            $date = new DateTime('2024-05-15');
            $calendar = HCalendarBase::fromDateTime($date);
            $result = $calendar->toStr();

            expect($result)->toBe('2024-05-15');
        });

        it('formats with leading zeros', function (): void {
            $date = new DateTime('2024-01-05');
            $calendar = HCalendarBase::fromDateTime($date);
            $result = $calendar->toStr();

            expect($result)->toBe('2024-01-05');
        });
    });

    describe('toDate()', function (): void {
        it('returns clone of internal date', function (): void {
            $date = new DateTime('2024-05-15');
            $calendar = HCalendarBase::fromDateTime($date);
            $result = $calendar->toDate();

            expect($result->format('Y-m-d'))->toBe('2024-05-15');
        });
    });

    describe('getters', function (): void {
        it('returns all Hebrew date components for 2024-01-01', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->getHYear())->toBeGreaterThan(5780);
            expect($calendar->getHMonthName())->not->toBeEmpty();
            expect($calendar->getHDay())->toBeGreaterThan(0);
            expect($calendar->getHMonthNum() >= 1)->toBe(true);
            expect($calendar->getHMonthNum() <= 13)->toBe(true);
            expect($calendar->getWeekDay())->toBe(2); // Monday = 2
        });
    });

    describe('Shabbat detection', function (): void {
        it('detects Shabbat on Saturday', function (): void {
            // 2024-01-06 is Saturday
            $date = new DateTime('2024-01-06');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->isShabbat())->toBe(true);
        });

        it('is not Shabbat on weekday', function (): void {
            // 2024-01-01 is Monday
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->isShabbat())->toBe(false);
        });

        it('detects Sheshi on Friday', function (): void {
            // 2024-01-05 is Friday
            $date = new DateTime('2024-01-05');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->isSheshi())->toBe(true);
        });

        it('is not Sheshi on other days', function (): void {
            $date = new DateTime('2024-01-01');
            $calendar = HCalendarBase::fromDateTime($date);

            expect($calendar->isSheshi())->toBe(false);
        });
    });

    describe('day info items', function (): void {
        it('includes Shabbat in location items on Saturday', function (): void {
            $date = new DateTime('2024-01-06');
            $calendar = HCalendarBase::fromDateTime($date);

            $israelItems = $calendar->getIsraelItems();
            $outItems = $calendar->getOutItems();
            expect(in_array('Shabbat', $israelItems, true))->toBe(true);
            expect(in_array('Shabbat', $outItems, true))->toBe(true);
        });
    });
});
