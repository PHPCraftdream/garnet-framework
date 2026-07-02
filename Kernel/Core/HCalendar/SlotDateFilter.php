<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    use DateTime;

    class SlotDateFilter {
        /**
         * Analyze a date range and split into available and restricted dates.
         *
         * @return array{available: array<int, array{date: string, hebrewDate: string}>, restricted: array<int, array{date: string, hebrewDate: string, reason: array{code: string, name: string|null}}>}
         */
        public static function analyzeDateRange(string $startDate, string $endDate): array {
            $start = DateTime::createFromFormat('Y-m-d', $startDate);
            $end = DateTime::createFromFormat('Y-m-d', $endDate);

            if (!$start || !$end || $end < $start) {
                return ['available' => [], 'restricted' => []];
            }

            $available = [];
            $restricted = [];

            $hcal = HCalendarBase::fromDateTime($start);

            while ($hcal->toDate() <= $end) {
                $dateStr = $hcal->toStr();
                $hebrewDate = $hcal->toStrH();
                $reason = static::getRestrictionReason($hcal);

                $entry = [
                    'date' => $dateStr,
                    'hebrewDate' => $hebrewDate,
                ];

                if ($reason !== null) {
                    $entry['reason'] = $reason;
                    $restricted[] = $entry;
                } else {
                    $available[] = $entry;
                }

                $hcal->addDays(1);
            }

            return ['available' => $available, 'restricted' => $restricted];
        }

        /**
         * Get the restriction reason for a given day as a structured array.
         *
         * @return array{code: string, name: string|null}|null
         */
        public static function getRestrictionReason(HCalendarBase $day): ?array {
            if ($day->isShabbat()) {
                return ['code' => 'shabbat', 'name' => null];
            }

            if ($day->isSheshi()) {
                return ['code' => 'erev_shabbat', 'name' => null];
            }

            if ($day->isTovIsrael()) {
                $items = array_merge($day->getCommonItems(), $day->getIsraelItems());
                // Filter out 'Shabbat' from items — it's handled by isShabbat() above
                $items = array_values(array_filter($items, fn (string $item) => $item !== HCalendarDays::iom_Shabbat));
                $name = $items[0] ?? null;

                return ['code' => 'yom_tov', 'name' => $name];
            }

            if ($day->isPreTov()) {
                return ['code' => 'erev_yom_tov', 'name' => null];
            }

            if ($day->isTsom()) {
                $items = $day->getCommonItems();
                $items = array_values(array_filter($items, fn (string $item) => $item !== HCalendarDays::iom_Shabbat));
                $name = $items[0] ?? null;

                return ['code' => 'fast', 'name' => $name];
            }

            if ($day->isPreTsom()) {
                return ['code' => 'erev_fast', 'name' => null];
            }
            $hDay = $day->getHDay();

            if ($hDay === 30 || $hDay === 1) {
                return ['code' => 'rosh_chodesh', 'name' => null];
            }

            if ($hDay === 29) {
                return ['code' => 'erev_rosh_chodesh', 'name' => null];
            }

            return null;
        }

        /**
         * Distribute N dates spaced by 7/frequency days, skipping restricted dates.
         *
         * @return array<int, array{date: string, hebrewDate: string}>
         */
        public static function distributeByFrequency(string $startDate, int $count, int $frequency): array {
            if ($count <= 0 || $frequency <= 0) {
                return [];
            }

            $dayGap = max(1, (int)round(7 / $frequency));
            $current = DateTime::createFromFormat('Y-m-d', $startDate);

            if (!$current) {
                return [];
            }

            $result = [];
            $maxIterations = $count * $dayGap * 4;
            $iterations = 0;

            while (count($result) < $count && $iterations < $maxIterations) {
                $iterations++;
                $hcal = HCalendarBase::fromDateTime($current);
                $reason = static::getRestrictionReason($hcal);

                if ($reason === null) {
                    $result[] = [
                        'date' => $hcal->toStr(),
                        'hebrewDate' => $hcal->toStrH(),
                    ];
                    $current->modify("+{$dayGap} days");
                } else {
                    $current->modify('+1 day');
                }
            }

            return $result;
        }

        /**
         * Pick N evenly-spaced dates from a pre-filtered list.
         *
         * @param array<int, array{date: string, hebrewDate: string}> $availableDates
         * @return array<int, array{date: string, hebrewDate: string}>
         */
        public static function distributeSlots(array $availableDates, int $count): array {
            $total = count($availableDates);

            if ($total === 0) {
                return [];
            }

            if ($count >= $total) {
                return $availableDates;
            }

            if ($count === 1) {
                return [$availableDates[(int)floor($total / 2)]];
            }

            $result = [];

            for ($i = 0; $i < $count; $i++) {
                $index = (int)round($i * ($total - 1) / ($count - 1));
                $result[] = $availableDates[$index];
            }

            return $result;
        }
    }
}
