<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IAccount {
        public function id(): int;

        public function readDbAsync(): void;

        public function getParams(): array;

        public function getData(): array;

        public function readParam(string $name, string $default = null): ?string;

        public function readParams(array $names): array;

        public function readData(string $name, string $default = null): ?string;

        public function readDataParams(array $names): array;

        public function setParam(string $name, string|int|null $value): void;

        public function setParams(array $params): void;

        public function setData(string $name, string|int $value): void;

        public function unsetData(string $name): void;

        public function setBoolDataArr(array $names): void;

        public function setDataArr(array $names): void;

        public function unsetDataArr(array $names): void;

        public function flush(): void;
    }
}
