<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Exceptions {
    class DbException extends CommonException {
        protected string $sql = '';

        protected array $args = [];

        /**
         * @return array
         */
        public function getArgs(): array {
            return $this->args;
        }

        /**
         * @param array $args
         */
        public function setArgs(array $args): void {
            $this->args = $args;
        }

        /**
         * @return string
         */
        public function getSql(): string {
            return $this->sql;
        }

        /**
         * @param string $sql
         */
        public function setSql(string $sql): void {
            $this->sql = $sql;
        }
    }
}
