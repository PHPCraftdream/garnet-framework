<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IGlobalReqParams {
        public function readServerValue(string $name, mixed $default = null): string|null;

        public function readServerAll(): array;

        public function readGetValue(string $name, mixed $default = null): mixed;

        public function readGetAll(): array;

        public function readPostValue(string $name, mixed $default = null): mixed;

        public function readPostAll(): array;

        public function readCookieValue(string $name, mixed $default = null): string|null;

        public function readCookieAll(): array;

        public function readFilesValue(string $name, mixed $default = null): mixed;

        public function readFilesAll(): array;

        public function getUri(): string;

        public function httpMethod(): string;

        public function isPost(): bool;

        public function isEmptyPost(): bool;

        public function isGet(): bool;

        public function isLocalhost(): bool;

        public function isPhpServer(): bool;

        public function isDev(): bool;

        public function ip(): string;
    }
}
