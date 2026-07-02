<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Command {
    use ReflectionClass;
    use stdClass;

    describe('CommandClasses', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(CommandClasses::class);
            $property = $reflection->getProperty('classes');
            $property->setAccessible(true);
            $property->setValue([]);
        });

        afterEach(function (): void {
            $reflection = new ReflectionClass(CommandClasses::class);
            $property = $reflection->getProperty('classes');
            $property->setAccessible(true);
            $property->setValue([]);
        });

        describe('::set() and ::get()', function (): void {
            it('stores and retrieves command class', function (): void {
                CommandClasses::set('test', CMDHelp::class);

                $className = CommandClasses::get('test');
                expect($className)->toBe(CMDHelp::class);
            });

            it('throws exception for non-existent class', function (): void {
                $expect = expect(function (): void {
                    CommandClasses::set('test', 'NonExistentClass');
                });

                $expect->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\CommandException('Unknown class (#1): NonExistentClass'));
            });

            it('throws exception for class not implementing ICommand', function (): void {
                $expect = expect(function (): void {
                    CommandClasses::set('test', stdClass::class);
                });

                $expect->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\CommandException());
            });

            it('throws exception when getting non-existent command', function (): void {
                $expect = expect(function (): void {
                    CommandClasses::get('nonexistent');
                });

                $expect->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\CommandException('Command not found: nonexistent'));
            });

            it('allows registering multiple commands', function (): void {
                CommandClasses::set('cmd1', CMDHelp::class);
                CommandClasses::set('cmd2', CMDHelp::class);

                expect(CommandClasses::get('cmd1'))->toBe(CMDHelp::class);
                expect(CommandClasses::get('cmd2'))->toBe(CMDHelp::class);
            });

            it('overwrites existing command', function (): void {
                CommandClasses::set('test', CMDHelp::class);

                $testCommand = new class() implements \PHPCraftdream\Garnet\Kernel\Interfaces\ICommand {
                    public static function description(): string {
                        return 'Test';
                    }

                    public static function help(array $args, \Aura\Cli\Context $context, \Aura\Cli\Stdio $stdio): void {
                    }

                    public static function run(array $args, \Aura\Cli\Context $context, \Aura\Cli\Stdio $stdio): void {
                    }
                };

                CommandClasses::set('test', get_class($testCommand));

                expect(CommandClasses::get('test'))->toBe(get_class($testCommand));
            });
        });

        describe('::all()', function (): void {
            it('returns empty array when no commands registered', function (): void {
                $commands = CommandClasses::all();
                expect($commands)->toBe([]);
            });

            it('returns all registered commands', function (): void {
                CommandClasses::set('cmd1', CMDHelp::class);
                CommandClasses::set('cmd2', CMDHelp::class);

                $commands = CommandClasses::all();

                expect(count($commands))->toBe(2);
                expect($commands['cmd1'])->toBe(CMDHelp::class);
                expect($commands['cmd2'])->toBe(CMDHelp::class);
            });

            it('updates when new commands are added', function (): void {
                CommandClasses::set('cmd1', CMDHelp::class);

                $commands1 = CommandClasses::all();
                expect(count($commands1))->toBe(1);

                CommandClasses::set('cmd2', CMDHelp::class);

                $commands2 = CommandClasses::all();
                expect(count($commands2))->toBe(2);
            });
        });
    });
}
