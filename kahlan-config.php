<?php declare(strict_types=1);

use Kahlan\Dir\Dir;
use Kahlan\Filter\Filters;
use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars;

require_once './TestsInit/init.php';

GlobalVars::set('phpRunCmd', 'php');
GlobalVars::set('ErrorCatcherTestEnabled', true);

// `*IntegrationSpec.php` files touch a real database on purpose (unlike the
// rest of Kernel/, which is DB-free) — they live next to the Kernel classes
// they test, per this project's "spec sits beside its source" convention,
// rather than under Bundle/ where the DB-backed test suite otherwise lives.
// `composer test:kernel` (and the CI "no DB" Kernel job) sets
// GARNET_SKIP_INTEGRATION_SPECS=1 to skip them here; `composer test:bundle`
// (which already has a live MySQL connection) picks them up via a separate
// --spec=Kernel pass so they still run somewhere in `composer test`/CI.
if (getenv('GARNET_SKIP_INTEGRATION_SPECS') === '1') {
    Filters::apply($this, 'load', function ($chain): void {
        $specDirs = $this->commandLine()->get('spec');

        foreach ($specDirs as $dir) {
            if (!file_exists($dir)) {
                fwrite(STDERR, "ERROR: unexisting `{$dir}` directory, use --spec option to set a valid one (ex: --spec=tests).\n");

                exit(1);
            }
        }

        $files = Dir::scan($specDirs, [
            'include' => $this->commandLine()->get('grep'),
            'exclude' => ['*/.*', '*IntegrationSpec.php'],
            'type' => 'file',
        ]);
        sort($files);

        foreach ($files as $file) {
            require $file;
        }
    });
}
