<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    use Exception;
    use InvalidArgumentException;

    class HCalendarTools {
        // ##############################################################################################################
        protected static array $yearEdges = [];

        public static function getHYearInfo(int $year): array {
            if (!isset(static::$yearEdges[$year])) {
                $startJd = jewishtojd(1, 1, $year);
                // Month 13 (Adar II) only exists in leap years, so asking
                // jewishtojd() for "13/29" unconditionally relies on however
                // it happens to clamp an out-of-range month in a non-leap
                // year — found to differ across PHP versions (correct on
                // 8.1/8.2, degenerate on 8.3). The last day of year N is
                // always exactly one day before the first day of year N+1,
                // which sidesteps the question of whether month 13 exists
                // at all.
                $endJd = jewishtojd(1, 1, $year + 1) - 1;
                $length = ($endJd - $startJd) + 1;
                $isLeap = $length > 355;

                static::$yearEdges[$year] = [$startJd, $endJd, $length, $isLeap];
            }

            return static::$yearEdges[$year];
        }

        /**
         * @return array<int, int>
         */
        public static function getHMonthsByYearLength(int $length): array {
            switch ($length) {
                case 353:
                    return [30, 29, 29, 29, 30, 29, 0, 30, 29, 30, 29, 30, 29];

                case 354:
                    return [30, 29, 30, 29, 30, 29, 0, 30, 29, 30, 29, 30, 29];

                case 355:
                    return [30, 30, 30, 29, 30, 29, 0, 30, 29, 30, 29, 30, 29];

                case 383:
                    return [30, 29, 29, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29];

                case 384:
                    return [30, 29, 30, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29];

                case 385:
                    return [30, 30, 30, 29, 30, 30, 29, 30, 29, 30, 29, 30, 29];
            }

            throw new Exception('Wrong year days: ' . $length);
        }

        public static function getHMonthsByYear(int $year): array {
            [$startJd, $endJd, $length, $isLeap] = static::getHYearInfo($year);

            return HCalendarTools::getHMonthsByYearLength($length);
        }

        // ##############################################################################################################

        public static function getHMonthsByYearInd(int $length): int {
            switch ($length) {
                case 353:
                    return 1;

                case 354:
                    return 2;

                case 355:
                    return 3;

                case 383:
                    return 4;

                case 384:
                    return 5;

                case 385:
                    return 6;
            }

            throw new Exception('Wrong year days: ' . $length);
        }

        public static function getHYearInd(int $year): int {
            [$startJd, $endJd, $length, $isLeap] = static::getHYearInfo($year);

            return HCalendarTools::getHMonthsByYearInd($length);
        }

        // ##############################################################################################################

        public static function dateToStr(int $year, int $month, int $day): string {
            $year = str_pad($year . '', 4, '0', STR_PAD_LEFT);
            $month = str_pad($month . '', 2, '0', STR_PAD_LEFT);
            $day = str_pad($day . '', 2, '0', STR_PAD_LEFT);

            return "{$year}-{$month}-{$day}";
        }

        // ##############################################################################################################

        protected static array $monthsIndBack = [
            1 => [[HCalendarMonths::tishrei, HCalendarMonths::tishrei_lower], [HCalendarMonths::tishrei, HCalendarMonths::tishrei_lower]],
            2 => [[HCalendarMonths::cheshvan, HCalendarMonths::cheshvan_lower], [HCalendarMonths::cheshvan, HCalendarMonths::cheshvan_lower]],
            3 => [[HCalendarMonths::kislev, HCalendarMonths::kislev_lower], [HCalendarMonths::kislev, HCalendarMonths::kislev_lower]],
            4 => [[HCalendarMonths::tevet, HCalendarMonths::tevet_lower], [HCalendarMonths::tevet, HCalendarMonths::tevet_lower]],
            5 => [[HCalendarMonths::shevat, HCalendarMonths::shevat_lower], [HCalendarMonths::shevat, HCalendarMonths::shevat_lower]],
            6 => [[HCalendarMonths::adar, HCalendarMonths::adar_lower], [HCalendarMonths::adar1, HCalendarMonths::adar1_lower]],
            7 => [[HCalendarMonths::adar, HCalendarMonths::adar_lower], [HCalendarMonths::adar2, HCalendarMonths::adar2_lower]],
            8 => [[HCalendarMonths::nissan, HCalendarMonths::nissan_lower], [HCalendarMonths::nissan, HCalendarMonths::nissan_lower]],
            9 => [[HCalendarMonths::iyar, HCalendarMonths::iyar_lower], [HCalendarMonths::iyar, HCalendarMonths::iyar_lower]],
            10 => [[HCalendarMonths::sivan, HCalendarMonths::sivan_lower], [HCalendarMonths::sivan, HCalendarMonths::sivan_lower]],
            11 => [[HCalendarMonths::tammuz, HCalendarMonths::tammuz_lower], [HCalendarMonths::tammuz, HCalendarMonths::tammuz_lower]],
            12 => [[HCalendarMonths::av, HCalendarMonths::av_lower], [HCalendarMonths::av, HCalendarMonths::av_lower]],
            13 => [[HCalendarMonths::elul, HCalendarMonths::elul_lower], [HCalendarMonths::elul, HCalendarMonths::elul_lower]],
        ];

        public static function getMonthName(int $month, bool $isLeap): array {
            $info = static::$monthsIndBack[$month] ?? null;

            if ($info === null) {
                throw new InvalidArgumentException("Unknown month ind: {$month}");
            }

            $name = $info[$isLeap ? 1 : 0] ?? null;

            if ($name === null) {
                throw new InvalidArgumentException("Unknown month ind #1: {$month}");
            }

            return $name;
        }

        // ##############################################################################################################

        protected static array $monthsInd = [
            HCalendarMonths::tishrei_lower => [1, 1, HCalendarMonths::tishrei],
            HCalendarMonths::cheshvan_lower => [2, 2, HCalendarMonths::cheshvan],
            HCalendarMonths::kislev_lower => [3, 3, HCalendarMonths::kislev],
            HCalendarMonths::tevet_lower => [4, 4, HCalendarMonths::tevet],
            HCalendarMonths::shevat_lower => [5, 5, HCalendarMonths::shevat],
            HCalendarMonths::adar_lower => [6, 6, HCalendarMonths::adar],
            HCalendarMonths::adar1_lower => [6, 6, HCalendarMonths::adar1],
            HCalendarMonths::adar2_lower => [6, 7, HCalendarMonths::adar2],
            HCalendarMonths::nissan_lower => [8, 8, HCalendarMonths::nissan],
            HCalendarMonths::iyar_lower => [9, 9, HCalendarMonths::iyar],
            HCalendarMonths::sivan_lower => [10, 10, HCalendarMonths::sivan],
            HCalendarMonths::tammuz_lower => [11, 11, HCalendarMonths::tammuz],
            HCalendarMonths::av_lower => [12, 12, HCalendarMonths::av],
            HCalendarMonths::elul_lower => [13, 13, HCalendarMonths::elul],
        ];

        public static function getMonthIndInfoByName(string $name): array|null {
            $month = mb_strtolower(trim($name));
            $month = str_replace(['1', '2', ' '], ['i', 'ii', '-'], $month);

            return static::$monthsInd[$month] ?? null;
        }

        // ##############################################################################################################
    }
}
