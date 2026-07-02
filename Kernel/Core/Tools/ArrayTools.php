<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Tools {
    class ArrayTools {
        public static function insertAfterValue(array $originalArray, string $insertAfterValue, array $newElements): array {
            $newArray = [];

            foreach ($originalArray as $value) {
                $newArray[] = $value;

                if ($value === $insertAfterValue) {
                    $newArray = array_merge($newArray, $newElements);
                }
            }

            return $newArray;
        }

        public static function array_merge_recursive(array $array1, array $array2): array {
            $result = $array1;

            foreach ($array2 as $key => $value) {
                if (is_array($value) && array_key_exists($key, $result) && is_array($result[$key])) {
                    $hasZero1 = array_key_exists(0, $value);

                    /** @var array $merged */
                    $merged = $hasZero1
                        ? [...$value, ...$result[$key]]
                        : array_merge_recursive($result[$key], $value)
                    ;
                    $result[$key] = $merged;
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        protected static function isEqualsDB(mixed $a, mixed $b): bool {
            if (is_int($a)) {
                return $a === intval($b);
            }

            if (is_int($b)) {
                return $b === intval($a);
            }

            if (!$a) {
                return !$b;
            }

            if (!$b) {
                return false;
            }

            return $a === $b;
        }

        public static function arrayDbDiffValues(array $prev, array $new): array {
            $result = [];

            foreach ($new as $k => $newVal) {
                $prevVal = $prev[$k] ?? null;

                if (!static::isEqualsDB($prevVal, $newVal)) {
                    $result[$k] = [$prevVal . '', $newVal . ''];
                }
            }

            return $result;
        }
    }
}
