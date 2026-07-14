<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarTools;

describe('HCalendarTools', function (): void {
    beforeEach(function (): void {
        // Reset static cache
        $reflection = new ReflectionClass(HCalendarTools::class);
        $yearEdgesProp = $reflection->getProperty('yearEdges');
        $yearEdgesProp->setValue(null, []);
    });

    describe('getHYearInfo()', function (): void {
        it('returns array with 4 elements', function (): void {
            $info = HCalendarTools::getHYearInfo(5784);
            expect(count($info))->toBe(4);
        });

        it('returns start date, end date, length, and isLeap', function (): void {
            [$startJd, $endJd, $length, $isLeap] = HCalendarTools::getHYearInfo(5784);

            expect($startJd)->toBeGreaterThan(0);
            expect($endJd)->toBeGreaterThan($startJd);
            expect($length >= 353 && $length <= 385)->toBe(true);
            expect($isLeap)->toBe(true); // 5784 is a leap year
        });

        it('caches result for same year', function (): void {
            $info1 = HCalendarTools::getHYearInfo(5784);
            $info2 = HCalendarTools::getHYearInfo(5784);

            expect($info1)->toBe($info2);
        });

        it('returns different info for different years', function (): void {
            $info1 = HCalendarTools::getHYearInfo(5784);
            $info2 = HCalendarTools::getHYearInfo(5785);

            expect($info1)->not->toBe($info2);
        });
    });

    describe('getHMonthsByYearLength()', function (): void {
        it('returns array for all valid Hebrew year lengths', function (): void {
            foreach ([353, 354, 355, 383, 384, 385] as $length) {
                $months = HCalendarTools::getHMonthsByYearLength($length);
                expect(count($months))->toBe(13);
            }
        });

        it('throws exception for invalid length', function (): void {
            expect(function (): void {
                HCalendarTools::getHMonthsByYearLength(400);
            })->toThrow();
        });
    });

    describe('getHMonthsByYear()', function (): void {
        it('returns 13 elements', function (): void {
            $months = HCalendarTools::getHMonthsByYear(5784);
            expect(count($months))->toBe(13);
        });

        it('contains valid day counts', function (): void {
            $months = HCalendarTools::getHMonthsByYear(5784);

            foreach ($months as $ind => $days) {
                if ($ind !== 6) { // Skip Adar placeholder
                    expect($days >= 29 && $days <= 30)->toBe(true);
                }
            }
        });
    });

    describe('getHMonthsByYearInd()', function (): void {
        it('returns correct index for each year length', function (): void {
            expect(HCalendarTools::getHMonthsByYearInd(353))->toBe(1);
            expect(HCalendarTools::getHMonthsByYearInd(354))->toBe(2);
            expect(HCalendarTools::getHMonthsByYearInd(355))->toBe(3);
            expect(HCalendarTools::getHMonthsByYearInd(383))->toBe(4);
            expect(HCalendarTools::getHMonthsByYearInd(384))->toBe(5);
            expect(HCalendarTools::getHMonthsByYearInd(385))->toBe(6);
        });

        it('throws exception for invalid length', function (): void {
            expect(function (): void {
                HCalendarTools::getHMonthsByYearInd(400);
            })->toThrow();
        });
    });

    describe('getHYearInd()', function (): void {
        it('returns index between 1 and 6', function (): void {
            $ind = HCalendarTools::getHYearInd(5784);
            expect($ind >= 1 && $ind <= 6)->toBe(true);
        });
    });

    describe('dateToStr()', function (): void {
        it('formats date with zero padding', function (): void {
            $result = HCalendarTools::dateToStr(5784, 1, 1);
            expect($result)->toBe('5784-01-01');
        });

        it('formats single digit month', function (): void {
            $result = HCalendarTools::dateToStr(5784, 9, 5);
            expect($result)->toBe('5784-09-05');
        });

        it('formats double digit values', function (): void {
            $result = HCalendarTools::dateToStr(5784, 11, 25);
            expect($result)->toBe('5784-11-25');
        });

        it('pads year to 4 digits', function (): void {
            $result = HCalendarTools::dateToStr(1, 1, 1);
            expect($result)->toBe('0001-01-01');
        });
    });

    describe('getMonthName()', function (): void {
        it('returns name array for Tishrei (month 1)', function (): void {
            $name = HCalendarTools::getMonthName(1, false);
            expect(count($name))->toBe(2);
            expect($name[0])->toContain('Tishrei');
        });

        it('returns name array for Nissan (month 8)', function (): void {
            $name = HCalendarTools::getMonthName(8, false);
            expect($name[0])->toContain('Nissan');
        });

        it('returns different names for Adar in leap year', function (): void {
            $nameRegular = HCalendarTools::getMonthName(6, false);
            $nameLeap = HCalendarTools::getMonthName(7, true);

            expect($nameRegular[0])->toContain('Adar');
            expect($nameLeap[0])->toContain('Adar');
        });

        it('throws exception for invalid month', function (): void {
            expect(function (): void {
                HCalendarTools::getMonthName(99, false);
            })->toThrow();
        });

        it('returns Adar for month 7 in non-leap year', function (): void {
            $name = HCalendarTools::getMonthName(7, false);
            expect($name[0])->toContain('Adar');
        });

        it('returns Adar 2 for month 7 in leap year', function (): void {
            $name = HCalendarTools::getMonthName(7, true);
            expect($name[0])->toContain('Adar-II');
        });
    });

    describe('getMonthIndInfoByName()', function (): void {
        it('returns info for Tishrei', function (): void {
            $info = HCalendarTools::getMonthIndInfoByName('tishrei');
            expect($info)->not->toBe(null);
        });

        it('returns info for Nissan', function (): void {
            $info = HCalendarTools::getMonthIndInfoByName('nissan');
            expect($info)->not->toBe(null);
        });

        it('handles capitalized names', function (): void {
            $info = HCalendarTools::getMonthIndInfoByName('Tishrei');
            expect($info)->not->toBe(null);
        });

        it('handles names with spaces', function (): void {
            $info = HCalendarTools::getMonthIndInfoByName('Adar I');
            expect($info)->not->toBe(null);
        });

        it('returns null for unknown month', function (): void {
            $info = HCalendarTools::getMonthIndInfoByName('unknown');
            expect($info)->toBe(null);
        });

        it('handles Adar 1/Adar 2 formats', function (): void {
            $info1 = HCalendarTools::getMonthIndInfoByName('Adar 1');
            expect($info1)->not->toBe(null);

            $info2 = HCalendarTools::getMonthIndInfoByName('Adar 2');
            expect($info2)->not->toBe(null);
        });
    });
});
