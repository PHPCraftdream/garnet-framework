<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    class HCalendarGauss {
        public static function calculateGauss(int $year): int {
            $a = floor((12 * $year + 17) % 19);
            $b = floor($year % 4);
            $m = 32.044093161144 + 1.5542417966212 * $a + $b / 4.0 - 0.0031777940220923 * $year;

            if ($m < 0) {
                $m -= 1;
            }

            $pesachDate = (int)floor($m);

            if ($m < 0) {
                $m += 1;
            }

            $m -= $pesachDate;
            $c = (int)floor(($pesachDate + 3 * $year + 5 * $b + 5) % 7);

            if ($c === 0 && $a > 11 && $m >= 0.89772376543210) {
                $pesachDate += 1;
            } elseif ($c === 1 && $a > 6 && $m >= 0.63287037037037) {
                $pesachDate += 2;
            } elseif ($c === 2 || $c === 4 || $c === 6) {
                $pesachDate += 1;
            }

            $pesachDate += (int)floor(($year - 3760) / 100) - (int)floor(($year - 3760) / 400) - 2;

            return $pesachDate;
        }

        public static function isLeapGrYear(int $t): bool {
            return $t % 400 === 0 || $t % 100 !== 0 && $t % 4 === 0;
        }

        public static function getHYearLength(int $year): int {
            $a = static::calculateGauss($year);
            $b = static::calculateGauss($year - 1);
            $c = static::isLeapGrYear($year - 3760) ? 1 : 0;
            $d = $a - $b + $c + 365;

            return $d;
        }

        public static function getHMonthsByYear(int $year): array {
            $d = static::getHYearLength($year);

            return HCalendarTools::getHMonthsByYearLength($d);
        }
    }
}
