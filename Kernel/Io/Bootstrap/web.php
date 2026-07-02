<?php declare(strict_types=1);

/**
 * Universal web-bootstrap for Garnet applications.
 *
 * The app-level docroot index.php (e.g. Apps/<App>/Public/index.php)
 * requires this file. It normalises HTTP headers, loads the framework
 * autoload, resolves the active application via APP_NAME, wires up
 * the public-dir pointer, and dispatches to `run_web.php`.
 *
 * There is NO application-specific code here — everything is driven
 * by the APP_NAME .env key and the standard Garnet conventions.
 */

(static function (): void {
    // ── Scheme / host normalisation ───────────────────────────────────────
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $_SERVER['REQUEST_SCHEME'] = 'https';
            $_SERVER['HTTPS'] = 'on';
        }
    }

    if (!isset($_SERVER['REQUEST_SCHEME'])) {
        $_SERVER['REQUEST_SCHEME'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    }

    if (!isset($_SERVER['HTTPS'])) {
        $_SERVER['HTTPS'] = $_SERVER['REQUEST_SCHEME'] === 'https' ? 'on' : 'off';
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if (str_starts_with($host, 'www.')) {
        $newHost = substr($host, 4);
        $scheme = $_SERVER['REQUEST_SCHEME'];
        $port = $_SERVER['HTTP_X_FORWARDED_PORT'] ?? $_SERVER['SERVER_PORT'] ?? '80';
        $port = ($port === '80' || $port === 80) ? '' : ':' . $port;
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: {$scheme}://{$newHost}{$port}{$uri}", true, 301);

        exit;
    }

    // ── Resolve GARNET_ROOT ──────────────────────────────────────────────
    // In dev: Framework/ is at $root/Framework, and this file lives inside
    // Framework/Kernel/Io/Bootstrap/. In bundle: GARNET_ROOT is already set
    // by the runtime bootstrap (_shared_index.php) before requiring this.
    $root = $_ENV['GARNET_ROOT']
        ?? getenv('GARNET_ROOT') ?: dirname(__DIR__, 4);

    if (!isset($_ENV['GARNET_ROOT'])) {
        $_ENV['GARNET_ROOT'] = $root;
    }

    if (!defined('GARNET_ROOT')) {
        define('GARNET_ROOT', $root);
    }

    // ── Framework autoload ────────────────────────────────────────────────
    require_once $root . DIRECTORY_SEPARATOR . 'Framework'
        . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    // ── Admin panel intercept ─────────────────────────────────────────────
    require_once $root . DIRECTORY_SEPARATOR . 'Framework'
        . DIRECTORY_SEPARATOR . 'Kernel' . DIRECTORY_SEPARATOR . 'Io'
        . DIRECTORY_SEPARATOR . 'GarnetCli' . DIRECTORY_SEPARATOR . 'Admin'
        . DIRECTORY_SEPARATOR . 'AdminIntercept.php';

    // ── Resolve active app ────────────────────────────────────────────────
    $appName = PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv::readAppNameFromRoot($root);

    if ($appName === '') {
        http_response_code(500);
        echo 'Garnet: APP_NAME not set. Run: php garnet app:use &lt;AppName&gt;';

        exit(1);
    }

    $publicDir = PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv::getPublicDir($appName) . DIRECTORY_SEPARATOR;
    $appDir = PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv::getAppDir($appName) . DIRECTORY_SEPARATOR;

    // ── Bootstrap ─────────────────────────────────────────────────────────
    define('PUBLIC_DIR', $publicDir);

    require_once $appDir . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    $appClass = 'PHPCraftdream\\' . $appName . '\\' . $appName;
    $appClass::setPublicDirInit($publicDir);

    // ── Dispatch ──────────────────────────────────────────────────────────
    if (php_sapi_name() === 'cli') {
        require $appDir . 'run_cmd.php';

        return;
    }

    require $appDir . 'run_web.php';
})();
