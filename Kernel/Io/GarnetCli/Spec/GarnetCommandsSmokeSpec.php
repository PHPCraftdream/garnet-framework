<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function class_exists;
    use function define;
    use function defined;

    use const DIRECTORY_SEPARATOR;

    use function dirname;

    use ReflectionClass;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    /**
     * Smoke-level invariants every Garnet*Command must satisfy:
     *
     *   1. The class exists and lives in the expected namespace.
     *   2. It exposes a public static run(...) entry point (the signature
     *      varies — some take (array $args), some (string $command, array $args),
     *      some no args at all).
     *
     * These are the bare-minimum protections against accidental deletion or
     * renaming. Deeper coverage for the specific helper methods lives in the
     * per-command specs alongside this file (e.g. GarnetAppCommandHelpersSpec,
     * GarnetServeCommandHelpersSpec, etc.).
     */
    describe('Garnet*Command smoke contract', function (): void {
        $commands = [
            'GarnetAdminCommand',
            'GarnetAppCommand',
            'GarnetBuildCheckCommand',
            'GarnetBuildCommand',
            'GarnetCacheCommand',
            'GarnetConfigCommand',
            'GarnetDbBackupCommand',
            'GarnetDbWipeCommand',
            'GarnetDeployCommand',
            'GarnetMaintenanceCommand',
            'GarnetMaintenanceRemoteCommand',
            'GarnetMigrateStatusCommand',
            'GarnetPermsCommand',
            'GarnetPrepareCommand',
            'GarnetServeCommand',
            'GarnetServeDebugCommand',
            'GarnetServeWatchCommand',
            'GarnetSnapshotCommand',
            'GarnetSqlCommand',
            'GarnetSshCommand',
            'GarnetTestRemoteCommand',
            'GarnetUninstallCommand',
        ];

        foreach ($commands as $cmd) {
            $fqcn = "PHPCraftdream\\Garnet\\Kernel\\Io\\GarnetCli\\{$cmd}";

            describe($cmd, function () use ($fqcn): void {
                it('exists in the expected namespace', function () use ($fqcn): void {
                    expect(class_exists($fqcn))->toBe(true);
                });

                it('exposes a public static `run` method', function () use ($fqcn): void {
                    $rc = new ReflectionClass($fqcn);
                    expect($rc->hasMethod('run'))->toBe(true);

                    $run = $rc->getMethod('run');
                    expect($run->isStatic())->toBe(true);
                    expect($run->isPublic())->toBe(true);
                });
            });
        }
    });
}
