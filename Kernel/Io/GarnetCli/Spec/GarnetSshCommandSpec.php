<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetSshCommand;
    use ReflectionMethod;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    /**
     * Targeted coverage of testable helper methods in GarnetSshCommand.
     * The top-level run() entry points end with exit(), so the helpers
     * are the testable pure logic.
     */
    describe('GarnetSshCommand helpers', function (): void {
        // ── fail() ───────────────────────────────────────────────────────

        describe('fail()', function (): void {
            it('exists, is private, static, and returns never', function (): void {
                $m = new ReflectionMethod(GarnetSshCommand::class, 'fail');
                expect($m->isPrivate())->toBe(true);
                expect($m->isStatic())->toBe(true);

                // The method should have return type 'never'
                $returnType = $m->getReturnType();
                expect($returnType?->getName())->toBe('never');

                // It should take exactly one string parameter
                $params = $m->getParameters();
                expect(count($params))->toBe(1);
                expect($params[0]->getType()?->getName())->toBe('string');
            });
        });

        // ── runtimeDir() ─────────────────────────────────────────────────

        describe('runtimeDir()', function (): void {
            it('exists, is private, static, and returns string', function (): void {
                $m = new ReflectionMethod(GarnetSshCommand::class, 'runtimeDir');
                expect($m->isPrivate())->toBe(true);
                expect($m->isStatic())->toBe(true);
                expect($m->getReturnType()?->getName())->toBe('string');
            });

            it('does NOT silently return empty string on missing deploy.ini (contract change)', function (): void {
                // Before the fix: runtimeDir() caught Throwable and returned ''
                // After the fix: runtimeDir() calls self::fail() which exits(1)

                // We can't test the actual exit behavior without killing the
                // Kahlan process, but we can verify the method signature and
                // that it no longer has the silent return pattern.

                // The key behavioral change is that the catch block no longer
                // returns ''; instead it calls self::fail(). We verify this
                // by inspecting the source.

                $source = file_get_contents(__DIR__ . '/../GarnetSshCommand.php');

                // The method should NOT have "return ''" in the catch block anymore
                $pattern = '/catch\s*\(\s*Throwable\s*\)\s*\{[^}]*return\s*\'\'/s';
                expect(preg_match($pattern, $source))->toBe(0);

                // The method SHOULD call self::fail() with the expected message
                expect(str_contains($source, "self::fail('deploy.ini must define remote_path and runtime_dir.')"))->toBe(true);
            });
        });
    });
}
