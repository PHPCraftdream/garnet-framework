<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarBase;
use PHPCraftdream\Garnet\Kernel\Core\HCalendar\SlotDateFilter;

describe('SlotDateFilter bug regressions', function (): void {
    // ── Bug #1: Sukkot chol hamoed 17-20 Tishrei wrongly marked as erev_yom_tov ──

    describe('Bug #1: Sukkot chol hamoed (17-20 Tishrei) must NOT be erev_yom_tov', function (): void {
        it('17-20 Tishrei are not restricted as erev_yom_tov, but 21 is erev_yom_tov and 22 is yom_tov', function (): void {
            // Scan October–November across years 2024–2029 to find 17–22 Tishrei
            $yearsToCheck = [
                '2024-10-01' => '2024-11-15',
                '2025-10-01' => '2025-11-15',
                '2026-09-15' => '2026-11-15',
                '2027-09-15' => '2027-11-15',
                '2028-09-15' => '2028-11-15',
                '2029-09-15' => '2029-11-15',
            ];

            $found17to20 = [];
            $found21 = [];
            $found22 = [];

            foreach ($yearsToCheck as $startStr => $endStr) {
                $dt = DateTime::createFromFormat('Y-m-d', $startStr);
                $end = DateTime::createFromFormat('Y-m-d', $endStr);

                while ($dt <= $end) {
                    $h = HCalendarBase::fromDateTime($dt);

                    // Month 1 = Tishrei
                    if ($h->getHMonthNum() === 1) {
                        $day = $h->getHDay();

                        if ($day >= 17 && $day <= 22) {
                            $reason = SlotDateFilter::getRestrictionReason($h);
                            $key = $h->getHYear() . '-' . $day;

                            if ($day >= 17 && $day <= 20) {
                                $found17to20[$key] = $reason;
                            } elseif ($day === 21) {
                                $found21[$key] = $reason;
                            } elseif ($day === 22) {
                                $found22[$key] = $reason;
                            }
                        }
                    }
                    $dt->modify('+1 day');
                }
            }

            // Must have found some days in each group
            expect(count($found17to20))->toBeGreaterThan(0);
            expect(count($found21))->toBeGreaterThan(0);
            expect(count($found22))->toBeGreaterThan(0);

            // 17-20 Tishrei: chol hamoed — must NOT be erev_yom_tov
            foreach ($found17to20 as $key => $reason) {
                $code = $reason['code'] ?? null;
                expect($code)->not->toBe('erev_yom_tov',
                    "{$key} Tishrei (chol hamoed Sukkot) must NOT be erev_yom_tov, got: " . json_encode($reason)
                );
            }

            // 21 Tishrei (Hoshana Rabbah) IS erev_yom_tov (before Shmini Atzeret on 22)
            foreach ($found21 as $key => $reason) {
                // Skip if it's Shabbat or erev_shabbat (weekday override)
                if (in_array($reason['code'] ?? null, ['shabbat', 'erev_shabbat'], true)) {
                    continue;
                }
                expect($reason)->toBe(['code' => 'erev_yom_tov', 'name' => null],
                    "{$key} (21 Tishrei, Hoshana Rabbah) should be erev_yom_tov"
                );
            }

            // 22 Tishrei (Shmini Atzeret) IS yom_tov
            foreach ($found22 as $key => $reason) {
                if (in_array($reason['code'] ?? null, ['shabbat', 'erev_shabbat'], true)) {
                    continue;
                }
                expect($reason['code'] ?? null)->toBe('yom_tov',
                    "{$key} (22 Tishrei, Shmini Atzeret) should be yom_tov"
                );
            }
        });
    });

    // ── Bug #2: Pesach 20 Nissan NOT marked as erev_yom_tov ──

    describe('Bug #2: Pesach 20 Nissan must be erev_yom_tov', function (): void {
        it('20 Nissan is erev_yom_tov (before Pesach VII on 21 Nissan), and 21 Nissan is yom_tov', function (): void {
            // Scan March–May across years 2025–2030 to find 20–21 Nissan
            $yearsToCheck = [
                '2025-03-01' => '2025-05-15',
                '2026-03-01' => '2026-05-15',
                '2027-03-01' => '2027-05-15',
                '2028-03-01' => '2028-05-15',
                '2029-03-01' => '2029-05-15',
                '2030-03-01' => '2030-05-15',
            ];

            $found20 = [];
            $found21 = [];

            foreach ($yearsToCheck as $startStr => $endStr) {
                $dt = DateTime::createFromFormat('Y-m-d', $startStr);
                $end = DateTime::createFromFormat('Y-m-d', $endStr);

                while ($dt <= $end) {
                    $h = HCalendarBase::fromDateTime($dt);

                    // Month 8 = Nissan
                    if ($h->getHMonthNum() === 8) {
                        $day = $h->getHDay();

                        if ($day === 20 || $day === 21) {
                            $reason = SlotDateFilter::getRestrictionReason($h);
                            $key = $h->getHYear() . '-' . $day;

                            if ($day === 20) {
                                $found20[$key] = $reason;
                            } else {
                                $found21[$key] = $reason;
                            }
                        }
                    }
                    $dt->modify('+1 day');
                }
            }

            expect(count($found20))->toBeGreaterThan(0);
            expect(count($found21))->toBeGreaterThan(0);

            // 20 Nissan must be erev_yom_tov (before Pesach VII on 21 Nissan)
            foreach ($found20 as $key => $reason) {
                // Skip when it falls on Shabbat or erev_shabbat
                if (in_array($reason['code'] ?? null, ['shabbat', 'erev_shabbat'], true)) {
                    continue;
                }
                expect($reason)->toBe(['code' => 'erev_yom_tov', 'name' => null],
                    "{$key} (20 Nissan) should be erev_yom_tov, got: " . json_encode($reason)
                );
            }

            // 21 Nissan (Pesach VII) must be yom_tov
            foreach ($found21 as $key => $reason) {
                if (in_array($reason['code'] ?? null, ['shabbat', 'erev_shabbat'], true)) {
                    continue;
                }
                expect($reason['code'] ?? null)->toBe('yom_tov',
                    "{$key} (21 Nissan, Pesach VII) should be yom_tov"
                );
            }
        });
    });
});
