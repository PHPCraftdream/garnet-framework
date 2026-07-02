<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Benchmark {
    class BenchmarkLog {
        protected static float $start;

        protected static float $last;

        protected static array $items = [];

        public static function init(string $str): void {
            static::$start = microtime(true);
            static::$items[] = [0, $str];
        }

        public static function log(string $name): void {
            static::$last = round(microtime(true) - static::$start, 4);
            static::$items[] = [static::$last, $name];
        }

        public static function printItems(): string {
            return join(PHP_EOL, array_map(fn ($item) => $item[0] . ' - ' . $item[1], self::$items));
        }

        public static function last(): float {
            return static::$last;
        }
    }
}
