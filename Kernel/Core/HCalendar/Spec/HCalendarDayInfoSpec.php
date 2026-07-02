<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar\Spec;

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarBase;
use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarDayInfo;

class TestHCalendarBase extends HCalendarBase {
    use HCalendarDayInfo;

    public int $weekDay = 1;

    public int $hYear = 5784;

    public int $hMonthNum = 1;

    public int $hDay = 1;

    public bool $isLeapHYear = false;

    public function __construct() {
        // Initialize parent properties
    }

    public function setDayInfoForTest(): void {
        static::setDayInfo($this);
    }
}

describe('HCalendarDayInfo', function (): void {
    describe('Shabbat detection', function (): void {
        it('returns false for weekday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 3; // Tuesday
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->isShabbat())->toBe(false);
        });

        it('returns true for Saturday (weekDay 7)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7; // Saturday
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->isShabbat())->toBe(true);
        });
    });

    describe('Sheshi detection', function (): void {
        it('returns true for Friday (weekDay 6)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 6;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->isSheshi())->toBe(true);
        });

        it('returns false for other days', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 3;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->isSheshi())->toBe(false);
        });
    });

    describe('Rosh Hashana', function (): void {
        it('detects Rosh Hashana day 1', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });

        it('detects Rosh Hashana day 2', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 2;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });
    });

    describe('Yom Kippur', function (): void {
        it('detects Yom Kippur (10 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 10;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
            expect($cal->isTsom())->toBe(true);
        });
    });

    describe('Items arrays', function (): void {
        it('returns empty arrays initially', function (): void {
            $cal = new TestHCalendarBase();

            expect($cal->getIsraelItems())->toBe([]);
            expect($cal->getOutItems())->toBe([]);
            expect($cal->getCommonItems())->toBe([]);
        });

        it('populates items after setDayInfo', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            expect($cal->getCommonItems())->not->toBeEmpty();
            expect($cal->getIsraelItems())->not->toBeEmpty();
            expect($cal->getOutItems())->not->toBeEmpty();
        });
    });

    describe('Pre-holiday detection', function (): void {
        it('detects erev Yom Kippur (9 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 9;

            $cal->setDayInfoForTest();

            expect($cal->isPreTov())->toBe(true);
            expect($cal->isPreTsom())->toBe(true);
        });

        it('detects erev Sukkot (14 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 14;

            $cal->setDayInfoForTest();

            expect($cal->isPreTov())->toBe(true);
        });
    });

    describe('Tzom Gedaliah', function (): void {
        it('fasts on 3 Tishrei when not Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 3;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('delays fast to 4 Tishrei when 3 Tishrei is Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 3;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(false);
            expect($cal->isPreTsom())->toBe(true);

            $cal->hDay = 4;
            $cal->weekDay = 1;
            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('sets pre-fast on Rosh Hashana day 2 when not Friday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 3;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 2;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });

        it('does not set pre-fast on Rosh Hashana day 2 when Friday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 6;
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 2;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(false);
        });
    });

    describe('Sukkot', function (): void {
        it('detects Sukkot (15 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 15;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });

        it('shows different observance on day 2 (Israel vs Diaspora)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 16;

            $cal->setDayInfoForTest();

            expect($cal->isMoedIsrael())->toBe(true);
            expect($cal->isTovOut())->toBe(true);
        });

        it('detects hol ha-moed Sukkot', function (): void {
            foreach ([17, 18, 19, 20, 21] as $day) {
                $cal = new TestHCalendarBase();
                $cal->hYear = 5784;
                $cal->hMonthNum = 1;
                $cal->hDay = $day;

                $cal->setDayInfoForTest();

                expect($cal->isMoedIsrael())->toBe(true);
                expect($cal->isMoedOut())->toBe(true);
            }
        });

        it('detects Hoshana Raba on 21 Tishrei', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 21;

            $cal->setDayInfoForTest();

            $items = $cal->getCommonItems();
            expect(in_array('Oshana raba', $items, true))->toBe(true);
        });
    });

    describe('Shmini Atzeret and Simchat Torah', function (): void {
        it('detects Shmini Atzeret (22 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 22;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
            expect($cal->isPreTov())->toBe(true);
        });

        it('detects Simchat Torah only in Diaspora (23 Tishrei)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 23;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(false);
        });
    });

    describe('Hanuka', function (): void {
        it('detects pre-Hanuka (24 Kislev)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 3;
            $cal->hDay = 24;

            $cal->setDayInfoForTest();

            expect($cal->isPreCelebrateDay())->toBe(true);
        });

        it('detects Hanuka day 1 (25 Kislev)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 3;
            $cal->hDay = 25;

            $cal->setDayInfoForTest();

            expect($cal->isCelebrateDay())->toBe(true);
        });

        it('detects all Hanuka days in Kislev', function (): void {
            for ($i = 1; $i <= 6; $i++) {
                $cal = new TestHCalendarBase();
                $cal->hYear = 5784;
                $cal->hMonthNum = 3;
                $cal->hDay = 24 + $i;

                $cal->setDayInfoForTest();

                expect($cal->isCelebrateDay())->toBe(true);
            }
        });
    });

    describe('Taanit Esther and Purim', function (): void {
        it('sets pre-fast on 10 Adar when Thursday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 4;
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 10;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });

        it('fasts on 11 Adar when Friday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 5;
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 11;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('sets pre-fast on 12 Adar when not Saturday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 12;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });

        it('fasts on 13 Adar when not Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 3;
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 13;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('does not fast on 13 Adar when Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7;
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 13;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(false);
        });

        it('detects Purim (14 Adar)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 7;
            $cal->hDay = 14;

            $cal->setDayInfoForTest();

            expect($cal->isCelebrateDay())->toBe(true);
        });
    });

    describe('Pesach', function (): void {
        it('detects Pesach day 1 (15 Nissan)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 8;
            $cal->hDay = 15;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });

        it('shows different observance on day 2', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 8;
            $cal->hDay = 16;

            $cal->setDayInfoForTest();

            expect($cal->isMoedIsrael())->toBe(true);
            expect($cal->isTovOut())->toBe(true);
        });

        it('detects hol ha-moed Pesach', function (): void {
            foreach ([17, 18, 19, 20] as $day) {
                $cal = new TestHCalendarBase();
                $cal->hYear = 5784;
                $cal->hMonthNum = 8;
                $cal->hDay = $day;

                $cal->setDayInfoForTest();

                expect($cal->isMoedIsrael())->toBe(true);
                expect($cal->isMoedOut())->toBe(true);
            }
        });

        it('detects Pesach day 7 (21 Nissan)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 8;
            $cal->hDay = 21;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });

        it('detects Pesach day 8 only in Diaspora (22 Nissan)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 8;
            $cal->hDay = 22;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(false);
        });

        it('sets pre-fast on 13 Nissan when not Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 3;
            $cal->hYear = 5784;
            $cal->hMonthNum = 8;
            $cal->hDay = 13;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });
    });

    describe('Shavuot', function (): void {
        it('sets pre-tov on 5 Sivan', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 10;
            $cal->hDay = 5;

            $cal->setDayInfoForTest();

            expect($cal->isPreTov())->toBe(true);
        });

        it('detects Shavuot (6 Sivan)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 10;
            $cal->hDay = 6;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(true);
        });

        it('detects Shavuot day 2 only in Diaspora (7 Sivan)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 10;
            $cal->hDay = 7;

            $cal->setDayInfoForTest();

            expect($cal->isTovOut())->toBe(true);
            expect($cal->isTovIsrael())->toBe(false);
        });
    });

    describe('Shivah Asar ba-Tammuz', function (): void {
        it('sets pre-fast on 16 Tamuz when not Friday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 11;
            $cal->hDay = 16;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });

        it('fasts on 17 Tamuz when not Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 2;
            $cal->hYear = 5784;
            $cal->hMonthNum = 11;
            $cal->hDay = 17;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('does not fast on 17 Tamuz when Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7;
            $cal->hYear = 5784;
            $cal->hMonthNum = 11;
            $cal->hDay = 17;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(false);
            expect($cal->isPreTsom())->toBe(true);
        });

        it('delays fast to 18 Tamuz when 17 Tamuz is Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 11;
            $cal->hDay = 18;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });
    });

    describe('Tisha BeAv', function (): void {
        it('sets pre-fast on 8 Av when not Friday', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 12;
            $cal->hDay = 8;

            $cal->setDayInfoForTest();

            expect($cal->isPreTsom())->toBe(true);
        });

        it('fasts on 9 Av when not Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 2;
            $cal->hYear = 5784;
            $cal->hMonthNum = 12;
            $cal->hDay = 9;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });

        it('does not fast on 9 Av when Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 7;
            $cal->hYear = 5784;
            $cal->hMonthNum = 12;
            $cal->hDay = 9;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(false);
            expect($cal->isPreTsom())->toBe(true);
        });

        it('delays fast to 10 Av when 9 Av is Shabbat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->weekDay = 1;
            $cal->hYear = 5784;
            $cal->hMonthNum = 12;
            $cal->hDay = 10;

            $cal->setDayInfoForTest();

            expect($cal->isTsom())->toBe(true);
        });
    });

    describe('Other holidays', function (): void {
        it('detects Tu BeShvat (15 Shvat)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 5;
            $cal->hDay = 15;

            $cal->setDayInfoForTest();

            expect($cal->isCelebrateDay())->toBe(true);
        });

        it('sets pre-celebration on 14 Shvat', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 5;
            $cal->hDay = 14;

            $cal->setDayInfoForTest();

            expect($cal->isPreCelebrateDay())->toBe(true);
        });

        it('detects Pesach Sheni (14 Iyar)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 9;
            $cal->hDay = 14;

            $cal->setDayInfoForTest();

            $items = $cal->getCommonItems();
            expect(in_array('Pesach Sheni', $items, true))->toBe(true);
        });

        it('detects Lag BaOmer (18 Iyar)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 9;
            $cal->hDay = 18;

            $cal->setDayInfoForTest();

            expect($cal->isCelebrateDay())->toBe(true);
        });

        it('detects Tu BeAv (15 Av)', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 12;
            $cal->hDay = 15;

            $cal->setDayInfoForTest();

            expect($cal->isCelebrateDay())->toBe(true);
        });
    });

    describe('Rosh Hodesh', function (): void {
        it('adds Rosh Hodesh on day 1', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 1;

            $cal->setDayInfoForTest();

            $items = $cal->getCommonItems();
            expect(in_array('Rosh hodesh', $items, true))->toBe(true);
        });

        it('adds Rosh Hodesh I on day 30', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 1;
            $cal->hDay = 30;

            $cal->setDayInfoForTest();

            $items = $cal->getCommonItems();
            expect(in_array('Rosh hodesh I', $items, true))->toBe(true);
        });
    });

    describe('Erev Rosh Hashana', function (): void {
        it('sets pre-tov on 29 Elul', function (): void {
            $cal = new TestHCalendarBase();
            $cal->hYear = 5784;
            $cal->hMonthNum = 13;
            $cal->hDay = 29;

            $cal->setDayInfoForTest();

            expect($cal->isPreTov())->toBe(true);
        });
    });
});
