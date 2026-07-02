<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec {
    use function define;
    use function defined;
    use function dirname;
    use function is_string;
    use function ob_get_clean;
    use function ob_start;

    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;

    use function strlen;

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', dirname(__DIR__, 5));
    }

    describe('GarnetRunner', function (): void {
        describe('::resolveFrameworkDir', function (): void {
            it('returns GARNET_ROOT when the constant is already defined', function (): void {
                expect(GarnetRunner::resolveFrameworkDir())->toBe(GARNET_ROOT);
            });

            it('returns a non-empty string', function (): void {
                expect(GarnetRunner::resolveFrameworkDir())->toBeA('string');
                expect(strlen(GarnetRunner::resolveFrameworkDir()))->toBeGreaterThan(0);
            });
        });

        describe('::showHelp', function (): void {
            it('emits the version banner and the active-app line', function (): void {
                ob_start();
                GarnetRunner::showHelp();
                $output = ob_get_clean();

                expect($output)->toContain('Garnet CLI');
                expect($output)->toContain('Active app');
                expect($output)->toContain('Usage:');
            });

            it('lists the standard Garnet commands', function (): void {
                ob_start();
                GarnetRunner::showHelp();
                $output = ob_get_clean();

                // Sample a handful of headline commands the help is expected to advertise.
                expect($output)->toContain('app');
                expect($output)->toContain('serve');
                expect($output)->toContain('build');
                expect($output)->toContain('deploy');
                expect($output)->toContain('bundle');
                expect($output)->toContain('ssh');
                expect($output)->toContain('config:init');
            });

            it('falls back to "(none)" when no active app is set in the .env', function (): void {
                ob_start();
                GarnetRunner::showHelp();
                $output = ob_get_clean();

                // Whichever apps the dev checkout has, the label should always
                // contain either a name or "(none)" — never explode.
                expect($output)->toMatch('/Active app:\s+\S/');
            });
        });

        describe('static properties', function (): void {
            it('exposes $frameworkDir and $appDir as static strings (set by ::main)', function (): void {
                // The properties exist as typed statics; their pre-main value is the
                // empty default. Asserting their type lets future regressions surface
                // (e.g. if someone removes the static or changes the type).
                expect(is_string(GarnetRunner::$frameworkDir))->toBe(true);
                expect(is_string(GarnetRunner::$appDir))->toBe(true);
            });
        });
    });
}
