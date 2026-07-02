<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarMonths;

describe('HCalendarMonths', function (): void {
    describe('Hebrew month constants', function (): void {
        it('defines capitalized month names', function (): void {
            $expectedMonths = [
                'tishrei' => 'Tishrei',
                'cheshvan' => 'Cheshvan',
                'kislev' => 'Kislev',
                'tevet' => 'Tevet',
                'shevat' => 'Shevat',
                'adar' => 'Adar',
                'nissan' => 'Nissan',
                'iyar' => 'Iyar',
                'sivan' => 'Sivan',
                'tammuz' => 'Tammuz',
                'av' => 'Av',
                'elul' => 'Elul',
            ];

            foreach ($expectedMonths as $const => $expected) {
                expect(constant(HCalendarMonths::class . "::{$const}"))->toBe($expected);
            }
        });

        it('defines Adar variants', function (): void {
            expect(HCalendarMonths::adar1)->toBe('Adar-I');
            expect(HCalendarMonths::adar2)->toBe('Adar-II');
        });
    });

    describe('Lowercase month constants', function (): void {
        it('defines all month names in lowercase', function (): void {
            $expectedLowerMonths = [
                'tishrei_lower' => 'tishrei',
                'cheshvan_lower' => 'cheshvan',
                'kislev_lower' => 'kislev',
                'tevet_lower' => 'tevet',
                'shevat_lower' => 'shevat',
                'adar_lower' => 'adar',
                'nissan_lower' => 'nissan',
                'iyar_lower' => 'iyar',
                'sivan_lower' => 'sivan',
                'tammuz_lower' => 'tammuz',
                'av_lower' => 'av',
                'elul_lower' => 'elul',
            ];

            foreach ($expectedLowerMonths as $const => $expected) {
                expect(constant(HCalendarMonths::class . "::{$const}"))->toBe($expected);
            }
        });

        it('defines Adar variants in lowercase', function (): void {
            expect(HCalendarMonths::adar1_lower)->toBe('adar-i');
            expect(HCalendarMonths::adar2_lower)->toBe('adar-ii');
        });
    });

    describe('Month order consistency', function (): void {
        it('has 12 month constants in each format', function (): void {
            $capitalized = [
                HCalendarMonths::tishrei,
                HCalendarMonths::cheshvan,
                HCalendarMonths::kislev,
                HCalendarMonths::tevet,
                HCalendarMonths::shevat,
                HCalendarMonths::adar,
                HCalendarMonths::nissan,
                HCalendarMonths::iyar,
                HCalendarMonths::sivan,
                HCalendarMonths::tammuz,
                HCalendarMonths::av,
                HCalendarMonths::elul,
            ];

            $lowercase = [
                HCalendarMonths::tishrei_lower,
                HCalendarMonths::cheshvan_lower,
                HCalendarMonths::kislev_lower,
                HCalendarMonths::tevet_lower,
                HCalendarMonths::shevat_lower,
                HCalendarMonths::adar_lower,
                HCalendarMonths::nissan_lower,
                HCalendarMonths::iyar_lower,
                HCalendarMonths::sivan_lower,
                HCalendarMonths::tammuz_lower,
                HCalendarMonths::av_lower,
                HCalendarMonths::elul_lower,
            ];

            expect(count($capitalized))->toBe(12);
            expect(count($lowercase))->toBe(12);
        });
    });
});
