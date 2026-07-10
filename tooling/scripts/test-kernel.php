<?php declare(strict_types=1);

// Cross-platform wrapper for `composer test:kernel`. Composer scripts can't
// portably use POSIX `VAR=value command` syntax (works under CI's bash, but
// not under a native Windows composer invocation via cmd.exe), so this sets
// the env var in-process via putenv() — which DOES propagate to the child
// kahlan process spawned below — before running the Kernel spec suite.
//
// GARNET_SKIP_INTEGRATION_SPECS tells kahlan-config.php to exclude
// *IntegrationSpec.php files (which touch a real database on purpose); see
// `composer test:kernel-integration` for the DB-backed counterpart.
putenv('GARNET_SKIP_INTEGRATION_SPECS=1');

$root = dirname(__DIR__, 2);
$kahlan = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'kahlan';

passthru('php ' . escapeshellarg($kahlan) . ' --spec=Kernel --grep=*Spec.php', $exitCode);

exit($exitCode);
