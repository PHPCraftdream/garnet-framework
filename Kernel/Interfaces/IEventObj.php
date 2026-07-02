<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IEventObj {
        public function getEventName(): string;

        public function getArgs(): array;

        public function isStopped(): bool;

        public function isRunning(): bool;

        public function stop(bool $stopped): void;

        public function getResult(): mixed;

        public function setResult(mixed $result): void;
    }
}
