<?php declare(strict_types=1);

/**
 * Router for the per-worker `php -S` processes spawned by the Node dev
 * server (tooling/server/garnet-serve.mjs).
 *
 * These workers are DYNAMIC-ONLY. The Node front server owns all static
 * file serving (with mime + cache headers, served straight off libuv), so
 * every request that reaches a PHP worker is an application request — route
 * it unconditionally to the app's single front controller. The PHP built-in
 * server never serves a static asset here; that's the whole point of putting
 * Node in front.
 *
 * DOCUMENT_ROOT is the app's public dir, set by `php -S -t <publicDir>`.
 */

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();

require $docRoot . DIRECTORY_SEPARATOR . 'index.php';
