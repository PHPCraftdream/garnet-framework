<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface ILogger {
        public function write(string $name, string $message): void;

        public function append(string $name, string $message): void;
    }
}
