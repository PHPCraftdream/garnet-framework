<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io {
    /**
     * Dev-only debug helpers. The inline `<pre>` here is an intentional
     * exception to AGENTS.md §12 "HTML markup — Twig only" — these
     * functions are only ever called from local debugging spikes
     * (`echo IoTools::pr($x); exit;`), have no production callers,
     * and must keep working when the Twig environment is broken
     * (which is often *why* you'd be debugging).
     */
    class IoTools {
        public static function pr(mixed $value): string {
            return '<pre>' . htmlspecialchars(print_r($value, true)) . '</pre>';
        }

        public static function varDump(mixed $value): string {
            ob_start();
            var_dump($value);
            $result = ob_get_clean();

            return '<pre>' . htmlspecialchars($result ?: '') . '</pre>';
        }

        public static function exitPr(mixed $value): void {
            echo static::pr($value);

            exit;
        }

        public static function exitVarDump(mixed $value): void {
            echo static::varDump($value);

            exit;
        }

        protected static ?float $benchmark = null;

        /**
         * @var array<string>
         */
        protected static array $benchmarkArr = [];

        /**
         * @return string[]
         */
        public static function getBenchmarkArr(): array {
            return self::$benchmarkArr;
        }

        public static function benchmark(string $name): string {
            if (static::$benchmark === null) {
                static::$benchmark = microtime(true);
            }

            $val = number_format((microtime(true) - static::$benchmark), 5, '.', ' ');
            $addVal = $name . ' - ' . $val;
            static::$benchmarkArr[] = $addVal;

            return $addVal;
        }
    }
}
