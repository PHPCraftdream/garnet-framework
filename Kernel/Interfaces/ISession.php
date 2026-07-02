<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    interface ISession {
        public function readFromRequest(RequestInterface $request): void;

        public function readFromServer(array $_server): void;

        public function patchResponse(ResponseInterface $response): ResponseInterface;

        public function touchCookie(bool $createCookie = false): void;

        public function peekCSRF(): string;

        public function touchCSRF(): string;

        public function readDataAsync(): void;

        public function readDataAsyncPollFinishAll(): void;

        public function setValue(string $name, string $value): void;

        public function unsetValue(string $name): void;

        public function unsetValues(array $names): void;

        public function getValue(string $name, ?string $default = null): ?string;

        public function getAllData(): array;

        public function getToken(): string;

        public function flush(): void;

        public function isReadCookies(): bool;
    }
}
