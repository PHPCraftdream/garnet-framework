<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use Phar;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleCopyTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleDeliverableTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleOptionsTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleRebrandTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleReportTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle\BundleRuntimeTrait;

/**
 * Build a production deploy bundle for the active app.
 *
 * Build a production deploy bundle for the active app.
 *
 * Output layout (4 sibling dirs):
 *   dist/<AppName>/
 *     ├── <public-dir>/                 (copy of Apps/<AppName>/Public/)
 *     ├── <framework-dir>/              (Framework/ kernel + vendor)
 *     ├── <app-dir>/                    (app PHP classes; no WorkDir, no garnet, no .env)
 *     └── <runtime-dir>/               (garnet CLI, _shared_index.php, .env, WorkDir/)
 *
 * public/<app>/index.php is rewritten to require runtime/_shared_index.php.
 * Local dev is unaffected — this layout only appears in the dist bundle.
 *
 * Flags:
 *   --skip-build           Skip rspack production build (assume assets already built)
 *   --no-vendor            Skip copying vendor directories
 *   --with-config          Include WorkDir/Config/*.ini in the runtime tree.
 *                          OFF by default — Config/ is server-owned state
 *                          and re-deploying must NOT overwrite the host's
 *                          live credentials. Use this only for the FIRST
 *                          bootstrap of a brand-new host (or when you've
 *                          intentionally rotated creds locally and want to
 *                          push them up).
 *   --zip                  Produce dist/<AppName>.tar.gz after building
 *   --flat-zip             Pack the archive without a wrapper dir, so
 *                          `tar -xzf … -C ~/www` drops siblings straight
 *                          into the target (use with --zip).
 *   --keep-dir             Keep the unpacked dist/<AppName>/ tree after
 *                          --zip / --phar succeeds. Default: the tree
 *                          is removed once the deliverable is on disk.
 *   --no-phar              Skip phar generation. By default `bundle`
 *                          produces a self-executing phar at
 *                          dist/<AppName>.phar — end users run
 *                          `php <name>.phar` and pick which sibling
 *                          dirs to extract (interactive or flag-based,
 *                          --all / --public / --framework / --app / --runtime).
 *                          phar.readonly=0 is set automatically via an
 *                          auto re-exec; no manual `-d` flag needed.
 *   --public-dir=<name>    Rename the docroot folder (default: `public`).
 *   --framework-dir=<name> Rename the framework folder (default: `garnet-framework`).
 *   --app-dir=<name>       Rename the app folder (default: `garnet-app-<appname>`).
 *   --runtime-dir=<name>   Rename the runtime folder
 *                          (default: `garnet-runtime-<publicname>`).
 *   --public-name=<name>   Rebrand public URL paths: renames
 *                          assets/<AppName>/ and upload/<AppName>/
 *                          subdirs inside docroot to <name>, and
 *                          rewrites URL literals in *Gen.php files.
 */
class GarnetBundleCommand {
    use BundleOptionsTrait;
    use BundleCopyTrait;
    use BundleRuntimeTrait;
    use BundleRebrandTrait;
    use BundleDeliverableTrait;
    use BundleReportTrait;

    public static function run(array $args): void {
        if (in_array('--help', $args, true) || in_array('-h', $args, true) || ($args[0] ?? '') === 'help') {
            self::help();

            return;
        }

        $c = self::resolveOptions($args);
        self::buildAssets($c);
        self::cleanDist($c);
        $c += self::copyPublic($c);
        $c += self::copyApp($c);
        $c += self::copyFramework($c);
        self::assembleRuntime($c);
        self::rebrandPublicPaths($c);
        self::reportSummary($c);
        self::makeArchive($c);
        self::makePharFile($c);
        self::dropUnpackedTree($c);
    }
}
