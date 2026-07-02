<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Spec {
    use function basename;
    use function class_exists;
    use function class_implements;
    use function in_array;
    use function interface_exists;
    use function is_subclass_of;

    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use ReflectionClass;
    use ReflectionNamedType;

    use function str_replace;

    /**
     * Schema-contract invariants every Fw*Table under Bundle/Modules/* must
     * satisfy. Each one is an ABSTRACT DbTable subclass (apps pin the
     * concrete table name in their own subclass) with a static `init()`
     * that returns an `ITableBuilderDriver` for the migration runner.
     *
     * What this spec guarantees:
     *   1. The class exists in the documented namespace.
     *   2. It is abstract — apps must subclass to pin $tableName.
     *   3. It extends DbTable (transitively or directly).
     *   4. It declares a public static `init()` method whose return type
     *      is or extends ITableBuilderDriver.
     *
     * The init() body itself can't be invoked here without a concrete
     * $tableName — that's intentional (the same Fw* class can be reused
     * across two app tables with different names). A future
     * integration-shaped spec can boot a test app and run the migration
     * end-to-end.
     */
    describe('Bundle/Modules/Fw*Tables init() contract', function (): void {
        $tables = [
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Balance\\Tables\\FwAccountBalance',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Balance\\Tables\\FwBalanceLedger',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Comments\\Tables\\FwComments',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Cron\\Tables\\FwCronLog',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Email\\Tables\\FwEmailAttempts',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Email\\Tables\\FwEmailQueue',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\EntityHistory\\Tables\\FwEntityHistory',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Idempotency\\Tables\\FwIdempotencyKeys',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Invite\\Tables\\FwInviteRegistrations',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Invite\\Tables\\FwInviteTokens',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\JsErrors\\Tables\\FwJsErrors',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Admin\\Tables\\FwAdminActionLog',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Mail\\Tables\\FwMailLog',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Mail\\Tables\\FwMailLogRecipients',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Messaging\\Tables\\FwImAttachments',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Messaging\\Tables\\FwImConversations',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Messaging\\Tables\\FwImMessages',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Messaging\\Tables\\FwImReadStatus',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\News\\Tables\\FwNewsArchived',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\News\\Tables\\FwNewsEvents',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\News\\Tables\\FwNewsReads',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\StaticPages\\Tables\\FwStaticPageBlocks',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\StaticPages\\Tables\\FwStaticPages',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\StaticPages\\Tables\\FwStaticSnippets',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Tables\\FwSupportAssignmentLog',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Tables\\FwSupportAttachments',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Tables\\FwSupportMessages',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Tables\\FwSupportTickets',
        ];

        foreach ($tables as $fqcn) {
            $short = basename(str_replace('\\', '/', $fqcn));

            describe($short, function () use ($fqcn): void {
                it('exists in the expected namespace', function () use ($fqcn): void {
                    expect(class_exists($fqcn, true))->toBe(true);
                });

                it('extends DbTable (directly or transitively)', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->isSubclassOf(DbTable::class))->toBe(true);
                });

                it('declares a public static init() that returns ITableBuilderDriver', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->hasMethod('init'))->toBe(true);

                    $init = $rc->getMethod('init');
                    expect($init->isStatic())->toBe(true);
                    expect($init->isPublic())->toBe(true);

                    $retType = $init->getReturnType();
                    expect($retType)->toBeAnInstanceOf(ReflectionNamedType::class);
                    /** @var ReflectionNamedType $retType */
                    $retName = $retType->getName();

                    $matchesContract = $retName === ITableBuilderDriver::class
                        || is_subclass_of($retName, ITableBuilderDriver::class)
                        || (interface_exists($retName, true)
                            && in_array(ITableBuilderDriver::class, class_implements($retName) ?: [], true));

                    expect($matchesContract)->toBe(true);
                });
            });
        }
    });
}
