<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    class LastQuery {
        public function __construct(
            protected string $sql,
            protected array $params
        ) {
        }

        /**
         * @return string
         */
        public function getSql(): string {
            return $this->sql;
        }

        /**
         * @return array
         */
        public function getParams(): array {
            return $this->params;
        }
    }
}
