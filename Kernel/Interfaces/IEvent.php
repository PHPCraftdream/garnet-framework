<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IEvent {
        public const SCOPE_MAIN = 'SCOPE_MAIN';

        public const SCOPE_ROUTE = 'SCOPE_ROUTE';

        public function subscribe(string $eventName, callable $handler): void;

        public function emit(string $eventName, array $args = []): IEventObj;
    }
}
