<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarDays;

describe('HCalendarDays', function (): void {
    describe('Major holidays constants', function (): void {
        it('defines Shabbat and high holidays', function (): void {
            expect(HCalendarDays::iom_Shabbat)->toBe('Shabbat');
            expect(HCalendarDays::iom_RoshHaShana1)->toBe('Rosh ha-shana-I');
            expect(HCalendarDays::iom_RoshHaShana2)->toBe('Rosh ha-shana-II');
            expect(HCalendarDays::iom_YomKippur)->toBe('Yom Kippur');
        });

        it('defines Sukkot holidays', function (): void {
            expect(HCalendarDays::iom_Sukkot)->toBe('Sukkot');
            expect(HCalendarDays::iom_SukkotHa)->toBe('Sukkot hol-ha-moed');
            expect(HCalendarDays::iom_OshanaRaba)->toBe('Oshana raba');
            expect(HCalendarDays::iom_SminiAtseret)->toBe('Smini Atseret');
            expect(HCalendarDays::iom_SminiAtseret2)->toBe('Smini Atseret II');
            expect(HCalendarDays::iom_SimkhatTotrah)->toBe('Simkhat Totrah');
        });

        it('defines Pesach holidays', function (): void {
            expect(HCalendarDays::iom_TaanitBkhorim)->toBe('Taanit Bkhorim');
            expect(HCalendarDays::iom_Pesach)->toBe('Pesach');
            expect(HCalendarDays::iom_PesachMoed)->toBe('Pesach hol-ha-moed');
            expect(HCalendarDays::iom_PesachSheni)->toBe('Pesach Sheni');
        });

        it('defines Shavuot', function (): void {
            expect(HCalendarDays::iom_Shavuot)->toBe('Shavuot');
        });
    });

    describe('Hanuka', function (): void {
        it('defines all 8 days of Hanuka', function (): void {
            for ($i = 1; $i <= 8; $i++) {
                expect(constant(HCalendarDays::class . "::iom_Hanuka{$i}"))->toBe("Hanuka {$i}");
            }
        });
    });

    describe('Minor holidays and observances', function (): void {
        it('defines Rosh Hodesh', function (): void {
            expect(HCalendarDays::iom_RoshHodesh)->toBe('Rosh hodesh');
            expect(HCalendarDays::iom_RoshHodesh1)->toBe('Rosh hodesh I');
            expect(HCalendarDays::iom_RoshHodesh2)->toBe('Rosh hodesh II');
        });

        it('defines minor holidays', function (): void {
            expect(HCalendarDays::iom_AsaraBaTevet)->toBe('Asarah Be-Tevet');
            expect(HCalendarDays::iom_TuBeShvat)->toBe('Tu Be-Shvat');
            expect(HCalendarDays::iom_TaanitEsther)->toBe('Taanit Esther');
            expect(HCalendarDays::iom_LagBaOmer)->toBe('Lag ba-Omer');
            expect(HCalendarDays::iom_SalvationAndLiberation)->toBe('Day of salvation and liberation');
        });

        it('defines Purim', function (): void {
            expect(HCalendarDays::iom_Purim)->toBe('Purim');
            expect(HCalendarDays::iom_ShushanPurim)->toBe('Shushan Purim');
            expect(HCalendarDays::iom_ShushanPurimMeshulash)->toBe('Shushan Purim Meshulash');
        });
    });

    describe('Summer fasts', function (): void {
        it('defines summer fasting days', function (): void {
            expect(HCalendarDays::iom_ShivahAsarBaTammuz)->toBe('Shivah Asar ba-Tammuz');
            expect(HCalendarDays::iom_TeshaBeAv)->toBe('Tesha be-Av');
            expect(HCalendarDays::iom_TuaBeAv)->toBe('Tu be-Av');
            expect(HCalendarDays::iom_TzomGedaliah)->toBe('Tzom Gedaliah');
        });
    });
});
