<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L0_Core\HCalendar\Spec {
    use DateInterval;
    use DateTime;
    use Exception;
    use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarBase;
    use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarGauss;
    use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarMonths;
    use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarTools;

    describe('HCalendar', function (): void {
        it('HCalendar 1', function (): void {
            $date = new DateTime();

            $interval = DateInterval::createFromDateString('1 year');
            $date->sub($interval);

            // Test 1 year of bidirectional conversion (sampled every week)
            for ($i = 0; $i < 365; $i += 7) {
                if ($i > 0) {
                    $interval = DateInterval::createFromDateString('7 days');
                    $date->add($interval);
                }
                $hDate = HCalendarBase::fromDateTime($date);
                $back = HCalendarBase::fromStrMonthHDate(
                    $hDate->getHYear(),
                    $hDate->getHMonthName(),
                    $hDate->getHDay()
                );

                $from = $date->format('Y-m-d');
                $to = $back->toStr();

                if ($from !== $to) {
                    throw new Exception("fail {$from} !== {$to}");
                }
            }
        });

        it('HCalendar 2', function (): void {
            $date = DateTime::createFromFormat('Y-m-d', '2024-04-03');
            $hDate = HCalendarBase::fromDateTime($date);
            expect($hDate->isLeapHYear())->toBe(true);
            expect($hDate->getHMonthName())->toBe('Adar-II');
            expect($hDate->toStrH())->toBe('24 Adar-II 5784');

            $date = DateTime::createFromFormat('Y-m-d', '2023-04-03');
            $hDate = HCalendarBase::fromDateTime($date);
            expect($hDate->isLeapHYear())->toBe(false);
            expect($hDate->getHMonthName())->toBe('Nissan');
            expect($hDate->toStrH())->toBe('12 Nissan 5783');
        });

        it('HCalendar 3', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(3759, HCalendarMonths::cheshvan, 1);
            expect($hDate->toStrH())->toBe('1 Cheshvan 3759');
            expect($hDate->toStr())->toBe('-0002-10-08');

            $hDate = HCalendarBase::fromStrMonthHDate(3760, HCalendarMonths::cheshvan, 1);
            expect($hDate->toStrH())->toBe('1 Cheshvan 3760');
            expect($hDate->toStr())->toBe('-0001-09-27');

            $hDate = HCalendarBase::fromStrMonthHDate(3761, HCalendarMonths::cheshvan, 1);
            expect($hDate->toStrH())->toBe('1 Cheshvan 3761');
            expect($hDate->toStr())->toBe('0000-10-16');

            $hDate = HCalendarBase::fromStrMonthHDate(3762, HCalendarMonths::cheshvan, 1);
            expect($hDate->toStrH())->toBe('1 Cheshvan 3762');
            expect($hDate->toStr())->toBe('0001-10-06');

            // ---------------------------------------------------------

            $hDate = HCalendarBase::fromStrMonthHDate(3759, HCalendarMonths::nissan, 16);
            expect($hDate->toStrH())->toBe('16 Nissan 3759');
            expect($hDate->toStr())->toBe('-0001-03-19');

            $hDate = HCalendarBase::fromStrMonthHDate(3760, HCalendarMonths::nissan, 16);
            expect($hDate->toStrH())->toBe('16 Nissan 3760');
            expect($hDate->toStr())->toBe('0000-04-07');

            $hDate = HCalendarBase::fromStrMonthHDate(3761, HCalendarMonths::nissan, 16);
            expect($hDate->toStrH())->toBe('16 Nissan 3761');
            expect($hDate->toStr())->toBe('0001-03-28');

            $hDate = HCalendarBase::fromStrMonthHDate(3762, HCalendarMonths::nissan, 16);
            expect($hDate->toStrH())->toBe('16 Nissan 3762');
            expect($hDate->toStr())->toBe('0002-04-15');
        });

        it('HCalendar 4', function (): void {
            // Sample every 50th year for faster execution while still covering wide range
            // Also test leap year boundaries and known problem years
            $sampleYears = [];

            for ($year = 1; $year < 7001; $year += 50) {
                $sampleYears[] = $year;
            }
            // Test leap year cycles (19 year Metonic cycle)
            $leapCycleYears = [3, 6, 8, 11, 14, 17, 19];

            foreach ($leapCycleYears as $cycle) {
                for ($base = 5700; $base < 5800; $base += 19) {
                    $sampleYears[] = $base + $cycle;
                }
            }
            // Test edge cases around year 1
            $sampleYears[] = 1;
            $sampleYears[] = 2;
            $sampleYears[] = 3;
            sort($sampleYears);
            $sampleYears = array_unique($sampleYears);

            foreach ($sampleYears as $year) {
                $calendar = join(':', HCalendarGauss::getHMonthsByYear($year));
                $gauss = join(':', HCalendarTools::getHMonthsByYear($year));

                if ($calendar !== $gauss) {
                    $lengthG = HCalendarGauss::getHYearLength($year);
                    [$startJd, $endJd, $lengthT, $isLeap] = HCalendarTools::getHYearInfo($year);

                    echo PHP_EOL, $year, ": {$lengthG} <> {$lengthT}", PHP_EOL;
                    echo $calendar, PHP_EOL;
                    echo $gauss, PHP_EOL;

                    throw new Exception('Fail on getHMonthsByYearLength: ' . $year);
                }
            }
        });

        it('Elul', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5783, HCalendarMonths::elul, 29);
            expect($hDate->getWeekDay())->toBe(6);
            expect($hDate->isPreTov())->toBe(true);
        });

        it('Tishrei', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::tishrei, 1);
            expect($hDate->getWeekDay())->toBe(7);
            expect($hDate->isTovOut())->toBe(true);
            expect($hDate->isTovIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::tishrei, 2);
            expect($hDate->getWeekDay())->toBe(1);
            expect($hDate->isTovOut())->toBe(true);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::tishrei, 3);
            expect($hDate->getWeekDay())->toBe(2);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5775, HCalendarMonths::tishrei, 3);
            expect($hDate->getWeekDay())->toBe(7);
            expect($hDate->isTsom())->toBe(false);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5775, HCalendarMonths::tishrei, 4);
            expect($hDate->getWeekDay())->toBe(1);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::tishrei, 4);
            expect($hDate->getWeekDay())->toBe(1);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::tishrei, 9);
            expect($hDate->getWeekDay())->toBe(6);
            expect($hDate->isPreTov())->toBe(true);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::tishrei, 10);
            expect($hDate->getWeekDay())->toBe(7);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::tishrei, 14);
            expect($hDate->isPreTov())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 15);
            expect($hDate->isShabbat())->toBe(true);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 16);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 17);
            expect($hDate->isMoedOut())->toBe(true);
            expect($hDate->isMoedIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 18);
            expect($hDate->isMoedOut())->toBe(true);
            expect($hDate->isMoedIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 19);
            expect($hDate->isMoedOut())->toBe(true);
            expect($hDate->isMoedIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 20);
            expect($hDate->isMoedOut())->toBe(true);
            expect($hDate->isMoedIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 21);
            expect($hDate->isMoedOut())->toBe(true);
            expect($hDate->isMoedIsrael())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 22);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tishrei, 23);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isTovOut())->toBe(true);
        });

        it('Kislev-Tevet', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 24);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 26);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 27);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 28);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 29);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::kislev, 30);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tevet, 1);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tevet, 2);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5767, HCalendarMonths::tevet, 3);
            expect($hDate->isCelebrateDay())->toBe(false);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::kislev, 24);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::kislev, 26);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::kislev, 27);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::kislev, 28);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::kislev, 29);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 1);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 2);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 3);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 4);
            expect($hDate->isCelebrateDay())->toBe(false);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 9);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::tevet, 10);
            expect($hDate->isTsom())->toBe(true);
        });

        it('Shevat-Adar', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::shevat, 14);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5768, HCalendarMonths::shevat, 15);
            expect($hDate->isCelebrateDay())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::adar2, 10);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::adar2, 11);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::adar2, 13);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5784, HCalendarMonths::adar2, 14);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5770, HCalendarMonths::adar, 10);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5770, HCalendarMonths::adar, 11);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5770, HCalendarMonths::adar, 13);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5770, HCalendarMonths::adar, 14);
            expect($hDate->isCelebrateDay())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar2, 10);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar2, 11);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar2, 13);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar2, 14);
            expect($hDate->isCelebrateDay())->toBe(true);

            // --

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar, 10);
            expect($hDate->isPreTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar, 11);
            expect($hDate->isTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar, 13);
            expect($hDate->isPreCelebrateDay())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar, 14);
            expect($hDate->isCelebrateDay())->toBe(false);

            // --

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar1, 10);
            expect($hDate->isPreTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar1, 11);
            expect($hDate->isTsom())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar1, 13);
            expect($hDate->isPreCelebrateDay())->toBe(false);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::adar1, 14);
            expect($hDate->isCelebrateDay())->toBe(false);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::adar, 12);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::adar, 13);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::adar, 14);
            expect($hDate->isCelebrateDay())->toBe(true);
        });

        it('Nissan', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 13);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 14);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreTov())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 15);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 16);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 17);
            expect($hDate->isTovOut())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 18);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 19);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 20);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 21);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5771, HCalendarMonths::nissan, 22);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isTovOut())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 13);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 14);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreTov())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 15);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 16);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 17);
            expect($hDate->isTovOut())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 18);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 19);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 20);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 21);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5772, HCalendarMonths::nissan, 22);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isTovOut())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 13);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 14);
            expect($hDate->isTsom())->toBe(true);
            expect($hDate->isPreTov())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 15);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 16);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 17);
            expect($hDate->isTovOut())->toBe(false);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 18);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 19);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 20);
            expect($hDate->isMoedIsrael())->toBe(true);
            expect($hDate->isMoedOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 21);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::nissan, 22);
            expect($hDate->isMoedIsrael())->toBe(false);
            expect($hDate->isMoedOut())->toBe(false);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isTovOut())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::nissan, 11);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::nissan, 12);
            expect($hDate->isTsom())->toBe(true);
        });

        it('Iar-Sivan-Tamuz-Av', function (): void {
            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::iyar, 17);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::iyar, 18);
            expect($hDate->isCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::iyar, 25);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::iyar, 26);
            expect($hDate->isCelebrateDay())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::sivan, 5);
            expect($hDate->isPreTov())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::sivan, 6);
            expect($hDate->isTovIsrael())->toBe(true);
            expect($hDate->isTovOut())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5781, HCalendarMonths::sivan, 7);
            expect($hDate->isTovIsrael())->toBe(false);
            expect($hDate->isTovOut())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5782, HCalendarMonths::tammuz, 17);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5782, HCalendarMonths::tammuz, 18);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::tammuz, 16);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::tammuz, 17);
            expect($hDate->isTsom())->toBe(true);

            // ---------

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::av, 8);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::av, 9);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5779, HCalendarMonths::av, 9);
            expect($hDate->isPreTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5779, HCalendarMonths::av, 10);
            expect($hDate->isTsom())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::av, 14);
            expect($hDate->isPreCelebrateDay())->toBe(true);

            $hDate = HCalendarBase::fromStrMonthHDate(5780, HCalendarMonths::av, 15);
            expect($hDate->isCelebrateDay())->toBe(true);
        });
    });
}
