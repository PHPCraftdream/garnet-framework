<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Event {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IEventObj;

    class EventObj implements IEventObj {
        protected bool $executed = false;

        protected bool $stopped = false;

        protected mixed $result = null;

        public function __construct(protected string $eventName, protected array $args = []) {
        }

        public function getEventName(): string {
            return $this->eventName;
        }

        public function getArgs(): array {
            return $this->args;
        }

        public function isStopped(): bool {
            return $this->stopped;
        }

        public function isRunning(): bool {
            return !$this->stopped;
        }

        public function stop(bool $stopped): void {
            $this->stopped = $stopped;
        }

        public function getResult(): mixed {
            return $this->result;
        }

        public function setResult(mixed $result): void {
            $this->result = $result;
        }

        public function isExecuted(): bool {
            return $this->executed;
        }

        public function setExecuted(): void {
            $this->executed = true;
        }
    }
}
