<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\GlobalVars {
    class GlobalVars4Tests {
        /**
         * @var array<string, mixed>
         */
        protected static array $values = [];

        public static function set(string $name, mixed $value): void {
            static::$values[$name] = $value;
        }

        public static function setNotNull(string $name, mixed $value): void {
            if ($value !== null) {
                static::$values[$name] = $value;
            }
        }

        public static function get(string $name, mixed $default = null): mixed {
            return array_key_exists($name, static::$values) ? static::$values[$name] : $default;
        }

        public static function getString(string $name, string $default): string {
            if (array_key_exists($name, static::$values)) {
                $val = static::$values[$name];

                return is_string($val) ? $val : $default;
            }

            return $default;
        }

        public static function reset(): void {
            static::$values = [];
        }

        public static function getAll(): array {
            return static::$values;
        }
    }
}
