<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use Aura\Cli\CliFactory;
    use Aura\Cli\Stdio;
    use ReflectionClass;

    describe('CMDHelp', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(CommandClasses::class);
            $property = $reflection->getProperty('classes');
            $property->setValue(null, []);

            // Create Stdio with in-memory streams for output capture
            $factory = new CliFactory();
            $this->stdio = $factory->newStdio('php://stdin', 'php://temp', 'php://temp');

            // Helper to read Stdio output via reflection
            $readStdout = function (Stdio $stdio): string {
                $rc = new ReflectionClass($stdio);
                $stdoutHandle = $rc->getProperty('stdout');
                $handle = $stdoutHandle->getValue($stdio);

                $rh = new ReflectionClass($handle);
                $rp = $rh->getProperty('resource');
                $resource = $rp->getValue($handle);

                rewind($resource);

                return stream_get_contents($resource) ?: '';
            };
            $this->readStdout = $readStdout;
        });

        afterEach(function (): void {
            $reflection = new ReflectionClass(CommandClasses::class);
            $property = $reflection->getProperty('classes');
            $property->setValue(null, []);
        });

        describe('::description()', function (): void {
            it('returns correct description', function (): void {
                $description = CMDHelp::description();
                expect($description)->toBe('Prints list of commands');
            });
        });

        describe('::help()', function (): void {
            it('lists all registered commands when args is empty', function (): void {
                CommandClasses::set('help', CMDHelp::class);
                CommandClasses::set('test1', CMDHelp::class);
                CommandClasses::set('test2', CMDHelp::class);

                $factory = new CliFactory();
                $context = $factory->newContext($GLOBALS);
                CMDHelp::help([], $context, $this->stdio);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('help');
                expect($out)->toContain('test1');
                expect($out)->toContain('test2');
                expect($out)->toContain('Prints list of commands');
            });
        });
    });
}
