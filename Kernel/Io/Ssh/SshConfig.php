<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh;

use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use Throwable;

final class SshConfig {
    public function __construct(
        public readonly string $host,
        public readonly string $user,
        public readonly int $port = 22,
        public readonly string $identityFile = '',
        public readonly string $identityKey = '',
        public readonly string $remotePath = '',
        public readonly string $strictHostKeyChecking = 'accept-new',
    ) {
    }

    public static function fromIniConfig(): self {
        $ssh = IniConfig::ssh();
        // remote_path is a deployment-layout param, not a connection param —
        // it lives in deploy.ini. Falls back silently if deploy.ini is absent.
        $remotePath = '';

        try {
            $remotePath = IniConfig::deploy()->paramString('remote_path', '');
        } catch (Throwable) { /* deploy.ini missing — ok */
        }

        return new self(
            host: $ssh->paramString('host', ''),
            user: $ssh->paramString('user', ''),
            port: $ssh->paramInt('port', 22),
            identityFile: IniConfig::sshIdentityFile(),
            identityKey: $ssh->paramString('identity_key', ''),
            remotePath: $remotePath,
            strictHostKeyChecking: $ssh->paramString('strict_host_key_checking', 'accept-new'),
        );
    }

    public function with(string $key, mixed $value): self {
        $args = [
            'host' => $this->host,
            'user' => $this->user,
            'port' => $this->port,
            'identityFile' => $this->identityFile,
            'identityKey' => $this->identityKey,
            'remotePath' => $this->remotePath,
            'strictHostKeyChecking' => $this->strictHostKeyChecking,
        ];
        $args[$key] = $value;

        return new self(...$args);
    }
}
