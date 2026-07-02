<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface ISettings {
        public function getValue(string $param, string $default = ''): string;

        public function setValue(string $param, string $value): void;

        public function unsetValue(string $name): void;

        public function getAllData(): array;

        public function flush(): void;

        public function readAsync(): void;

        public function read(): void;
    }
}
