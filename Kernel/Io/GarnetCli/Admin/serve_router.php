<?php declare(strict_types=1);

/**
 * Router script for `php garnet serve` (PHP built-in server).
 *
 * Responsibility:
 *   1. Pre-set GARNET_ROOT so AdminIntercept (called from public/index.php) can use it.
 *   2. Serve static files from the app's public dir directly (fast path).
 *   3. Forward everything else to the single central public/index.php.
 */

// This file is at Framework/Kernel/Io/GarnetCli/Admin/ — 5 dirs up = project root.
$_garnet_root = rtrim(getenv('COMMON_GARNET_WEB_DIR') ?: dirname(__DIR__, 5), DIRECTORY_SEPARATOR);

if (!isset($_ENV['GARNET_ROOT'])) {
    $_ENV['GARNET_ROOT'] = $_garnet_root;
}

// ── Static file fast path ──────────────────────────────────────────────────
// DOCUMENT_ROOT is public/{AppName}/ (set by serve command via chdir).
$_garnet_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_garnet_file = $_SERVER['DOCUMENT_ROOT'] . $_garnet_path;

if ($_garnet_path !== '/' && file_exists($_garnet_file) && is_file($_garnet_file)) {
    return false; // let PHP built-in server serve it
}

unset($_garnet_root, $_garnet_path, $_garnet_file);

// ── Central entry point ────────────────────────────────────────────────────
require dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
