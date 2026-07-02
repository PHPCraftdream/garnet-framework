<?php declare(strict_types=1);

/**
 * Drop-in intercept for /__garnet/* routes.
 * Include this file early in index.php (before app init) to enable the admin panel
 * when running behind any web server (nginx, Apache, PHP built-in, etc.).
 *
 * Exits immediately for /__garnet/* requests; returns normally for all others.
 */
(static function (): void {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (!str_starts_with($path, '/__garnet')) {
        return;
    }

    // App-mode (standalone): the docroot index.php already required the app's
    // composer autoload before this file, so AdminApp is loadable as-is.
    // Monorepo fallback: when included without an autoloader (e.g. the legacy
    // `$root/Framework/vendor/autoload.php` bootstrap), pull it in here.
    if (!class_exists(PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminApp::class)) {
        // This file lives at .../Kernel/Io/GarnetCli/Admin/ — 5 levels up = project root.
        $garnetRoot = dirname(__DIR__, 5);

        if (!isset($_ENV['GARNET_ROOT'])) {
            $_ENV['GARNET_ROOT'] = $garnetRoot;
        }

        $legacyAutoload = $garnetRoot . DIRECTORY_SEPARATOR . 'Framework'
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'autoload.php';

        if (\is_file($legacyAutoload)) {
            require_once $legacyAutoload;
        }
    }

    PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin\AdminApp::handle(
        $_SERVER['REQUEST_URI'] ?? '/'
    );

    exit;
})();
