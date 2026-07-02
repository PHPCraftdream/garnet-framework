<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Tools {
    use DateTime;
    use DateTimeZone;
    use Exception;
    use InvalidArgumentException;

    class StrTools {
        /**
         * @param string $string
         * @param int $length
         * @param string $padString
         * @param bool $fromRight
         * @return string
         */
        public static function pad(string $string, int $length, string $padString = ' ', bool $fromRight = true): string {
            $pad = $fromRight ? STR_PAD_RIGHT : STR_PAD_LEFT;

            return str_pad($string, $length, $padString, $pad);
        }

        /**
         * @param array $array
         * @return int
         */
        public static function maxKeyLen(array $array): int {
            $maxLen = 0;

            foreach ($array as $key => $val) {
                $currentLen = mb_strlen($key);

                if ($currentLen > $maxLen) {
                    $maxLen = $currentLen;
                }
            }

            return $maxLen;
        }

        protected static array $symbolsN = [
            'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'V', 'B',
            'N', 'M', 'q', 'w', 'e', 'r', 't', 'y', 'u', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'i', 'j', 'k', 'z', 'x',
            'v', 'b', 'n', 'm', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        ];

        protected static array $symbols = [
            'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'V', 'B',
            'N', 'M', 'q', 'w', 'e', 'r', 't', 'y', 'u', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'i', 'j', 'k', 'z', 'x',
            'v', 'b', 'n', 'm'
        ];

        /**
         * @param int<1, max> $length
         * @return string
         * @throws Exception
         */
        public static function randomUtString(int $length = 32): string {
            $ut = static::intToBase55(time());
            $addLen = $length - strlen($ut);

            if ($addLen <= 0) {
                throw new InvalidArgumentException('Wrong length ' . $length);
            }

            return $ut . static::randomString($addLen);
        }

        public static function intToBase55(int $number): string {
            $base = count(static::$symbolsN);

            $resultArray = [];

            if ($number === 0) {
                return static::$symbolsN[0];
            }

            while ($number > 0) {
                $remainder = $number % $base;
                $resultArray[] = static::$symbolsN[$remainder];
                $number = intdiv($number, $base);
            }

            return join('', array_reverse($resultArray));
        }

        /**
         * @param int<1, max> $length
         * @return string
         * @throws Exception
         */
        public static function randomString(int $length = 32): string {
            $strItemsA = &static::$symbols;
            $strItemsCountA = count($strItemsA);

            $strItems = &static::$symbolsN;
            $strItemsCount = count($strItems);

            $bytes = str_split(random_bytes($length));

            $result = '';

            foreach ($bytes as $ind => $char) {
                $c1 = ord($char) * random_int(101, 251) % 521;
                /* @phpstan-ignore-next-line */
                $c2 = ($ind + $c1) * random_int($c1, $c1 + $c1) % 521;
                /* @phpstan-ignore-next-line */
                $c3 = round(microtime(true) * random_int($c2, $c2 + $c2)) % 521;
                $c4 = ($c1 + $c2 + $c3) * 521;

                if (empty($result)) {
                    $newInd = $c4 % $strItemsCountA;
                    $result .= $strItemsA[$newInd];
                } else {
                    $newInd = $c4 % $strItemsCount;
                    $result .= $strItems[$newInd];
                }
            }

            return $result;
        }

        /**
         * @param string $string
         * @param string $prefix
         * @return string
         */
        public static function removePrefix(string $string, string $prefix): string {
            if (str_starts_with($string, $prefix)) {
                $string = substr($string, strlen($prefix));
            }

            return $string;
        }

        /**
         * @param $data
         * @return array|null
         */
        public static function jsonRead($data): array|null {
            if (empty($data)) {
                return null;
            }

            $res = (array)json_decode($data);

            if (json_last_error() === 0) {
                return $res;
            }

            return null;
        }

        public static function utToDate(int|string $time, string $timeZone): DateTime {
            $dt = new DateTime('@' . intval($time));
            $dt->setTimeZone(new DateTimeZone($timeZone));

            return $dt;
        }

        public static function utToDateF(int|string $time, string $timeZone, string $format = 'Y-m-d H:i'): string {
            return static::utToDate($time . '', $timeZone)->format($format);
        }

        public static function isIntStr(int|string|null|bool $time): bool {
            return ($time . '') === (intval($time) . '');
        }

        public static function convertToSnakeCase(string $input): string {
            $pattern = '/([a-z])([A-Z])/';
            $snakeCase = preg_replace($pattern, '$1_$2', $input);
            $snakeCase = strtolower($snakeCase);

            return $snakeCase;
        }
    }
}
