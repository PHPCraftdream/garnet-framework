<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarGauss;

describe('HCalendarGauss', function (): void {
    describe('calculateGauss()', function (): void {
        it('calculates Pesach date for multiple years', function (): void {
            $result5784 = HCalendarGauss::calculateGauss(5784);
            $result5785 = HCalendarGauss::calculateGauss(5785);
            $result5780 = HCalendarGauss::calculateGauss(5780);

            // Pesach falls in March/April (March 25 + result day)
            // So result can be from ~6 (early April) to ~35 (early May)
            expect($result5784)->toBeGreaterThan(0);
            expect($result5784)->toBeLessThan(60);
            expect($result5785)->toBeGreaterThan(0);
            expect($result5785)->toBeLessThan(60);
            expect($result5780)->toBeGreaterThan(0);
            expect($result5780)->toBeLessThan(60);
        });
    });

    describe('isLeapGrYear()', function (): void {
        it('returns true for divisible by 400', function (): void {
            expect(HCalendarGauss::isLeapGrYear(2000))->toBe(true);
        });

        it('returns true for divisible by 4 but not 100', function (): void {
            expect(HCalendarGauss::isLeapGrYear(2024))->toBe(true);
        });

        it('returns false for divisible by 100 but not 400', function (): void {
            expect(HCalendarGauss::isLeapGrYear(1900))->toBe(false);
        });

        it('returns false for not divisible by 4', function (): void {
            expect(HCalendarGauss::isLeapGrYear(2023))->toBe(false);
        });

        it('handles year 0', function (): void {
            expect(HCalendarGauss::isLeapGrYear(0))->toBe(true);
        });
    });

    describe('getHYearLength()', function (): void {
        it('returns length for Hebrew year 5784', function (): void {
            $length = HCalendarGauss::getHYearLength(5784);
            expect($length)->toBeGreaterThan(353);
            expect($length)->toBeLessThan(386);
        });

        it('returns length for Hebrew year 5785', function (): void {
            $length = HCalendarGauss::getHYearLength(5785);
            expect($length)->toBeGreaterThan(353);
            expect($length)->toBeLessThan(386);
        });

        it('returns valid Hebrew year length (353-385 days)', function (): void {
            $length = HCalendarGauss::getHYearLength(5784);
            expect($length >= 353 && $length <= 385)->toBe(true);
        });
    });

    describe('getHMonthsByYear()', function (): void {
        it('returns 13 elements (months)', function (): void {
            $months = HCalendarGauss::getHMonthsByYear(5784);
            expect(count($months))->toBe(13);
        });

        it('contains valid day counts for months', function (): void {
            $months = HCalendarGauss::getHMonthsByYear(5784);

            foreach ($months as $ind => $days) {
                if ($ind !== 6) { // Skip Adar/Adar I placeholder
                    expect($days >= 29 && $days <= 30)->toBe(true);
                }
            }
        });
    });
});
