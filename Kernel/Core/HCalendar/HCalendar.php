<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    use PHPCraftdream\Garnet\Kernel\Interfaces\II18n;

    class HCalendar extends HCalendarBase {
        public function toStrHTr(II18n $i18n): string {
            $key = strtoupper("HEB_MONTH_{$this->hMonthName}");
            $monthName = $i18n->tr($key);

            return "{$this->hDay} {$monthName} {$this->hYear}";
        }

        public function toStrWorldTr(II18n $i18n): string {
            $key = strtoupper("WORLD_MONTH_{$this->hMonthName}");
            $monthName = $i18n->tr($key);

            return "{$this->hDay} {$monthName} {$this->hYear}";
        }
    }
}
