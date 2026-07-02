<?php declare(strict_types=1);

// PHPStan bootstrap: define runtime constants that the analyser cannot
// discover statically. GARNET_ROOT is defined at boot in
// Kernel/Io/Bootstrap/web.php (define('GARNET_ROOT', $root)); the CLI
// command classes reference it but PHPStan never runs the bootstrap,
// so it is reported as "Constant GARNET_ROOT not found." Defining it
// here teaches PHPStan the constant exists without weakening any check.
if (!defined('GARNET_ROOT')) {
    define('GARNET_ROOT', dirname(__DIR__));
}
