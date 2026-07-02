<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh\Spec {
    use Error;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshConfig;

    describe('SshConfig', function (): void {
        describe('constructor + defaults', function (): void {
            it('captures the minimal connection params and applies sane defaults', function (): void {
                $c = new SshConfig(host: 'example.com', user: 'deployer');

                expect($c->host)->toBe('example.com');
                expect($c->user)->toBe('deployer');
                expect($c->port)->toBe(22);
                expect($c->identityFile)->toBe('');
                expect($c->identityKey)->toBe('');
                expect($c->remotePath)->toBe('');
                expect($c->strictHostKeyChecking)->toBe('accept-new');
            });

            it('honours every named arg when supplied', function (): void {
                $c = new SshConfig(
                    host: 'h',
                    user: 'u',
                    port: 2222,
                    identityFile: '/etc/ssh/id',
                    identityKey: 'KEY-MATERIAL',
                    remotePath: '/var/www',
                    strictHostKeyChecking: 'yes',
                );

                expect($c->port)->toBe(2222);
                expect($c->identityFile)->toBe('/etc/ssh/id');
                expect($c->identityKey)->toBe('KEY-MATERIAL');
                expect($c->remotePath)->toBe('/var/www');
                expect($c->strictHostKeyChecking)->toBe('yes');
            });

            it('properties are readonly', function (): void {
                $c = new SshConfig(host: 'h', user: 'u');
                $ex = null;

                try {
                    // @phpstan-ignore-next-line — intentional write to readonly
                    $c->host = 'other';
                } catch (Error $e) {
                    $ex = $e;
                }
                expect($ex)->toBeAnInstanceOf(Error::class);
            });
        });

        describe('::with — immutable update', function (): void {
            it('returns a NEW instance with one field changed (immutability)', function (): void {
                $c1 = new SshConfig(host: 'a', user: 'u', port: 22);
                $c2 = $c1->with('port', 2222);

                expect($c2)->not->toBe($c1);
                expect($c1->port)->toBe(22);    // original untouched
                expect($c2->port)->toBe(2222);
                expect($c2->host)->toBe('a');   // other fields carried
                expect($c2->user)->toBe('u');
            });

            it('supports updating identityFile, identityKey, remotePath, strictHostKeyChecking', function (): void {
                $c = new SshConfig(host: 'h', user: 'u');

                expect($c->with('identityFile', '/key')->identityFile)->toBe('/key');
                expect($c->with('identityKey', 'INLINE')->identityKey)->toBe('INLINE');
                expect($c->with('remotePath', '/var/www')->remotePath)->toBe('/var/www');
                expect($c->with('strictHostKeyChecking', 'no')->strictHostKeyChecking)->toBe('no');
            });
        });
    });
}
