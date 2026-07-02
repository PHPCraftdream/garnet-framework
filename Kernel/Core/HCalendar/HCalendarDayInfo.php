<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\HCalendar {
    use Exception;

    trait HCalendarDayInfo {
        protected bool $isTovIsrael = false;

        protected bool $isPreTov = false;

        protected bool $isMoedIsrael = false;

        protected bool $isTovOut = false;

        protected bool $isMoedOut = false;

        protected bool $isShabbat = false;

        protected bool $isSheshi = false;

        protected bool $isTsom = false;

        protected bool $isPreTsom = false;

        protected bool $isCelebrateDay = false;

        protected bool $isPreCelebrateDay = false;

        protected array $israelItems = [];

        protected array $outItems = [];

        protected array $commonItems = [];

        protected static function setDayInfo(HCalendarBase $res): void {
            $res->isTovIsrael = false;
            $res->isMoedIsrael = false;

            $res->isTovOut = false;
            $res->isPreTov = false;
            $res->isMoedOut = false;

            $res->isShabbat = false;
            $res->isSheshi = false;
            $res->isTsom = false;
            $res->isPreTsom = false;
            $res->israelItems = [];
            $res->outItems = [];

            if ($res->weekDay === 7) {
                $res->isShabbat = true;

                $res->israelItems[] = HCalendarDays::iom_Shabbat;
                $res->outItems[] = HCalendarDays::iom_Shabbat;
            }

            if ($res->weekDay === 6) {
                $res->isSheshi = true;
            }

            $monthsLength = HCalendarTools::getHMonthsByYear($res->hYear);
            $monthLengthPrev = $monthsLength[$res->hMonthNum - 2] ?? null;
            $is30DaysPrev = $monthLengthPrev === 30;

            if (!$monthsLength) {
                throw new Exception("Fail on get month length: {$res->hYear}.{$res->hMonthNum}");
            }

            switch ($res->hMonthNum) {
                case 13: // Elul
                    if ($res->hDay === 29) {
                        $res->isPreTov = true;
                    }

                    break;

                case 1: // Tishrei
                    switch ($res->hDay) {
                        case 1:
                            $res->isTovOut = true;
                            $res->isTovIsrael = true;

                            $res->commonItems[] = HCalendarDays::iom_RoshHaShana1;

                            break;

                        case 2:
                            $res->isTovOut = true;
                            $res->isTovIsrael = true;

                            $res->commonItems[] = HCalendarDays::iom_RoshHaShana2;

                            if ($res->weekDay !== 6) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 3:
                            if ($res->weekDay === 7) {
                                $res->isPreTsom = true;
                            } else {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TzomGedaliah;
                            }

                            break;

                        case 4:
                            if ($res->weekDay === 1) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TzomGedaliah;
                            }

                            break;

                        case 9:
                            $res->isPreTov = true;
                            $res->isPreTsom = true;

                            break;

                        case 10:
                            $res->isTovIsrael = true;
                            $res->isTovOut = true;
                            $res->isTsom = true;

                            $res->commonItems[] = HCalendarDays::iom_YomKippur;

                            break;

                        case 14:
                            $res->isPreTov = true;

                            break;

                        case 15:
                            $res->isTovIsrael = true;
                            $res->isTovOut = true;

                            $res->commonItems[] = HCalendarDays::iom_Sukkot;

                            break;

                        case 16:
                            $res->isMoedIsrael = true;
                            $res->isTovOut = true;

                            $res->israelItems[] = HCalendarDays::iom_SukkotHa;
                            $res->outItems[] = HCalendarDays::iom_Sukkot;

                            break;

                        case 17:
                        case 18:
                        case 19:
                        case 20:
                            $res->isMoedIsrael = true;
                            $res->isMoedOut = true;

                            $res->commonItems[] = HCalendarDays::iom_SukkotHa;

                            break;

                        case 21:
                            $res->isMoedIsrael = true;
                            $res->isMoedOut = true;
                            $res->isPreTov = true;

                            $res->commonItems[] = HCalendarDays::iom_SukkotHa;
                            $res->commonItems[] = HCalendarDays::iom_OshanaRaba;

                            break;

                        case 22:
                            $res->isTovIsrael = true;
                            $res->isTovOut = true;
                            $res->isPreTov = true;

                            $res->commonItems[] = HCalendarDays::iom_SminiAtseret;
                            $res->israelItems[] = HCalendarDays::iom_SimkhatTotrah;

                            break;

                        case 23:
                            $res->isTovOut = true;

                            $res->outItems[] = HCalendarDays::iom_SminiAtseret2;
                            $res->outItems[] = HCalendarDays::iom_SimkhatTotrah;

                            break;

                        case 30:
                            $res->commonItems[] = HCalendarDays::iom_RoshHodesh1;

                            break;
                    }

                    break;

                case 3: // Kislev
                    switch ($res->hDay) {
                        case 24:
                            $res->isPreCelebrateDay = true;

                            break;

                        case 25:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_Hanuka1;

                            break;

                        case 26:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_Hanuka2;

                            break;

                        case 27:
                            $res->commonItems[] = HCalendarDays::iom_Hanuka3;
                            $res->isCelebrateDay = true;

                            break;

                        case 28:
                            $res->commonItems[] = HCalendarDays::iom_Hanuka4;
                            $res->isCelebrateDay = true;

                            break;

                        case 29:
                            $res->commonItems[] = HCalendarDays::iom_Hanuka5;
                            $res->isCelebrateDay = true;

                            break;

                        case 30:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_Hanuka6;

                            break;
                    }

                    break;

                case 4: // Tevet
                    switch ($res->hDay) {
                        case 1:
                            $res->isCelebrateDay = true;

                            if ($is30DaysPrev) {
                                $res->commonItems[] = HCalendarDays::iom_Hanuka7;
                            } else {
                                $res->commonItems[] = HCalendarDays::iom_Hanuka6;
                            }

                            break;

                        case 2:
                            $res->isCelebrateDay = true;

                            if ($is30DaysPrev) {
                                $res->commonItems[] = HCalendarDays::iom_Hanuka8;
                            } else {
                                $res->commonItems[] = HCalendarDays::iom_Hanuka7;
                            }

                            break;

                        case 3:
                            if (!$is30DaysPrev) {
                                $res->isCelebrateDay = true;
                                $res->commonItems[] = HCalendarDays::iom_Hanuka8;
                            }

                            break;

                        case 9:
                            $res->isPreTsom = true;

                            break;

                        case 10:
                            $res->isTsom = true;
                            $res->commonItems[] = HCalendarDays::iom_AsaraBaTevet;

                            break;
                    }

                    break;

                case 5: // Shvat
                    switch ($res->hDay) {
                        case 14:
                            $res->isPreCelebrateDay = true;

                            break;

                        case 15:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_TuBeShvat;

                            break;
                    }

                    break;

                case 6: // Adar, Adar-1
                case 7: // Adar-2
                    if ($res->hMonthNum === 6 && $res->isLeapHYear) {
                        break;
                    }

                    switch ($res->hDay) {
                        case 10:
                            if ($res->weekDay === 4) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 11:
                            if ($res->weekDay === 5) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TaanitEsther;
                            }

                            break;

                        case 12:
                            if ($res->weekDay !== 6) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 13:
                            if ($res->weekDay !== 7) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TaanitEsther;
                            }
                            $res->isPreCelebrateDay = true;

                            break;

                        case 14:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_Purim;

                            break;

                        case 15:
                            $res->commonItems[] = $res->weekDay === 7 ? HCalendarDays::iom_Purim : HCalendarDays::iom_ShushanPurimMeshulash;
                    }

                    break;

                case 8: // Nissan
                    switch ($res->hDay) {
                        case 11:
                            if ($res->weekDay === 4) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 12:
                            if ($res->weekDay === 5) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TaanitBkhorim;
                            }

                            break;

                        case 13:
                            if ($res->weekDay !== 6) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 14:
                            if ($res->weekDay !== 7) {
                                $res->isTsom = true;
                                $res->isPreTov = true;
                                $res->commonItems[] = HCalendarDays::iom_TaanitBkhorim;
                            }

                            break;

                        case 15:
                        case 21:
                            $res->isTovIsrael = true;
                            $res->isTovOut = true;

                            $res->commonItems[] = HCalendarDays::iom_Pesach;

                            break;

                        case 16:
                            $res->isMoedIsrael = true;
                            $res->isTovOut = true;

                            $res->israelItems[] = HCalendarDays::iom_PesachMoed;
                            $res->outItems[] = HCalendarDays::iom_Pesach;

                            break;

                        case 17:
                        case 18:
                        case 19:
                            $res->isMoedIsrael = true;
                            $res->isMoedOut = true;

                            $res->israelItems[] = HCalendarDays::iom_PesachMoed;
                            $res->outItems[] = HCalendarDays::iom_PesachMoed;

                            break;

                        case 20:
                            $res->isMoedIsrael = true;
                            $res->isMoedOut = true;
                            $res->isPreTov = true;

                            $res->israelItems[] = HCalendarDays::iom_PesachMoed;
                            $res->outItems[] = HCalendarDays::iom_PesachMoed;

                            break;

                        case 22:
                            $res->isTovOut = true;
                            $res->outItems[] = HCalendarDays::iom_Pesach;

                            break;
                    }

                    break;

                case 9: // Iyar
                    switch ($res->hDay) {
                        case 14:
                            $res->commonItems[] = HCalendarDays::iom_PesachSheni;

                            break;

                        case 17:
                        case 25:
                            $res->isPreCelebrateDay = true;

                            break;

                        case 18:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_LagBaOmer;

                            break;

                        case 26:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_SalvationAndLiberation;

                            break;
                    }

                    break;

                case 10: // Sivan
                    switch ($res->hDay) {
                        case 5:
                            $res->isPreTov = true;

                            break;

                        case 6:
                            $res->isTovIsrael = true;
                            $res->isTovOut = true;
                            $res->commonItems[] = HCalendarDays::iom_Shavuot;

                            break;

                        case 7:
                            $res->isTovOut = true;
                            $res->outItems[] = HCalendarDays::iom_Shavuot;

                            break;
                    }

                    break;

                case 11: // Tamuz
                    switch ($res->hDay) {
                        case 16:
                            if ($res->weekDay !== 6) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 17:
                            if ($res->weekDay === 7) {
                                $res->isPreTsom = true;
                            } else {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_ShivahAsarBaTammuz;
                            }

                            break;

                        case 18:
                            if ($res->weekDay === 1) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_ShivahAsarBaTammuz;
                            }

                            break;
                    }

                    break;

                case 12: // Av
                    switch ($res->hDay) {
                        case 8:
                            if ($res->weekDay !== 6) {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 9:
                            if ($res->weekDay !== 7) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TeshaBeAv;
                            } else {
                                $res->isPreTsom = true;
                            }

                            break;

                        case 10:
                            if ($res->weekDay === 1) {
                                $res->isTsom = true;
                                $res->commonItems[] = HCalendarDays::iom_TeshaBeAv;
                            }

                            break;

                        case 14:
                            $res->isPreCelebrateDay = true;

                            break;

                        case 15:
                            $res->isCelebrateDay = true;
                            $res->commonItems[] = HCalendarDays::iom_TuaBeAv;

                            break;
                    }
            }

            if ($res->hDay === 30) {
                $res->commonItems[] = HCalendarDays::iom_RoshHodesh1;
            }

            if ($res->hDay === 1) {
                if ($is30DaysPrev) {
                    $res->commonItems[] = HCalendarDays::iom_RoshHodesh2;
                } else {
                    $res->commonItems[] = HCalendarDays::iom_RoshHodesh;
                }
            }
        }

        /**
         * @return bool
         */
        public function isTovIsrael(): bool {
            return $this->isTovIsrael;
        }

        /**
         * @return bool
         */
        public function isPreTov(): bool {
            return $this->isPreTov;
        }

        /**
         * @return bool
         */
        public function isMoedIsrael(): bool {
            return $this->isMoedIsrael;
        }

        /**
         * @return bool
         */
        public function isTovOut(): bool {
            return $this->isTovOut;
        }

        /**
         * @return bool
         */
        public function isMoedOut(): bool {
            return $this->isMoedOut;
        }

        /**
         * @return bool
         */
        public function isShabbat(): bool {
            return $this->isShabbat;
        }

        /**
         * @return bool
         */
        public function isSheshi(): bool {
            return $this->isSheshi;
        }

        /**
         * @return bool
         */
        public function isTsom(): bool {
            return $this->isTsom;
        }

        /**
         * @return bool
         */
        public function isPreTsom(): bool {
            return $this->isPreTsom;
        }

        /**
         * @return bool
         */
        public function isCelebrateDay(): bool {
            return $this->isCelebrateDay;
        }

        /**
         * @return bool
         */
        public function isPreCelebrateDay(): bool {
            return $this->isPreCelebrateDay;
        }

        /**
         * @return array
         */
        public function getIsraelItems(): array {
            return $this->israelItems;
        }

        /**
         * @return array
         */
        public function getOutItems(): array {
            return $this->outItems;
        }

        /**
         * @return array
         */
        public function getCommonItems(): array {
            return $this->commonItems;
        }
    }
}
