<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\Event {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IEvent;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IEventObj;

    class Event implements IEvent {
        protected static array $items = [];

        protected array $handlers = [];

        /**
         * @param string $scopeName
         * @return IEvent
         */
        public static function get(string $scopeName = IEvent::SCOPE_MAIN): IEvent {
            if (empty(static::$items[$scopeName])) {
                $obj = new static();
                static::$items[$scopeName] = $obj;
            }

            return static::$items[$scopeName];
        }

        /**
         * @param string $eventName
         * @param callable $handler
         * @return void
         */
        public function subscribe(string $eventName, callable $handler): void {
            $this->handlers[$eventName][] = $handler;
        }

        /**
         * @param string $eventName
         * @param array $args
         * @return IEventObj
         */
        public function emit(string $eventName, array $args = []): IEventObj {
            $eventObj = new EventObj($eventName, $args);

            if (empty($this->handlers[$eventName])) {
                return $eventObj;
            }

            $handlers = $this->handlers[$eventName];

            foreach ($handlers as $handler) {
                if ($eventObj->isRunning()) {
                    call_user_func($handler, $eventObj);
                    $eventObj->setExecuted();
                }
            }

            return $eventObj;
        }
    }
}
