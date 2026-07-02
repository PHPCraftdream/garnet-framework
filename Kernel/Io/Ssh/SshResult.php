<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh;

final class SshResult {
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly float $durationMs,
        public readonly array $argv,
    ) {
    }

    public function ok(): bool {
        return $this->exitCode === 0;
    }
}
