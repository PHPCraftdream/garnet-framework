<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Ssh\Spec {
    use Error;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshResult;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshTestResult;

    describe('SshResult', function (): void {
        it('captures every constructor field as readonly', function (): void {
            $r = new SshResult(
                exitCode: 0,
                stdout: 'hello',
                stderr: '',
                durationMs: 123.4,
                argv: ['ssh', '-p', '22', 'host'],
            );

            expect($r->exitCode)->toBe(0);
            expect($r->stdout)->toBe('hello');
            expect($r->stderr)->toBe('');
            expect($r->durationMs)->toBe(123.4);
            expect($r->argv)->toBe(['ssh', '-p', '22', 'host']);
        });

        it('ok() returns true exactly when exitCode === 0', function (): void {
            $zero = new SshResult(0, '', '', 1.0, []);
            $one = new SshResult(1, '', 'err', 1.0, []);
            $minus = new SshResult(-1, '', '', 1.0, []);

            expect($zero->ok())->toBe(true);
            expect($one->ok())->toBe(false);
            expect($minus->ok())->toBe(false);
        });

        it('fields are readonly (immutable post-construction)', function (): void {
            $r = new SshResult(0, '', '', 0.0, []);
            $ex = null;

            try {
                // @phpstan-ignore-next-line — intentional write to readonly
                $r->exitCode = 1;
            } catch (Error $e) {
                $ex = $e;
            }
            expect($ex)->toBeAnInstanceOf(Error::class);
        });
    });

    describe('SshTestResult', function (): void {
        it('captures ok / pwd / whoami + the underlying raw SshResult', function (): void {
            $raw = new SshResult(0, "/var/www\ndeployer\n", '', 50.0, ['ssh']);
            $t = new SshTestResult(
                ok: true,
                pwd: '/var/www',
                whoami: 'deployer',
                raw: $raw,
            );

            expect($t->ok)->toBe(true);
            expect($t->pwd)->toBe('/var/www');
            expect($t->whoami)->toBe('deployer');
            expect($t->raw)->toBe($raw);
        });

        it('represents a failed test (ok=false) without forcing pwd/whoami to be empty', function (): void {
            $raw = new SshResult(255, '', 'Permission denied', 1500.0, ['ssh']);
            $t = new SshTestResult(false, '', '', $raw);

            expect($t->ok)->toBe(false);
            expect($t->raw->ok())->toBe(false);
        });
    });
}
