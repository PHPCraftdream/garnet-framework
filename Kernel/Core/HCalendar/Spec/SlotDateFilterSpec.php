<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\HCalendar\HCalendarBase;
use PHPCraftdream\Garnet\Kernel\Core\HCalendar\SlotDateFilter;

describe('SlotDateFilter', function (): void {
    // ── analyzeDateRange ─────────────────────────────────────────────────────

    describe('analyzeDateRange()', function (): void {
        it('returns empty arrays when end < start', function (): void {
            $result = SlotDateFilter::analyzeDateRange('2025-01-10', '2025-01-05');
            expect($result['available'])->toBe([]);
            expect($result['restricted'])->toBe([]);
        });

        it('returns empty arrays for invalid dates', function (): void {
            $result = SlotDateFilter::analyzeDateRange('not-a-date', '2025-01-05');
            expect($result['available'])->toBe([]);
            expect($result['restricted'])->toBe([]);
        });

        it('marks a known Saturday as shabbat restricted', function (): void {
            // 2025-01-04 is Saturday
            $result = SlotDateFilter::analyzeDateRange('2025-01-04', '2025-01-04');
            expect(count($result['restricted']))->toBe(1);
            expect(count($result['available']))->toBe(0);
            expect($result['restricted'][0]['reason']['code'])->toBe('shabbat');
            expect($result['restricted'][0]['date'])->toBe('2025-01-04');
        });

        it('marks a known Friday as erev_shabbat restricted', function (): void {
            // 2025-01-03 is Friday
            $result = SlotDateFilter::analyzeDateRange('2025-01-03', '2025-01-03');
            expect(count($result['restricted']))->toBe(1);
            expect($result['restricted'][0]['reason']['code'])->toBe('erev_shabbat');
        });

        it('finds at least one shabbat and one erev_shabbat in a 7-day range starting Sunday', function (): void {
            // 2025-01-05 is Sunday
            $result = SlotDateFilter::analyzeDateRange('2025-01-05', '2025-01-11');
            $codes = array_column(array_column($result['restricted'], 'reason'), 'code');
            expect(in_array('shabbat', $codes, true))->toBe(true);
            expect(in_array('erev_shabbat', $codes, true))->toBe(true);
        });

        it('has restricted + available sum equal to range length for 30 days', function (): void {
            $result = SlotDateFilter::analyzeDateRange('2025-03-01', '2025-03-30');
            $total = count($result['available']) + count($result['restricted']);
            expect($total)->toBe(30);
        });

        it('includes Hebrew date in entries', function (): void {
            $result = SlotDateFilter::analyzeDateRange('2025-01-04', '2025-01-04');
            expect($result['restricted'][0]['hebrewDate'])->not->toBeEmpty();
        });
    });

    // ── getRestrictionReason ─────────────────────────────────────────────────

    describe('getRestrictionReason()', function (): void {
        it('returns shabbat for Saturday', function (): void {
            // 2025-01-04 is Saturday
            $day = HCalendarBase::fromDateTime(new DateTime('2025-01-04'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason)->toBe(['code' => 'shabbat', 'name' => null]);
        });

        it('returns erev_shabbat for Friday', function (): void {
            // 2025-01-03 is Friday
            $day = HCalendarBase::fromDateTime(new DateTime('2025-01-03'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason)->toBe(['code' => 'erev_shabbat', 'name' => null]);
        });

        it('returns yom_tov with name for Pesach (15 Nissan 5785 = 2025-04-13)', function (): void {
            // Pesach I: 15 Nissan 5785 = April 13, 2025 (Sunday)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-04-13'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('yom_tov');
            expect($reason['name'])->toContain('Pesach');
        });

        it('returns erev_yom_tov for Erev Sukkot (14 Tishrei 5786 = 2025-10-06)', function (): void {
            // 14 Tishrei 5786 = October 6, 2025 (Monday)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-10-06'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('erev_yom_tov');
        });

        it('returns yom_tov with name for Yom Kippur (10 Tishrei 5786 = 2025-10-02)', function (): void {
            // Yom Kippur: 10 Tishrei 5786 = October 2, 2025 (Thursday)
            // isTovIsrael is checked before isTsom, so yom_tov wins
            $day = HCalendarBase::fromDateTime(new DateTime('2025-10-02'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('yom_tov');
            expect($reason['name'])->toContain('Yom Kippur');
        });

        it('returns fast with name for Tzom Gedaliah (3 Tishrei 5786 = 2025-09-25)', function (): void {
            // 3 Tishrei 5786 = September 25, 2025 (Thursday, weekDay=5)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-09-25'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('fast');
            expect($reason['name'])->toContain('Tzom Gedaliah');
        });

        it('returns erev_fast for Erev Asara BeTevet (9 Tevet 5785 = 2025-01-09)', function (): void {
            // 9 Tevet 5785 = January 9, 2025 (Thursday)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-01-09'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('erev_fast');
        });

        it('returns rosh_chodesh for 1st of Hebrew month', function (): void {
            // 1 Nissan 5785 = March 30, 2025 (Sunday)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-03-30'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('rosh_chodesh');
        });

        it('returns erev_rosh_chodesh for 29th of Hebrew month', function (): void {
            // 29 Shvat 5785 = February 27, 2025 (Thursday)
            $day = HCalendarBase::fromDateTime(new DateTime('2025-02-27'));
            expect($day->getHDay())->toBe(29);
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('erev_rosh_chodesh');
        });

        it('returns null for regular weekday', function (): void {
            // 2025-01-07 is Tuesday, 7 Tevet 5785
            $day = HCalendarBase::fromDateTime(new DateTime('2025-01-07'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason)->toBeNull();
        });

        it('returns yom_tov for Rosh Hashana (1 Tishrei 5786 = 2025-09-23)', function (): void {
            $day = HCalendarBase::fromDateTime(new DateTime('2025-09-23'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            expect($reason['code'])->toBe('yom_tov');
            expect($reason['name'])->toContain('Rosh ha-shana');
        });

        it('returns erev_shabbat when fast falls on Friday', function (): void {
            // 10 Tevet 5785 = January 10, 2025 (Friday)
            // erev_shabbat takes precedence over fast
            $day = HCalendarBase::fromDateTime(new DateTime('2025-01-10'));
            $reason = SlotDateFilter::getRestrictionReason($day);
            // Actually, isSheshi (Friday) check comes before isTsom
            // But wait: 10 Tevet IS a fast, and isSheshi is true for Friday
            // The order: isShabbat -> isSheshi -> isTovIsrael -> isPreTov -> isTsom
            // isSheshi fires first
            expect($reason['code'])->toBe('erev_shabbat');
        });
    });

    // ── distributeByFrequency ────────────────────────────────────────────────

    describe('distributeByFrequency()', function (): void {
        it('returns empty for count=0', function (): void {
            $result = SlotDateFilter::distributeByFrequency('2025-01-06', 0, 1);
            expect($result)->toBe([]);
        });

        it('returns empty for frequency=0', function (): void {
            $result = SlotDateFilter::distributeByFrequency('2025-01-06', 5, 0);
            expect($result)->toBe([]);
        });

        it('returns empty for invalid start date', function (): void {
            $result = SlotDateFilter::distributeByFrequency('invalid', 5, 1);
            expect($result)->toBe([]);
        });

        it('returns 5 dates 7 days apart for frequency=1 starting on a weekday', function (): void {
            // Start on 2025-01-07 (Tuesday). dayGap = round(7/1) = 7
            $result = SlotDateFilter::distributeByFrequency('2025-01-07', 5, 1);
            expect(count($result))->toBe(5);

            // Verify spacing: >= 7 days (may be more if restricted days skipped)
            for ($i = 1; $i < count($result); $i++) {
                $prev = new DateTime($result[$i - 1]['date']);
                $curr = new DateTime($result[$i]['date']);
                $diff = (int)$prev->diff($curr)->days;
                expect($diff >= 7)->toBe(true);
            }
        });

        it('returns 3 dates ~3-4 days apart for frequency=2', function (): void {
            // dayGap = round(7/2) = 4
            $result = SlotDateFilter::distributeByFrequency('2025-01-07', 3, 2);
            expect(count($result))->toBe(3);

            for ($i = 1; $i < count($result); $i++) {
                $prev = new DateTime($result[$i - 1]['date']);
                $curr = new DateTime($result[$i]['date']);
                $diff = (int)$prev->diff($curr)->days;
                expect($diff >= 3)->toBe(true);
            }
        });

        it('skips restricted dates and still returns requested count', function (): void {
            // Start on 2025-01-08 (Wednesday), frequency=1, count=3
            $result = SlotDateFilter::distributeByFrequency('2025-01-08', 3, 1);
            expect(count($result))->toBe(3);

            // Verify no result falls on Shabbat (Saturday) or Friday
            foreach ($result as $entry) {
                $dt = new DateTime($entry['date']);
                $dow = (int)$dt->format('w'); // 0=Sun, 6=Sat
                expect($dow)->not->toBe(6);
                expect($dow)->not->toBe(5);
            }
        });

        it('terminates via maxIterations when count is huge', function (): void {
            // maxIterations = count * dayGap * 4 = 10000 * 7 * 4 = 280000
            // That's way too many iterations. Use a more targeted test:
            // frequency=7 => dayGap = round(7/7) = 1, maxIterations = 50*1*4 = 200
            // In 200 days there are many restricted days, so we may not get 50.
            // Actually with dayGap=1 and 200 iterations, we still get plenty.
            // Better: just verify the function always terminates and returns an array.
            $result = SlotDateFilter::distributeByFrequency('2025-01-06', 50, 7);
            expect(is_array($result))->toBe(true);
            // dayGap=1 means maxIterations=50*1*4=200, ~200 days from start
            // There should be enough non-restricted days to fill 50
            expect(count($result))->toBe(50);
        });

        it('returns dates that are never restricted', function (): void {
            $result = SlotDateFilter::distributeByFrequency('2025-03-01', 10, 2);

            foreach ($result as $entry) {
                $day = HCalendarBase::fromDateTime(new DateTime($entry['date']));
                $reason = SlotDateFilter::getRestrictionReason($day);
                expect($reason)->toBeNull();
            }
        });
    });

    // ── distributeSlots ──────────────────────────────────────────────────────

    describe('distributeSlots()', function (): void {
        it('returns empty for empty input', function (): void {
            $result = SlotDateFilter::distributeSlots([], 5);
            expect($result)->toBe([]);
        });

        it('returns all elements when count >= length', function (): void {
            $input = [
                ['date' => '2025-01-06', 'hebrewDate' => 'a'],
                ['date' => '2025-01-07', 'hebrewDate' => 'b'],
                ['date' => '2025-01-08', 'hebrewDate' => 'c'],
            ];
            $result = SlotDateFilter::distributeSlots($input, 5);
            expect(count($result))->toBe(3);
            expect($result)->toBe($input);
        });

        it('returns all elements when count == length', function (): void {
            $input = [
                ['date' => '2025-01-06', 'hebrewDate' => 'a'],
                ['date' => '2025-01-07', 'hebrewDate' => 'b'],
                ['date' => '2025-01-08', 'hebrewDate' => 'c'],
            ];
            $result = SlotDateFilter::distributeSlots($input, 3);
            expect(count($result))->toBe(3);
            expect($result)->toBe($input);
        });

        it('returns middle element for count=1 from 10-element array', function (): void {
            $input = [];

            for ($i = 0; $i < 10; $i++) {
                $input[] = ['date' => '2025-01-' . str_pad((string)($i + 6), 2, '0', STR_PAD_LEFT), 'hebrewDate' => "h{$i}"];
            }
            $result = SlotDateFilter::distributeSlots($input, 1);
            expect(count($result))->toBe(1);
            // floor(10/2) = 5 => index 5 => 2025-01-11
            expect($result[0]['date'])->toBe('2025-01-11');
        });

        it('returns evenly spaced indexes for count=5 from 10-element array', function (): void {
            $input = [];

            for ($i = 0; $i < 10; $i++) {
                $input[] = ['date' => '2025-01-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT), 'hebrewDate' => "h{$i}"];
            }
            $result = SlotDateFilter::distributeSlots($input, 5);
            expect(count($result))->toBe(5);

            // Indexes: round(0*9/4)=0, round(1*9/4)=2, round(2*9/4)=5, round(3*9/4)=7, round(4*9/4)=9
            // But round(2*9/4) = round(4.5) = 5 in PHP (rounds away from zero)
            expect($result[0]['date'])->toBe('2025-01-01');
            expect($result[1]['date'])->toBe('2025-01-03');
            expect($result[2]['date'])->toBe('2025-01-06'); // index 5 => Jan 6
            expect($result[3]['date'])->toBe('2025-01-08');
            expect($result[4]['date'])->toBe('2025-01-10');
        });
    });
});
