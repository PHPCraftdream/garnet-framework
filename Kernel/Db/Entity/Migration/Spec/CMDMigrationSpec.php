<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Spec {
    use Kahlan\Plugin\Double;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\CMDMigration;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigration;
    use ReflectionClass;

    describe('CMDMigration', function (): void {
        describe('getMigrationClass()', function (): void {
            it('returns default Migration class', function (): void {
                $result = CMDMigration::getMigrationClass();

                expect($result)->toBe('PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Migration');
            });
        });

        describe('setMigrationClass()', function (): void {
            it('sets a new migration class', function (): void {
                $reflection = new ReflectionClass(CMDMigration::class);
                $migrationClassProp = $reflection->getProperty('migrationClass');
                $migrationClassProp->setAccessible(true);

                // Create a mock migration class that implements IMigration
                $mockMigrationClass = 'MockMigration_' . uniqid();
                $mockMigration = Double::classname(['implements' => IMigration::class, 'class' => $mockMigrationClass]);

                CMDMigration::setMigrationClass($mockMigrationClass);

                $result = $migrationClassProp->getValue(null);

                expect($result)->toBe($mockMigrationClass);

                // Reset to default
                $migrationClassProp->setValue(null, 'PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Migration');
            });

            it('throws exception when class does not implement IMigration', function (): void {
                expect(function (): void {
                    CMDMigration::setMigrationClass('stdClass');
                })->toThrow();
            });
        });

        describe('description()', function (): void {
            it('returns description string', function (): void {
                $result = CMDMigration::description();

                expect($result)->toBe('Migration tool');
            });
        });

        describe('$commands static property', function (): void {
            it('has defined commands array', function (): void {
                $reflection = new ReflectionClass(CMDMigration::class);
                $commandsProp = $reflection->getProperty('commands');
                $commandsProp->setAccessible(true);
                $commands = $commandsProp->getValue(null);

                expect($commands)->toBeA('array');
                expect(array_key_exists('init', $commands))->toBe(true);
                expect(array_key_exists('version-db', $commands))->toBe(true);
                expect(array_key_exists('version-fs', $commands))->toBe(true);
                expect(array_key_exists('migrate', $commands))->toBe(true);
            });

            it('has correct command descriptions', function (): void {
                $reflection = new ReflectionClass(CMDMigration::class);
                $commandsProp = $reflection->getProperty('commands');
                $commandsProp->setAccessible(true);
                $commands = $commandsProp->getValue(null);

                expect($commands['init'])->toBe('Init the migration tracker table');
                expect($commands['version-db'])->toBe('Migration version of database');
                expect($commands['version-fs'])->toBe('Migration version of filesystem');
                expect($commands['migrate'])->toBe('Update database (default action when no sub-command given)');
            });
        });
    });
}
