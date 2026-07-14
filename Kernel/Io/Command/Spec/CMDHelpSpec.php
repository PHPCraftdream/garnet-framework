<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use ReflectionClass;

    describe('CMDHelp', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(CommandClasses::class);
            $property = $reflection->getProperty('classes');
            $property->setValue(null, []);
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
            it('calls listAllCommands when args is empty', function (): void {
                CommandClasses::set('help', CMDHelp::class);
            });
        });
    });
}
