<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Spec {
    use function array_filter;
    use function basename;
    use function class_exists;
    use function count;

    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use ReflectionClass;
    use ReflectionMethod;

    use function str_replace;
    use function str_starts_with;

    /**
     * Cross-module contract for every Fw*Controller shipped under
     * Bundle/Modules/*. Each one is an ABSTRACT base — the framework
     * never wires concrete controllers itself; the app does that and
     * supplies the DI hooks (table classes, role checks, brand info).
     *
     * The invariants we lock down:
     *   1. The class exists in the documented namespace.
     *   2. It is abstract (mistakenly making it concrete would let apps
     *      register the framework controller as-is and skip the DI).
     *   3. It extends FrameworkController (or a subclass) so the
     *      `renderTwig`, `json`, `internal_error_500` plumbing is available.
     *   4. It declares at least one abstract method (the DI surface the
     *      app must implement).
     *
     * Smoke-level coverage — keeps the contract honest without forcing
     * every controller into the unit suite (most need a real DB + DI to
     * exercise their happy paths).
     */
    describe('Bundle/Modules/Fw*Controller contract', function (): void {
        $controllers = [
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Auth\\Controllers\\FwAccountsController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Balance\\Controllers\\FwBalanceAdminController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Balance\\Controllers\\FwBalanceController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Dashboard\\Controllers\\FwDashboardController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\EntityHistory\\Controllers\\FwEntityHistoryController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\JsErrors\\Controllers\\FwJsErrorLogController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Admin\\Controllers\\FwDashboardLogsController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Mail\\Controllers\\FwDashboardMailLogController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Request\\Controllers\\FwDashboardRequestLogController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Logging\\Viewer\\Controllers\\FwDashboardLogsViewerController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Messaging\\Controllers\\FwImController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\News\\Controllers\\FwNewsController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\StaticPages\\Controllers\\FwStaticPagesAdminController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\StaticPages\\Controllers\\FwStaticPagesPublicController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Controllers\\FwSupportAdminController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\Support\\Controllers\\FwSupportController',
            'PHPCraftdream\\Garnet\\Bundle\\Modules\\SystemSettings\\Controllers\\FwSystemSettingsController',
        ];

        foreach ($controllers as $fqcn) {
            $shortName = basename(str_replace('\\', '/', $fqcn));

            describe($shortName, function () use ($fqcn): void {
                it('exists in the expected namespace', function () use ($fqcn): void {
                    expect(class_exists($fqcn, true))->toBe(true);
                });

                it('is either abstract OR uses static-setter DI (cannot be wired with zero config)', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);

                    // Either way to enforce that an app must do SOMETHING before
                    // the controller is usable: subclass + implement abstract
                    // methods, OR call a static setter to pin the concrete tables.
                    $hasStaticSetter = false;

                    foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $m) {
                        if ($m->getDeclaringClass()->getName() === $rc->getName()
                            && (str_starts_with($m->getName(), 'set') || str_starts_with($m->getName(), 'register'))) {
                            $hasStaticSetter = true;

                            break;
                        }
                    }

                    expect($rc->isAbstract() || $hasStaticSetter)->toBe(true);
                });

                it('extends FrameworkController (directly or transitively)', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->isSubclassOf(FrameworkController::class))->toBe(true);
                });

                it('declares its public DI surface — either abstract methods or static setters', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);

                    // Abstract methods declared on this class (not inherited)
                    $abstractMethods = array_filter(
                        $rc->getMethods(),
                        static fn ($m) => $m->isAbstract() && $m->getDeclaringClass()->getName() === $rc->getName(),
                    );

                    // OR: static setters / register* methods this class introduces.
                    // (e.g. FwJsErrorLogController::setTableClass, FwInviteTokenService::
                    // setTableClasses pattern.)
                    $diSetters = array_filter(
                        $rc->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC),
                        static fn ($m) => $m->getDeclaringClass()->getName() === $rc->getName()
                            && (str_starts_with($m->getName(), 'set') || str_starts_with($m->getName(), 'register')),
                    );

                    expect(count($abstractMethods) + count($diSetters))->toBeGreaterThan(0);
                });
            });
        }
    });
}
