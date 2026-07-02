<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    use DateInterval;
    use DateTime;
    use Exception;
    use InvalidArgumentException;

    class HCalendarBase {
        use HCalendarDayInfo;

        protected int $gYear = 1;

        protected int $gMonth = 1;

        protected int $gDay = 1;

        protected int $weekDay = 1;

        protected int $hYear = 1;

        protected string $hMonthName = '';

        protected string $hMonthNameLower = '';

        protected string $hMonthInd = '';

        protected int $hMonthNum = 1;

        protected int $hDay = 1;

        protected bool $isLeapHYear = false;

        protected DateTime $date;

        protected int $jd;

        protected int $hYearDays = 0;

        protected int $hYearStartJd = 0;

        protected int $hYearEndJd = 0;

        /**
         * @return int
         */
        public function getHYear(): int {
            return $this->hYear;
        }

        /**
         * @return string
         */
        public function getHMonthName(): string {
            return $this->hMonthName;
        }

        /**
         * @return int
         */
        public function getHDay(): int {
            return $this->hDay;
        }

        /**
         * @return int
         */
        public function getHMonthNum(): int {
            return $this->hMonthNum;
        }

        /**
         * @return bool
         */
        public function isLeapHYear(): bool {
            return $this->isLeapHYear;
        }

        /**
         * @return int
         */
        public function getWeekDay(): int {
            return $this->weekDay;
        }

        // ##############################################################################################################

        /**
         * @param static $res
         * @return static
         */
        protected static function fromDateProcess(HCalendarBase $res): static {
            $dateJewish = jdtojewish($res->jd);
            [$hMonthNum, $day, $year] = explode('/', $dateJewish);
            $year = intval($year);
            $hMonthNum = intval($hMonthNum);
            $day = intval($day);

            [$startJd, $endJd, $length, $isLeap] = HCalendarTools::getHYearInfo($year);

            $res->isLeapHYear = $isLeap;
            $res->hYearDays = $length;
            $res->hYearStartJd = $startJd;
            $res->hYearEndJd = $endJd;

            [$hMonthName, $hMonthNameLower] = HCalendarTools::getMonthName($hMonthNum, $res->isLeapHYear);

            $res->weekDay = intval($res->date->format('w')) + 1;

            $res->hDay = $day;
            $res->hYear = $year;
            $res->hMonthName = $hMonthName;
            $res->hMonthNameLower = $hMonthNameLower;
            $res->hMonthNum = $hMonthNum;

            static::setDayInfo($res);

            return $res;
        }

        public static function fromDate(int $gYear, int $gMonth, int $gDay): static {
            $res = new static();

            $res->gDay = $gDay;
            $res->gMonth = $gMonth;
            $res->gYear = $gYear;

            $dateStr = HCalendarTools::dateToStr($gYear, $gMonth, $gDay);
            $res->date = DateTime::createFromFormat('X-m-d', $dateStr);
            $res->jd = gregoriantojd($res->gMonth, $res->gDay, $res->gYear);

            static::fromDateProcess($res);

            return $res;
        }

        public static function fromDateTime(DateTime $date): static {
            $res = new static();

            $res->date = clone $date;
            $res->gDay = intval($date->format('d'));
            $res->gMonth = intval($date->format('m'));
            $res->gYear = intval($date->format('Y'));
            $res->jd = gregoriantojd($res->gMonth, $res->gDay, $res->gYear);

            static::fromDateProcess($res);

            return $res;
        }

        public function addDays(int $days): void {
            $abs = abs($days);
            $interval = DateInterval::createFromDateString($days > 0 ? "{$abs} days" : "-{$abs} days");

            $this->date->add($interval);
            $this->gDay = intval($this->date->format('d'));
            $this->gMonth = intval($this->date->format('m'));
            $this->gYear = intval($this->date->format('Y'));
            $this->jd = gregoriantojd($this->gMonth, $this->gDay, $this->gYear);

            static::fromDateProcess($this);
        }

        public static function fromStrMonthHDate(int $year, string $month, int $day): static {
            $monthIndInfo = HCalendarTools::getMonthIndInfoByName($month);

            if ($monthIndInfo === null) {
                throw new InvalidArgumentException("Unknown month: {$month}");
            }

            $res = new static();

            [$startJd, $endJd, $length, $isLeap] = HCalendarTools::getHYearInfo($year);

            $res->isLeapHYear = $isLeap;
            $res->hYearDays = $length;
            $res->hYearStartJd = $startJd;
            $res->hYearEndJd = $endJd;

            $monthNumber = $monthIndInfo[$res->isLeapHYear ? 1 : 0];
            $res->hMonthNum = $monthNumber;

            $gregorianDate = jdtogregorian(jewishtojd($monthNumber, $day, $year));
            $gregorianDateInfo = explode('/', $gregorianDate);
            /* @phpstan-ignore-next-line */
            $res->gMonth = intval($gregorianDateInfo[0] ?? 0);
            /* @phpstan-ignore-next-line */
            $res->gDay = intval($gregorianDateInfo[1] ?? 0);
            $res->gYear = intval($gregorianDateInfo[2] ?? 0);

            if ($res->gYear < 0) {
                $res->gYear += 1;
            }

            $dateStr = HCalendarTools::dateToStr(max($res->gYear, 0), $res->gMonth, $res->gDay);
            $date = DateTime::createFromFormat('Y-m-d', $dateStr);

            if (!$date) {
                throw new Exception("Fail on convert: {$dateStr}");
            }

            if ($res->gYear < 0) {
                $date->add(DateInterval::createFromDateString("{$res->gYear} years"));
            }

            $res->date = $date;

            $res->hDay = $day;
            $res->hYear = $year;
            $res->hMonthName = $monthIndInfo[2];
            $res->hMonthInd = $month;
            $res->weekDay = intval($res->date->format('w')) + 1;

            static::setDayInfo($res);

            return $res;
        }

        public function toStrH(): string {
            return "{$this->hDay} {$this->hMonthName} {$this->hYear}";
        }

        public function toStr(): string {
            return $this->date->format('Y-m-d');
        }

        public function toDate(): DateTime {
            return clone $this->date;
        }
    }
}
