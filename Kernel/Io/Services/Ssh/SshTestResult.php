<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Services\Ssh;

final class SshTestResult {
    public function __construct(
        public readonly bool $ok,
        public readonly string $pwd,
        public readonly string $whoami,
        public readonly SshResult $raw,
    ) {
    }
}
