<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Tools {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IRuntimeParams;

    class RuntimeParams implements IRuntimeParams {
        /**
         * @var ?IRuntimeParams
         */
        protected static ?IRuntimeParams $instance = null;

        /**
         * @var Array<callable> $callableItems
         */
        protected array $callableItems = [];

        /**
         * @var Array<array> $params
         */
        protected array $params = [];

        /**
         * @return IRuntimeParams
         */
        public static function init(): IRuntimeParams {
            if (empty(static::$instance)) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        /**
         * @param string $paramsName
         * @param array|callable $params
         * @return void
         */
        public function set(
            string $paramsName,
            array|callable $params,
        ): void {
            if (is_callable($params)) {
                $this->callableItems[$paramsName] = $params;
            } else {
                $this->params[$paramsName] = $params;
            }
        }

        /**
         * @param string $paramsName
         * @param array $appendToResult
         * @param bool $recursiveAppend
         * @return array
         */
        public function get(string $paramsName, array $appendToResult = [], bool $recursiveAppend = true): array {
            $result = [];

            if (empty($this->params[$paramsName])) {
                if (!empty($this->callableItems[$paramsName])) {
                    $fn = $this->callableItems[$paramsName];
                    $result = $fn();
                    $this->params[$paramsName] = $result;
                }
            } else {
                $result = $this->params[$paramsName];
            }

            if (empty($appendToResult)) {
                return $result;
            }

            if ($recursiveAppend) {
                return ArrayTools::array_merge_recursive($result, $appendToResult);
            }

            return [...$result, ...$appendToResult];
        }
    }
}
