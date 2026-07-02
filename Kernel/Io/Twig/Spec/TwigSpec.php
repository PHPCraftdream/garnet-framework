<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

describe('Twig', function (): void {
    beforeAll(function (): void {
        // Create test template directories
        if (!is_dir(__DIR__ . '/templates')) {
            mkdir(__DIR__ . '/templates', 0o777, true);
        }

        if (!is_dir(__DIR__ . '/templates2')) {
            mkdir(__DIR__ . '/templates2', 0o777, true);
        }
    });

    afterAll(function (): void {
        // Cleanup
        if (is_dir(__DIR__ . '/templates')) {
            rmdir(__DIR__ . '/templates');
        }

        if (is_dir(__DIR__ . '/templates2')) {
            rmdir(__DIR__ . '/templates2');
        }
    });

    describe('Singleton pattern', function (): void {
        it('returns same instance for same name', function (): void {
            $instance1 = Twig::get('test_instance');
            $instance2 = Twig::get('test_instance');

            expect($instance1)->toBe($instance2);
        });

        it('returns different instance for different names', function (): void {
            $instance1 = Twig::get('instance_a');
            $instance2 = Twig::get('instance_b');

            expect($instance1)->not->toBe($instance2);
        });

        it('uses TWIG_MAIN as default instance name', function (): void {
            $instance1 = Twig::get();
            $instance2 = Twig::get(Twig::TWIG_MAIN);

            expect($instance1)->toBe($instance2);
        });
    });

    describe('Path management', function (): void {
        it('adds path to loader', function (): void {
            $twig = Twig::get('add_path_test');
            $loader = $twig->getFwLoader();

            $twig->addFsPath(__DIR__ . '/templates');

            $paths = $loader->getPaths();
            expect(count($paths))->toBe(1);
        });

        it('prepends path to loader', function (): void {
            $twig = Twig::get('prepend_path_test');
            $loader = $twig->getFwLoader();

            $twig->addFsPath(__DIR__ . '/templates');
            $twig->prependFsPath(__DIR__ . '/templates2');

            $paths = $loader->getPaths();
            expect(count($paths))->toBe(2);
            expect($paths[0])->toContain('templates2');
        });
    });

    describe('Cache management', function (): void {
        it('sets cache path', function (): void {
            $twig = Twig::get('cache_test');
            $cachePath = sys_get_temp_dir() . '/twig_cache_test';

            $twig->defineCachePath($cachePath);

            $env = $twig->twig();
            expect($env->getCache())->toContain('twig_cache_test');
        });
    });
});
