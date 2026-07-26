<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetDeployFullCommand;
    use ReflectionMethod;

    describe('GarnetDeployFullCommand', function (): void {
        describe('::preflightLayout (via reflection)', function (): void {
            beforeEach(function (): void {
                $this->preflightLayout = new ReflectionMethod(GarnetDeployFullCommand::class, 'preflightLayout');
            });

            // NOTE: ::resolveLayout() is not covered here — it calls
            // GarnetEnv::requireAppName(), which does a raw `echo` + `exit(1)`
            // (not a catchable exception) when no app is active. Asserting
            // that path would kill the whole Kahlan process rather than fail
            // one spec, so it's intentionally left untested at this layer —
            // exercised instead by the real `php garnet deploy:full --help`
            // and scratch-directory SSH runs performed manually.

            it('preflightLayout passes silently when every required key is present', function (): void {
                $layout = [
                    'remote_path' => '/var/www/example/data/www',
                    'public_dir' => 'public',
                    'public_name' => 'myapp',
                    'framework_dir' => 'garnet-framework',
                    'app_dir' => 'garnet-app-myapp',
                    'runtime_dir' => 'garnet-runtime-myapp',
                ];

                // No exception / no exit — reaching this line at all is the
                // assertion; a failing preflight would exit(1) the whole
                // Kahlan process instead of returning here.
                $this->preflightLayout->invoke(null, $layout);
                expect(true)->toBe(true);
            });
        });
    });
}
