<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh;

use PHPCraftdream\Garnet\Kernel\Exceptions\SshException;

final class SshClient {
    public static function fromIniConfig(): self {
        return new self(SshConfig::fromIniConfig());
    }

    public function __construct(private readonly SshConfig $config) {
    }

    public function with(string $key, mixed $value): self {
        return new self($this->config->with($key, $value));
    }

    public function validate(): void {
        if ($this->config->host === '') {
            throw new SshException('ssh.ini: host is empty');
        }

        if ($this->config->user === '') {
            throw new SshException('ssh.ini: user is empty');
        }
    }

    public function run(string $command, array $opts = []): SshResult {
        $argv = $this->buildRunArgv($command, $opts);

        return $this->execute($argv, $opts);
    }

    public function put(string $local, ?string $remote = null, array $opts = []): SshResult {
        $argv = $this->buildPutArgv($local, $remote, $opts);
        // scp by default is live-mode — shows progress
        $opts['stream'] ??= true;

        return $this->execute($argv, $opts);
    }

    public function get(string $remote, ?string $local = null, array $opts = []): SshResult {
        $argv = $this->buildGetArgv($remote, $local, $opts);
        $opts['stream'] ??= true;

        return $this->execute($argv, $opts);
    }

    public function test(): SshTestResult {
        $result = $this->run('echo ok; pwd; whoami', ['tty' => false, 'stream' => false]);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $result->stdout))));
        $ok = $result->ok() && ($lines[0] ?? '') === 'ok';

        return new SshTestResult(
            ok: $ok,
            pwd: $lines[1] ?? '',
            whoami: $lines[2] ?? '',
            raw: $result,
        );
    }

    /** @return list<string> */
    public function buildRunArgv(string $command, array $opts = []): array {
        $tty = $opts['tty'] ?? false;
        $noTty = $opts['no_tty'] ?? (!($opts['tty'] ?? false)); // default no-tty for capture
        $verbose = $opts['verbose'] ?? false;
        $stream = $opts['stream'] ?? false;

        // In stream (live) mode, don't force tty/no-tty unless explicitly set
        if ($stream && !array_key_exists('tty', $opts) && !array_key_exists('no_tty', $opts)) {
            $tty = false;
            $noTty = false;
        }

        $cmd = $this->buildBaseArgv('ssh', false);

        if ($tty) {
            $cmd[] = '-t';
        }

        if ($noTty) {
            $cmd[] = '-T';
        }

        if ($verbose) {
            $cmd[] = '-v';
        }

        $cmd[] = "{$this->config->user}@{$this->config->host}";

        $wrapped = $command;

        // --env=K=V → prepend `export K='V'; `
        foreach (($opts['env'] ?? []) as $kv) {
            $eq = strpos($kv, '=');
            $key = $eq !== false ? substr($kv, 0, $eq) : $kv;
            $val = $eq !== false ? substr($kv, $eq + 1) : '';
            $escaped = str_replace("'", "'\\''", $val);
            $wrapped = "export {$key}='{$escaped}'; " . $wrapped;
        }

        // --cwd=PATH or --cd-remote → cd 'PATH' && ( cmd )
        $cwd = $opts['cwd'] ?? '';
        $cdRemote = $opts['cd_remote'] ?? false;

        if ($cwd === '' && $cdRemote) {
            if ($this->config->remotePath === '') {
                throw new SshException('--cd-remote requires remote_path in ssh.ini');
            }
            $cwd = $this->config->remotePath;
        }

        if ($cwd !== '') {
            $escapedCwd = str_replace("'", "'\\''", $cwd);
            $wrapped = "cd '{$escapedCwd}' && ( {$wrapped} )";
        }

        $cmd[] = $wrapped;

        return $cmd;
    }

    /** @return list<string> */
    public function buildPutArgv(string $local, ?string $remote = null, array $opts = []): array {
        $remote = $this->resolveRemotePath($remote, basename($local));
        $cmd = $this->buildBaseArgv('scp', true);

        if ($opts['verbose'] ?? false) {
            $cmd[] = '-v';
        }
        $cmd[] = $local;
        $cmd[] = "{$this->config->user}@{$this->config->host}:{$remote}";

        return $cmd;
    }

    /** @return list<string> */
    public function buildGetArgv(string $remote, ?string $local = null, array $opts = []): array {
        if (!$this->isAbsolutePath($remote)) {
            $remote = rtrim($this->config->remotePath, '/') . '/' . $remote;
        }
        $local ??= basename($remote);
        $cmd = $this->buildBaseArgv('scp', true);

        if ($opts['verbose'] ?? false) {
            $cmd[] = '-v';
        }
        $cmd[] = "{$this->config->user}@{$this->config->host}:{$remote}";
        $cmd[] = $local;

        return $cmd;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build base argv (ssh or scp) with connection flags, identity, port.
     * Does NOT include -t/-T/-v or the destination — those are caller's job.
     * In dry-run mode the identity key tempfile is shown as a placeholder.
     *
     * @param bool $scpMode  scp uses -P (uppercase) for port; ssh uses -p (lowercase)
     * @return list<string>
     */
    private function buildBaseArgv(string $binary, bool $scpMode = false): array {
        $port = $this->config->port;
        $shkc = $this->config->strictHostKeyChecking;
        $portFlag = $scpMode ? '-P' : '-p';

        $cmd = [
            $binary,
            '-o', "StrictHostKeyChecking={$shkc}",
            '-o', 'ConnectTimeout=10',
            '-o', 'ServerAliveInterval=30',
            $portFlag, (string)$port,
        ];

        $identityKey = $this->config->identityKey;
        $identityFile = $this->config->identityFile;

        if ($identityKey !== '' && $identityFile !== '') {
            fwrite(STDERR, "\033[33mWarning:\033[0m ssh.ini: both identity_key and identity_file set; using identity_key\n");
        }

        if ($identityKey !== '') {
            // Actual tempfile path is resolved at execute-time; in dry-run mode show placeholder
            $cmd[] = '-i';
            $cmd[] = '<REDACTED-tempfile-path-to-be-generated>';
            $cmd[] = '-o';
            $cmd[] = 'IdentitiesOnly=yes';
        } elseif ($identityFile !== '') {
            $cmd[] = '-i';
            $cmd[] = $identityFile;
            $cmd[] = '-o';
            $cmd[] = 'IdentitiesOnly=yes';
        }

        return $cmd;
    }

    /**
     * Execute argv. Handles tempfile creation for identity_key, and both
     * stream (live) and capture modes.
     *
     * @param list<string> $argv
     */
    private function execute(array $argv, array $opts): SshResult {
        $stream = $opts['stream'] ?? false;
        $timeout = $opts['timeout'] ?? 0;

        // Resolve the real identity -i arg (replace placeholder with actual tempfile)
        $tempfile = '';

        if ($this->config->identityKey !== '') {
            $tempfile = $this->writeTempKey($this->config->identityKey);
            $argv = array_map(
                fn ($a) => $a === '<REDACTED-tempfile-path-to-be-generated>' ? $tempfile : $a,
                $argv
            );
        }

        $start = microtime(true);

        try {
            if ($stream) {
                $exitCode = $this->procOpenLive($argv);

                return new SshResult(
                    exitCode: $exitCode,
                    stdout: '',
                    stderr: '',
                    durationMs: (microtime(true) - $start) * 1000,
                    argv: $argv,
                );
            }
            [$exitCode, $stdout, $stderr] = $this->procOpenCapture($argv, $timeout, $opts);

            return new SshResult(
                exitCode: $exitCode,
                stdout: $stdout,
                stderr: $stderr,
                durationMs: (microtime(true) - $start) * 1000,
                argv: $argv,
            );
        } finally {
            if ($tempfile !== '' && file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
    }

    /**
     * Write identityKey contents to a secure tempfile and return its path.
     * @throws SshException
     */
    private function writeTempKey(string $key): string {
        $path = tempnam(sys_get_temp_dir(), 'garnet_ssh_');

        if ($path === false) {
            throw new SshException('SshClient: could not create tempfile for identity key');
        }
        file_put_contents($path, $key);
        // chmod 0600: owner read/write only — required by ssh
        chmod($path, 0o600);

        return $path;
    }

    /**
     * Live mode: connect stdio directly to PHP's STDIN/STDOUT/STDERR.
     * @param list<string> $argv
     * @throws SshException
     */
    private function procOpenLive(array $argv): int {
        $descriptors = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $env = $this->mergedEnv();
        $proc = proc_open($argv, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);

        if ($proc === false) {
            // Fallback for Windows without POSIX layer
            putenv('MSYS_NO_PATHCONV=1');
            $shell = implode(' ', array_map('escapeshellarg', $argv));
            passthru($shell, $code);

            return (int)$code;
        }

        return proc_close($proc);
    }

    /**
     * Capture mode: read stdout/stderr to strings.
     * Supports optional chunk callbacks and timeout.
     *
     * @param list<string>  $argv
     * @return array{int, string, string}  [exitCode, stdout, stderr]
     * @throws SshException
     */
    private function procOpenCapture(array $argv, int $timeout, array $opts): array {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $env = $this->mergedEnv();
        $proc = proc_open($argv, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);

        if ($proc === false) {
            throw new SshException('SshClient: proc_open failed — could not start ssh');
        }

        fclose($pipes[0]); // no stdin needed in capture mode

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = $timeout > 0 ? microtime(true) + $timeout : 0.0;
        $onOut = $opts['onStdout'] ?? null;
        $onErr = $opts['onStderr'] ?? null;

        while (true) {
            $status = proc_get_status($proc);

            if (!$status['running']) {
                // Drain remaining output after process exits
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);

                break;
            }

            if ($deadline > 0.0 && microtime(true) > $deadline) {
                proc_terminate($proc, 15);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);

                throw new SshException("SshClient: timeout after {$timeout}s");
            }

            $chunk1 = fread($pipes[1], 8192);
            $chunk2 = fread($pipes[2], 8192);

            if ($chunk1 !== false && $chunk1 !== '') {
                $stdout .= $chunk1;

                if ($onOut !== null) {
                    ($onOut)($chunk1);
                }
            }

            if ($chunk2 !== false && $chunk2 !== '') {
                $stderr .= $chunk2;

                if ($onErr !== null) {
                    ($onErr)($chunk2);
                }
            }

            usleep(5000); // 5 ms poll interval
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return [$exitCode, $stdout, $stderr];
    }

    private function resolveRemotePath(?string $remote, string $fallbackBasename): string {
        if ($remote === null || $remote === '') {
            return rtrim($this->config->remotePath, '/') . '/' . $fallbackBasename;
        }

        if (!$this->isAbsolutePath($remote)) {
            return rtrim($this->config->remotePath, '/') . '/' . $remote;
        }

        return $remote;
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, '/')
            || (bool)preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    /**
     * Environment for proc_open that suppresses msys2 (Git Bash) automatic
     * POSIX-to-Windows path conversion.  Without this, remote absolute paths
     * like /var/www/… are rewritten to C:/Program Files/Git/var/www/… before
     * they reach scp/ssh.  MSYS_NO_PATHCONV=1 is a no-op outside msys2.
     *
     * @return array<string, string>|null  null on non-Windows (inherit all)
     */
    private function mergedEnv(): ?array {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $env = getenv();
        $env['MSYS_NO_PATHCONV'] = '1';

        return $env;
    }
}
