<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IIniConfig {
        public function set(string $name, mixed $value): void;

        public function setRuntimeOverride(string $name, mixed $value): void;

        public function clearRuntimeOverride(string $name): void;

        public function clearAllRuntimeOverrides(): void;

        public function param(string $name, mixed $default = null): mixed;

        public function paramString(string $name, ?string $default = null): string;

        public function paramArray(string $name, array $default = []): array;

        public function paramInt(string $name, ?int $default = null): int;

        public function paramBool(string $name, bool $default = false): bool;

        public function paramWithFlag(string $name, mixed $default = null): array;

        public function all(): array;

        public function getFilePath(): string;
    }
}
