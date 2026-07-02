<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cookies {
    trait StringUtilTrait {
        /**
         * @param string $string
         * @return array<string>
         */
        public function splitOnAttributeDelimiter(string $string): array {
            $val = preg_split('@\s*;\s*@', $string);

            return $val === false ? [] : array_values(array_filter($val));
        }

        /**
         * @param string $string
         * @return array{string, string}
         */
        public function splitCookiePair(string $string): array {
            $pairParts = explode('=', $string, 2);

            if (count($pairParts) === 1) {
                $pairParts[1] = '';
            }

            return [
                urldecode($pairParts[0]),
                urldecode($pairParts[1]),
            ];
        }
    }
}
