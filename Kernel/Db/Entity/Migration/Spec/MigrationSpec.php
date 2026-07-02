<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Spec {
    use Aura\Cli\Stdio;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\Migration;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Migration\IMigration;
    use ReflectionClass;

    describe('Migration', function (): void {
        beforeEach(function (): void {
            $reflection = new ReflectionClass(Migration::class);
            $instanceProperty = $reflection->getProperty('instance');
            $instanceProperty->setAccessible(true);
            $instanceProperty->setValue(null);
        });

        describe('get()', function (): void {
            it('returns singleton instance', function (): void {
                $migration1 = Migration::get();
                $migration2 = Migration::get();

                expect($migration1)->toBe($migration2);
            });

            it('returns instance implementing IMigration', function (): void {
                $migration = Migration::get();

                expect($migration)->toBeAnInstanceOf(IMigration::class);
            });
        });

        describe('getCurrentVersion()', function (): void {
            it('returns default current version', function (): void {
                $migration = Migration::get();
                $version = $migration->getCurrentVersion();

                expect($version)->toBe(1);
            });

            it('returns current version after modification', function (): void {
                $migration = Migration::get();

                $reflection = new ReflectionClass($migration);
                $currentVersionProperty = $reflection->getProperty('currentVersion');
                $currentVersionProperty->setAccessible(true);
                $currentVersionProperty->setValue($migration, 5);

                $version = $migration->getCurrentVersion();

                expect($version)->toBe(5);
            });
        });

        describe('migrationClasses', function (): void {
            it('initializes with empty migration classes', function (): void {
                $migration = Migration::get();

                $reflection = new ReflectionClass($migration);
                $migrationClassesProperty = $reflection->getProperty('migrationClasses');
                $migrationClassesProperty->setAccessible(true);
                $migrationClasses = $migrationClassesProperty->getValue($migration);

                expect($migrationClasses)->toBe([]);
            });

            it('can be modified via reflection', function (): void {
                $migration = Migration::get();

                $reflection = new ReflectionClass($migration);
                $migrationClassesProperty = $reflection->getProperty('migrationClasses');
                $migrationClassesProperty->setAccessible(true);
                $migrationClassesProperty->setValue($migration, [2 => 'TestClass']);

                $migrationClasses = $migrationClassesProperty->getValue($migration);

                expect($migrationClasses)->toBe([2 => 'TestClass']);
            });
        });

        describe('Initial properties', function (): void {
            it('has protected currentVersion property', function (): void {
                $migration = Migration::get();
                $reflection = new ReflectionClass($migration);
                expect($reflection->hasProperty('currentVersion'))->toBe(true);
            });

            it('has protected migrationClasses property', function (): void {
                $migration = Migration::get();
                $reflection = new ReflectionClass($migration);
                expect($reflection->hasProperty('migrationClasses'))->toBe(true);
            });

            it('has protected static instance property', function (): void {
                $reflection = new ReflectionClass(Migration::class);
                expect($reflection->hasProperty('instance'))->toBe(true);
                expect($reflection->getProperty('instance')->isStatic())->toBe(true);
            });
        });

        describe('Constructor', function (): void {
            it('is protected', function (): void {
                $reflection = new ReflectionClass(Migration::class);
                $constructor = $reflection->getConstructor();
                expect($constructor->isProtected())->toBe(true);
            });
        });

        describe('migrate()', function (): void {
            it('is a method that accepts Stdio parameter', function (): void {
                $migration = Migration::get();
                $reflection = new ReflectionClass($migration);
                $method = $reflection->getMethod('migrate');

                expect($method->getName())->toBe('migrate');
                expect($method->getNumberOfParameters())->toBe(1);
                expect($method->getParameters()[0]->getType()->getName())->toBe(Stdio::class);
            });
        });
    });
}
