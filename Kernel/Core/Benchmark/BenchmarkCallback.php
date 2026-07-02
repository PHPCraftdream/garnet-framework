<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Benchmark {
    use Closure;

    class BenchmarkCallback {
        /**
         * @var array<string, Closure>
         */
        protected array $callBacks = [];

        public function __construct(
            protected int $experimentSize = 100
        ) {
        }

        /**
         * @param int $experimentSize
         */
        public function setExperimentSize(int $experimentSize): void {
            $this->experimentSize = $experimentSize;
        }

        /**
         * @param string $name
         * @param callable $callback
         */
        public function setCallBack(string $name, callable $callback): void {
            $this->callBacks[$name] = $callback(...);
        }

        /**
         * @return array<string, float>
         */
        public function run(): array {
            $result = [];

            foreach ($this->callBacks as $name => $callBack) {
                $min = null;

                for ($j = 0; $j < $this->experimentSize; $j += 1) {
                    $start = microtime(true);
                    $callBack($j);
                    $current = round(microtime(true) - $start, 8);

                    if ($min === null || $current < $min) {
                        $min = $current;
                    }
                }

                $result[$name] = $min === null ? 0 : $min;
            }

            return $result;
        }
    }
}
