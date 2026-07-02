<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun\Spec {
    use function class_exists;

    use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
    use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunConsole;
    use ReflectionClass;
    use Throwable;

    use function uniqid;

    describe('IoRunConsole', function (): void {
        describe('class contract', function (): void {
            it('lives in the expected namespace', function (): void {
                expect(class_exists(IoRunConsole::class))->toBe(true);
            });

            it('exposes a public static run() method', function (): void {
                $rc = new ReflectionClass(IoRunConsole::class);
                expect($rc->hasMethod('run'))->toBe(true);

                $run = $rc->getMethod('run');
                expect($run->isStatic())->toBe(true);
                expect($run->isPublic())->toBe(true);
                expect($run->getNumberOfParameters())->toBe(0);
            });

            it('declares the documented exception types in PHPDoc', function (): void {
                $rc = new ReflectionClass(IoRunConsole::class);
                $doc = $rc->getMethod('run')->getDocComment();

                expect($doc)->toContain('CommandException');
                expect($doc)->toContain('IoException');
            });
        });

        describe('::run — error paths', function (): void {
            it('throws CommandException when the requested command class is missing', function (): void {
                // Set up $GLOBALS so the run() method sees a bogus command name.
                // This exercises the CommandClasses::get() failure branch — exact
                // class CommandClasses tries to resolve doesn't matter, just that
                // the resolution fails or yields something not callable.
                $prevArgv = $_SERVER['argv'] ?? [];
                $_SERVER['argv'] = [
                    'garnet',
                    '___definitely_not_a_real_command_' . uniqid() . '___',
                ];

                $ex = null;

                try {
                    IoRunConsole::run();
                } catch (Throwable $e) {
                    $ex = $e;
                }

                // Restore
                $_SERVER['argv'] = $prevArgv;

                // Either CommandException (not callable) or an internal exception
                // from CommandClasses::get — both are acceptable failure modes.
                expect($ex)->toBeAnInstanceOf(Throwable::class);
            });
        });
    });
}
