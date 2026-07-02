<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh\Spec {
    use function array_merge;
    use function array_search;
    use function end;

    use PHPCraftdream\Garnet\Kernel\Exceptions\SshException;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshConfig;
    use Throwable;

    describe('SshClient', function (): void {
        $newClient = function (array $overrides = []): SshClient {
            $defaults = [
                'host' => 'host.example',
                'user' => 'deployer',
                'port' => 22,
                'identityFile' => '',
                'identityKey' => '',
                'remotePath' => '/var/www',
                'strictHostKeyChecking' => 'accept-new',
            ];
            $config = new SshConfig(...array_merge($defaults, $overrides));

            return new SshClient($config);
        };
        $this->newClient = $newClient;

        describe('::validate', function (): void {
            it('throws SshException when host is empty', function (): void {
                $c = ($this->newClient)(['host' => '']);
                $ex = null;

                try {
                    $c->validate();
                } catch (SshException $e) {
                    $ex = $e;
                }
                expect($ex)->toBeAnInstanceOf(SshException::class);
                expect($ex->getMessage())->toContain('host');
            });

            it('throws SshException when user is empty', function (): void {
                $c = ($this->newClient)(['user' => '']);
                $ex = null;

                try {
                    $c->validate();
                } catch (SshException $e) {
                    $ex = $e;
                }
                expect($ex)->toBeAnInstanceOf(SshException::class);
                expect($ex->getMessage())->toContain('user');
            });

            it('does NOT throw when host + user are present', function (): void {
                $c = ($this->newClient)();
                $ex = null;

                try {
                    $c->validate();
                } catch (Throwable $e) {
                    $ex = $e;
                }
                expect($ex)->toBeNull();
            });
        });

        describe('::buildRunArgv', function (): void {
            it('emits ssh + StrictHostKeyChecking from config + the user@host destination', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls -la');

                expect($argv[0])->toBe('ssh');
                expect($argv)->toContain('StrictHostKeyChecking=accept-new');
                expect($argv)->toContain('ConnectTimeout=10');
                expect($argv)->toContain('ServerAliveInterval=30');
                expect($argv)->toContain('deployer@host.example');
                expect(end($argv))->toBe('ls -la');
            });

            it('uses ssh lowercase -p flag with the configured port', function (): void {
                $argv = ($this->newClient)(['port' => 2222])->buildRunArgv('echo hi');
                $pIndex = array_search('-p', $argv, true);
                expect($pIndex)->not->toBe(false);
                expect($argv[$pIndex + 1])->toBe('2222');
            });

            it('adds -t when tty=true', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls', ['tty' => true]);
                expect($argv)->toContain('-t');
            });

            it('adds -T (no-tty) by default for capture mode', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls');
                expect($argv)->toContain('-T');
                expect($argv)->not->toContain('-t');
            });

            it('adds -v when verbose=true', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls', ['verbose' => true]);
                expect($argv)->toContain('-v');
            });

            it('honours --cd-remote: wraps the command in cd remotePath && ( cmd )', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls', ['cd_remote' => true]);
                $last = end($argv);
                expect($last)->toBe("cd '/var/www' && ( ls )");
            });

            it('throws SshException when --cd-remote is used and remote_path is empty', function (): void {
                $c = ($this->newClient)(['remotePath' => '']);
                $ex = null;

                try {
                    $c->buildRunArgv('ls', ['cd_remote' => true]);
                } catch (SshException $e) {
                    $ex = $e;
                }
                expect($ex)->toBeAnInstanceOf(SshException::class);
                expect($ex->getMessage())->toContain('remote_path');
            });

            it('honours explicit cwd', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls', ['cwd' => '/opt/app']);
                $last = end($argv);
                expect($last)->toBe("cd '/opt/app' && ( ls )");
            });

            it('shell-escapes single quotes inside cwd', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('ls', ['cwd' => "/op't/x"]);
                $last = end($argv);
                expect($last)->toContain("cd '/op'\\''t/x'");
            });

            it('prepends env exports (escapes single quotes in the value)', function (): void {
                $argv = ($this->newClient)()->buildRunArgv('cmd', [
                    'env' => ['FOO=bar', "QUOTED=it's"],
                ]);
                $last = end($argv);
                expect($last)->toContain("export FOO='bar';");
                expect($last)->toContain("export QUOTED='it'\\''s';");
            });

            it('adds -i + IdentitiesOnly when identityFile is set', function (): void {
                $argv = ($this->newClient)(['identityFile' => '/home/.ssh/id'])->buildRunArgv('ls');
                $iIndex = array_search('-i', $argv, true);
                expect($iIndex)->not->toBe(false);
                expect($argv[$iIndex + 1])->toBe('/home/.ssh/id');
                expect($argv)->toContain('IdentitiesOnly=yes');
            });

            it('uses REDACTED placeholder when identityKey is set (real path resolved at execute)', function (): void {
                $argv = ($this->newClient)(['identityKey' => "-----BEGIN KEY-----\nMATERIAL\n"])->buildRunArgv('ls');
                expect($argv)->toContain('<REDACTED-tempfile-path-to-be-generated>');
                expect($argv)->toContain('IdentitiesOnly=yes');
            });
        });

        describe('::buildPutArgv', function (): void {
            it('emits scp + uppercase -P port + destination user@host:remote', function (): void {
                $argv = ($this->newClient)()->buildPutArgv('local.txt', '/var/www/dest.txt');

                expect($argv[0])->toBe('scp');
                expect($argv)->toContain('-P');
                expect($argv)->toContain('22');
                expect($argv)->toContain('local.txt');
                expect($argv)->toContain('deployer@host.example:/var/www/dest.txt');
            });

            it('defaults remote to remotePath/<basename(local)>', function (): void {
                $argv = ($this->newClient)()->buildPutArgv('/tmp/file.txt');
                $dest = end($argv);
                expect($dest)->toBe('deployer@host.example:/var/www/file.txt');
            });

            it('adds -v when verbose', function (): void {
                $argv = ($this->newClient)()->buildPutArgv('a', '/b', ['verbose' => true]);
                expect($argv)->toContain('-v');
            });
        });

        describe('::buildGetArgv', function (): void {
            it('emits scp + source user@host:remote + local destination', function (): void {
                $argv = ($this->newClient)()->buildGetArgv('/var/www/log.txt', '/tmp/log.txt');

                expect($argv[0])->toBe('scp');
                expect($argv)->toContain('deployer@host.example:/var/www/log.txt');
                expect(end($argv))->toBe('/tmp/log.txt');
            });

            it('prepends remotePath when remote is not absolute', function (): void {
                $argv = ($this->newClient)()->buildGetArgv('relative/path.log');
                $sourceIndex = array_search('deployer@host.example:/var/www/relative/path.log', $argv, true);
                expect($sourceIndex)->not->toBe(false);
            });

            it('defaults local to basename(remote) when omitted', function (): void {
                $argv = ($this->newClient)()->buildGetArgv('/abs/path/some.log');
                expect(end($argv))->toBe('some.log');
            });
        });
    });
}
