<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Spec {
    use function basename;
    use function class_exists;
    use function class_implements;
    use function count;
    use function in_array;
    use function interface_exists;
    use function is_subclass_of;

    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\AccountEntity;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\MigrationTable;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionData;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionDataTable;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionTable;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\SettingsTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;
    use ReflectionMethod;
    use ReflectionNamedType;
    use ReflectionProperty;

    use function str_ends_with;
    use function str_replace;

    /**
     * Kernel-level Db entities & tables: the migration runner, session,
     * settings, and account tables. These ship as part of the framework
     * core (not under Bundle/Modules) and need the same schema contract
     * as the bundle tables.
     */
    describe('Kernel/Db/Entity contract', function (): void {
        // Tables — DbTable subclass + static init(): ITableBuilderDriver
        $tables = [
            DbAccount::class,
            MigrationTable::class,
            SessionDataTable::class,
            SessionTable::class,
            SettingsTable::class,
        ];

        foreach ($tables as $fqcn) {
            $short = basename(str_replace('\\', '/', $fqcn));

            describe($short . ' (DbTable)', function () use ($fqcn): void {
                it('exists', function () use ($fqcn): void {
                    expect(class_exists($fqcn, true))->toBe(true);
                });

                it('extends DbTable', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->isSubclassOf(DbTable::class))->toBe(true);
                });

                it('declares public static init(): ITableBuilderDriver', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->hasMethod('init'))->toBe(true);

                    $init = $rc->getMethod('init');
                    expect($init->isStatic())->toBe(true);
                    expect($init->isPublic())->toBe(true);

                    $retType = $init->getReturnType();
                    expect($retType)->toBeAnInstanceOf(ReflectionNamedType::class);
                    /** @var ReflectionNamedType $retType */
                    $retName = $retType->getName();

                    $matches = $retName === ITableBuilderDriver::class
                        || is_subclass_of($retName, ITableBuilderDriver::class)
                        || (interface_exists($retName, true)
                            && in_array(ITableBuilderDriver::class, class_implements($retName) ?: [], true));
                    expect($matches)->toBe(true);
                });
            });
        }

        // ── AccountEntity — BaseEntity subclass, not a table ───────────

        describe('AccountEntity (BaseEntity)', function (): void {
            it('exists', function (): void {
                expect(class_exists(AccountEntity::class, true))->toBe(true);
            });

            it('is concrete (instantiable when wired)', function (): void {
                $rc = new ReflectionClass(AccountEntity::class);
                expect($rc->isAbstract())->toBe(false);
            });

            it('extends the framework BaseEntity (carries Active Record / Unit of Work)', function (): void {
                $rc = new ReflectionClass(AccountEntity::class);
                $parent = $rc->getParentClass();
                expect($parent)->not->toBe(false);
                /** @var ReflectionClass $parent */
                expect(str_ends_with($parent->getName(), 'BaseEntity'))->toBe(true);
            });
        });

        // ── SessionData — plain value object ───────────────────────────

        describe('SessionData (value object)', function (): void {
            it('exists', function (): void {
                expect(class_exists(SessionData::class, true))->toBe(true);
            });

            it('has at least one public property or accessor', function (): void {
                $rc = new ReflectionClass(SessionData::class);
                $publicProps = $rc->getProperties(ReflectionProperty::IS_PUBLIC);
                $publicMethods = $rc->getMethods(ReflectionMethod::IS_PUBLIC);

                expect(count($publicProps) + count($publicMethods))->toBeGreaterThan(0);
            });
        });
    });
}
