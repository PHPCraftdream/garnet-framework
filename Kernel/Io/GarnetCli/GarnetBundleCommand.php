<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

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
    public static function run(array $args): void {
        if (in_array('--help', $args, true) || in_array('-h', $args, true) || ($args[0] ?? '') === 'help') {
            self::help();

            return;
        }

        $appName = GarnetEnv::requireAppName();
        $appNameLower = strtolower($appName);

        // `bundle` builds a self-contained deploy artifact by copying the
        // app + framework sources into 4 sibling dirs (public/framework/app/
        // runtime). Two source layouts exist:
        //   - legacy monorepo: Apps/<App>/ + Framework/ under GARNET_ROOT.
        //   - standalone app (composer-vendored framework): the app is its
        //     own repo/root, and the framework lives inside its own
        //     vendor/phpcraftdream/garnet-framework/ — already the exact
        //     tree `php garnet build` (run from the app) writes the
        //     asset-bridge *Gen.php files and hashed Public/assets/ into, so
        //     sourcing the framework copy from there (not a separate
        //     framework checkout) can never drift from what was actually
        //     built, unlike a manual `ssh:put` of a pristine framework repo.
        $isAppMode = GarnetRunner::$appDir !== ''
            && str_replace('\\', '/', (string)realpath(GarnetRunner::$appDir))
               !== str_replace('\\', '/', (string)realpath(GarnetRunner::$frameworkDir));

        // --__phar-relaunched is the recursion guard: when phar.readonly=1
        // we re-exec the whole command through `php -d phar.readonly=0`,
        // appending this flag so the child process knows not to relaunch
        // again. Strip it from the args list before normal parsing.
        $relaunchFlag = '--__phar-relaunched';
        $alreadyRelaunched = in_array($relaunchFlag, $args, true);
        $args = array_values(array_filter($args, static fn ($a) => $a !== $relaunchFlag));

        $skipBuild = in_array('--skip-build', $args, true);
        $noVendor = in_array('--no-vendor', $args, true);
        $withConfig = in_array('--with-config', $args, true);
        $makeZip = in_array('--zip', $args, true);
        $flatZip = in_array('--flat-zip', $args, true);
        $keepDir = in_array('--keep-dir', $args, true);
        // Phar is the default deliverable now (it's selectable, gz-compressed
        // and self-executing). --no-phar opts out.
        $makePhar = !in_array('--no-phar', $args, true);

        // Phar building needs phar.readonly=0 in php.ini. Rather than make
        // the user remember to pass `-d phar.readonly=0`, re-exec ourselves
        // with the flag set. The relaunch marker prevents an infinite loop
        // if the override somehow fails to take effect.
        if ($makePhar && (int)ini_get('phar.readonly') === 1) {
            if ($alreadyRelaunched) {
                echo "\033[33mWarning:\033[0m phar.readonly is still 1 after re-exec — skipping phar." . PHP_EOL;
                $makePhar = false;
            } else {
                echo "\033[33mNote:\033[0m relaunching with phar.readonly=0 (auto)" . PHP_EOL . PHP_EOL;
                // App-mode's own ./garnet lives at the app root
                // (GarnetRunner::$appDir), not under GARNET_ROOT (which in
                // app-mode is the vendored framework dir) — same reasoning
                // as $garnetSrc below for the runtime-dir assembly step.
                $garnetBin = $isAppMode ? GarnetRunner::$appDir . DS . 'garnet' : GARNET_ROOT . DS . 'garnet';
                $cmd = sprintf(
                    '%s -d phar.readonly=0 %s bundle %s %s',
                    escapeshellarg(PHP_BINARY),
                    escapeshellarg($garnetBin),
                    implode(' ', array_map('escapeshellarg', $args)),
                    $relaunchFlag
                );
                passthru($cmd, $code);

                exit($code);
            }
        }

        // --public-dir / --framework-dir / --app-dir / --runtime-dir / --public-name:
        // Resolution order: 1) CLI flag  2) ssh.ini deployment block  3) built-in default.
        $defaults = [
            'public-dir' => 'public',
            'framework-dir' => 'garnet-framework',
            'app-dir' => 'garnet-app-' . $appNameLower,
            'public-name' => $appNameLower,
            'runtime-dir' => '',  // derived from public-name below if still empty
        ];
        $resolved = $defaults;
        $paramSources = array_fill_keys(array_keys($defaults), 'default');

        // deploy.ini provides per-deployment defaults (set once, override per CLI call).
        $deployDefaults = self::readDeployDefaults();
        $deployKeyMap = [
            'public-dir' => 'public_dir',
            'framework-dir' => 'framework_dir',
            'app-dir' => 'app_dir',
            'runtime-dir' => 'runtime_dir',
            'public-name' => 'public_name',
        ];

        foreach ($deployKeyMap as $paramName => $deployKey) {
            if (($deployDefaults[$deployKey] ?? '') !== '') {
                $resolved[$paramName] = $deployDefaults[$deployKey];
                $paramSources[$paramName] = 'deploy.ini';
            }
        }

        // CLI flags take final precedence.
        $cliOpts = [
            '--public-dir=' => 'public-dir',
            '--framework-dir=' => 'framework-dir',
            '--app-dir=' => 'app-dir',
            '--public-name=' => 'public-name',
            '--runtime-dir=' => 'runtime-dir',
        ];

        foreach ($args as $arg) {
            foreach ($cliOpts as $prefix => $paramName) {
                if (str_starts_with($arg, $prefix)) {
                    $val = trim(substr($arg, strlen($prefix)), " \t\"'/\\");

                    if ($val === '') {
                        self::fail("{$prefix} requires a non-empty name");
                    }
                    $resolved[$paramName] = $val;
                    $paramSources[$paramName] = 'CLI';
                }
            }
        }

        $publicDirName = $resolved['public-dir'];
        $frameworkDirName = $resolved['framework-dir'];
        $appDirName = $resolved['app-dir'];
        $publicName = $resolved['public-name'];
        $runtimeDirName = $resolved['runtime-dir'];

        // Default runtime dir uses the resolved public name so it matches the
        // deployed domain (e.g. garnet-runtime-myapp next to myapp/).
        if ($runtimeDirName === '') {
            $runtimeDirName = 'garnet-runtime-' . $publicName;
            $paramSources['runtime-dir'] = 'default';
        }

        // All four sibling folder names must be distinct.
        $names = [$publicDirName, $frameworkDirName, $appDirName, $runtimeDirName];

        if (count(array_unique($names)) !== count($names)) {
            self::fail('--public-dir / --framework-dir / --app-dir / --runtime-dir must all be different (got: ' . implode(', ', $names) . ')');
        }

        $root = GARNET_ROOT;
        // dist/ must NOT land under GARNET_ROOT in app-mode — there it's the
        // vendored framework package dir, wiped by the next composer
        // install, and (worse) a stray bundle run can then copy the app
        // into itself (this exact failure was hit for real once — see the
        // anti-recursion guards in copyDir() below, which this fix makes
        // largely unnecessary but doesn't remove, as defence in depth).
        $distRoot = ($isAppMode ? GarnetRunner::$appDir : $root) . DS . 'dist';
        $distApp = $distRoot . DS . $appName;

        $publicSrc = GarnetEnv::getPublicDir($appName);
        // App-mode: the app IS its own root, and the framework lives inside
        // the app's own vendor/ (composer-installed) — GarnetRunner already
        // resolves both anchors correctly (it's how $isAppMode above was
        // computed). Legacy mode keeps the Apps/<App> + Framework/ sibling
        // layout under GARNET_ROOT.
        $appSrc = $isAppMode ? GarnetRunner::$appDir : $root . DS . 'Apps' . DS . $appName;
        $frameworkSrc = $isAppMode ? GarnetRunner::$frameworkDir : $root . DS . 'Framework';

        if (!is_dir($publicSrc)) {
            self::fail("Public dir not found: {$publicSrc}");
        }

        if (!is_dir($appSrc)) {
            self::fail("App dir not found: {$appSrc}");
        }

        if (!is_dir($frameworkSrc)) {
            self::fail("Framework dir not found: {$frameworkSrc}");
        }

        $pad = static fn (string $s, int $w): string => str_pad($s, $w);
        echo "\033[1m=== Garnet Bundle: {$appName} ===\033[0m" . PHP_EOL;
        echo "  dist target:   {$distApp}" . PHP_EOL;
        echo '  ' . $pad('public-dir',    14) . ' = ' . $pad($publicDirName,    32) . "({$paramSources['public-dir']})" . PHP_EOL;
        echo '  ' . $pad('framework-dir', 14) . ' = ' . $pad($frameworkDirName, 32) . "({$paramSources['framework-dir']})" . PHP_EOL;
        echo '  ' . $pad('app-dir',       14) . ' = ' . $pad($appDirName,       32) . "({$paramSources['app-dir']})" . PHP_EOL;
        echo '  ' . $pad('runtime-dir',   14) . ' = ' . $pad($runtimeDirName,   32) . "({$paramSources['runtime-dir']})" . PHP_EOL;
        echo '  ' . $pad('public-name',   14) . ' = ' . $pad($publicName,       32) . "({$paramSources['public-name']})" . PHP_EOL;

        if ($makeZip) {
            echo '  archive:       ' . ($flatZip ? 'flat (no wrapper dir)' : 'wrapped in ' . $appName . '/') . PHP_EOL;
        }
        echo PHP_EOL;

        // 1. Production build of frontend assets
        if (!$skipBuild) {
            self::step('1/6', 'Building frontend assets (production)');
            self::runRspackBuild($root);
            echo PHP_EOL;
        } else {
            self::step('1/6', 'Skipping rspack build (--skip-build)');
            echo PHP_EOL;
        }

        // 2. Clean dist target
        self::step('2/6', 'Cleaning dist target');

        if (is_dir($distApp)) {
            self::rmrf($distApp);
            echo "  removed previous dir: {$distApp}" . PHP_EOL;
        }
        // Also drop any leftover archive from a prior --zip run so a
        // subsequent bundle without --zip can't be confused with the new one.
        $staleArchive = $distRoot . DS . $appName . '.tar.gz';

        if (is_file($staleArchive)) {
            @unlink($staleArchive);
            echo "  removed previous archive: {$staleArchive}" . PHP_EOL;
        }

        if (!is_dir($distRoot)) {
            @mkdir($distRoot, 0o755, true);
        }
        @mkdir($distApp, 0o755, true);
        echo "  ready: {$distApp}" . PHP_EOL . PHP_EOL;

        // 3. Copy Apps/<App>/Public
        self::step('3/6', 'Copying public assets');
        $distPublic = $distApp . DS . $publicDirName;
        self::copyDir($publicSrc, $distPublic);
        echo "  -> {$distPublic}" . PHP_EOL;

        // Rewrite per-app index.php to boot via the runtime dir's _shared_index.php.
        // The runtime dir is a sibling of the public dir at the bundle root level.
        $perAppIndex = $distPublic . DS . 'index.php';

        if (is_file($perAppIndex)) {
            file_put_contents($perAppIndex, PublicPathRebrander::perAppIndexContent($runtimeDirName));
            echo "  per-app index rewritten -> {$perAppIndex}" . PHP_EOL;
        }
        echo PHP_EOL;

        // 4. Copy app
        self::step('4/6', 'Copying app');
        $distAppApp = $distApp . DS . $appDirName;
        // WorkDir is entirely moved to the runtime dir — exclude it here.
        // `dist` is excluded unconditionally: \$distRoot (see above) now
        // lives under the app's OWN root in app-mode, i.e. directly inside
        // the very tree this step copies FROM — without this exclude a
        // build recurses into its own not-yet-finished output and copies it
        // into itself, growing without bound (hit this for real: a stray
        // --skip-build run ballooned to 2.5GB before being killed, back
        // when \$distRoot instead sat under the vendored framework dir).
        $appExcludes = [
            'WorkDir',
            'Public',
            'Tests',
            'Front',
            'node_modules',
            'docs',
            'Spec',
            'TestsInit',
            'Migrations' . DS . 'WorkDir',
            'dist',
            '.idea',
            '.vscode',
            '.vs',
            '.xcodeproj',
            '.atom',
            '.git',
        ];
        $appExcludeFiles = [
            '.env',
            '.env.local',
            '.env.example',
            'check.bat',
            'cm.bat',
            'kahlan-config.php',
            'phpstan.neon',
            'package.json',
            'package-lock.json',
            'r_dump_autoload.bat',
            'seed.php',
        ];

        if ($noVendor) {
            $appExcludes[] = 'vendor';
        } elseif ($isAppMode) {
            // App-mode ships vendor/ by default (the app's OTHER composer
            // deps, e.g. a real app's own libraries, aren't available any
            // other way on a host with no composer) but the framework's own
            // copy inside it is redundant — it's copied separately, fresh,
            // as the framework-dir bucket below. Shipping both would waste
            // space and, worse, risks the two drifting from each other.
            $appExcludes[] = 'vendor' . DS . 'phpcraftdream' . DS . 'garnet-framework';
        }

        self::copyDir($appSrc, $distAppApp, $appExcludes, $appExcludeFiles);

        // Patch app autoload.php to point at bundle framework
        $appAutoload = $distAppApp . DS . 'autoload.php';

        if (is_file($appAutoload)) {
            $orig = file_get_contents($appAutoload);
            $rewritten = str_replace(
                "__DIR__ . '/../../Framework/vendor/autoload.php'",
                "__DIR__ . '/../{$frameworkDirName}/vendor/autoload.php'",
                $orig
            );

            if ($rewritten !== $orig) {
                file_put_contents($appAutoload, $rewritten);
                echo '  app autoload patched' . PHP_EOL;
            }
        }

        // App-mode's run_cmd.php scaffold (Templates/Application/run_cmd.php,
        // and every app:create'd app's own copy) hardcodes
        // `IRabi::setPublicDirInit(__DIR__ . '/WorkDir/public/')` — a local-
        // dev convenience stub, always an empty directory (public/ isn't
        // meaningful for CLI commands like migrate/cron, only for web
        // requests), that WorkDir is entirely excluded from this app-dir
        // copy since the real WorkDir now lives in the runtime-dir sibling.
        // getPublicDir()'s CLI fallback only kicks in when NOTHING was set
        // at all; an explicitly-set-but-missing dir still throws. Recreate
        // the empty stub here so `php garnet-runtime/garnet <cli-cmd>`
        // boots without needing run_cmd.php rewritten.
        if ($isAppMode) {
            @mkdir($distAppApp . DS . 'WorkDir' . DS . 'public', 0o755, true);
        }
        echo "  -> {$distAppApp}" . PHP_EOL . PHP_EOL;

        // 5. Copy framework
        self::step('5/6', 'Copying framework');
        $distFw = $distApp . DS . $frameworkDirName;
        @mkdir($distFw, 0o755, true);

        // Templates/ is the app:create scaffold (Templates/Application/) —
        // a dev-only tool for generating new apps, never read at runtime by
        // a deployed app. Shipping it just wastes space (tens of MB).
        // `dist` excluded for the same self-recursion reason as the app
        // copy above — GARNET_ROOT (and $distRoot) lives inside the
        // framework dir itself in app-mode. `FrontBuilder` (Node build
        // tooling — not needed once Public/assets/ is already built and
        // shipped, same exclusion documented for the manual redeploy path
        // in docs/deploy.md) and `node_modules` (its own copy is an NTFS
        // junction to FrontBuilder/node_modules, created by `garnet setup`
        // — walking it made copy() choke with "cannot be a directory",
        // and even if it worked it'd ship an unnecessary multi-hundred-MB
        // tree) are excluded for the same reason.
        $fwExcludes = ['.idea', '.vscode', '.vs', '.xcodeproj', '.atom', '.git', 'Templates', 'dist', 'FrontBuilder', 'node_modules'];
        $fwExcludeFiles = [
            'cm.bat', 'errors.log', 'kahlan-config.php', 'phpstan.neon',
            'php-cs-fixer.phar', 'phpstan.phar',
            'r_dump_autoload.bat', 'r_kahlan.bat', 'r_php-cs-fixer.bat', 'r_phpstan.bat',
        ];

        if ($noVendor) {
            $fwExcludes[] = 'vendor';
        }

        // Copy entries from Framework/ root, preserving structure
        self::copyDir($frameworkSrc, $distFw, $fwExcludes, $fwExcludeFiles);

        // App-mode only: the composer-installed copy at vendor/phpcraftdream/
        // garnet-framework has NO vendor/ of its own — Composer flattens a
        // dependency's transitive deps (aura/*, twig/twig, guzzlehttp/*, …)
        // into the CONSUMING app's shared vendor/, not a nested tree under
        // the package. That's fine for local dev (autoload.php falls back to
        // the app's own vendor/autoload.php, which really does have
        // everything) but breaks the whole point of a separate framework-dir
        // sibling: GARNET_FRAMEWORK_DIR signals "the framework's classes AND
        // its own deps live here, independently upgradable" — the runtime
        // dispatcher and autoload.php both require $frameworkDir/vendor/
        // autoload.php to exist. Materialise it for real, in this disposable
        // dist/ copy only (never touching the real installed copy) — the
        // framework's own composer.json/composer.lock DO get shipped
        // (composer never installs a nested vendor/, but it does copy the
        // manifest files), so `composer install` here resolves the SAME
        // pinned versions that the real install used elsewhere.
        if ($isAppMode && !$noVendor && !is_dir($distFw . DS . 'vendor')) {
            if (is_file($distFw . DS . 'composer.json')) {
                self::step('5b', 'Materialising framework vendor/ (composer install --no-dev)');
                $cwd = getcwd();
                chdir($distFw);
                // --no-scripts: the framework's own composer.json runs a
                // post-install-cmd (`bin/garnet setup --skip-composer --soft`)
                // meant for a REAL framework checkout — it tries to npm-install
                // FrontBuilder (deliberately excluded from this dist copy) and
                // wire an admin-panel node_modules junction, neither of which
                // makes sense for a disposable copy whose only job here is to
                // produce vendor/autoload.php.
                passthru('composer install --no-dev --no-interaction --no-scripts --optimize-autoloader', $composerCode);
                chdir($cwd);

                if ($composerCode !== 0 || !is_dir($distFw . DS . 'vendor')) {
                    self::fail(
                        "Could not materialise the framework's own vendor/ in {$distFw}"
                        . " (composer install exited {$composerCode}). The bundled"
                        . ' framework-dir would be missing vendor/autoload.php and'
                        . ' fail to boot. Fix composer availability/network access'
                        . ' and re-run, or pass --no-vendor and provide dependencies'
                        . ' another way.'
                    );
                }
                echo PHP_EOL;
            } else {
                echo '  WARNING: no composer.json found in the framework copy —'
                    . ' vendor/ could not be materialised; the bundle will NOT boot'
                    . ' as-is.' . PHP_EOL . PHP_EOL;
            }
        }
        echo "  -> {$distFw}" . PHP_EOL . PHP_EOL;

        // 6. Assemble runtime directory
        // Contains the garnet CLI, _shared_index.php, .env (with all BUNDLE_*
        // keys), and the WorkDir skeleton. App and framework dirs are now
        // path-agnostic and don't carry any runtime mutable state.
        self::step('6/6', "Assembling runtime dir: {$runtimeDirName}/");
        $distRuntime = $distApp . DS . $runtimeDirName;
        @mkdir($distRuntime, 0o755, true);

        // garnet CLI — lives in runtime dir; sets GARNET_ROOT to bundle root
        // and points GARNET_APP_DIR at the actual app dir sibling. App-mode's
        // own ./garnet lives at the app root (GarnetRunner::$appDir), not
        // under GARNET_ROOT (which in app-mode is the framework dir — see
        // the isAppMode computation above).
        $garnetSrc = $isAppMode ? GarnetRunner::$appDir . DS . 'garnet' : $root . DS . 'garnet';
        $contents = self::renderRuntimeGarnet($garnetSrc, $appDirName, $appName, $frameworkDirName, $isAppMode);

        if ($contents !== null) {
            file_put_contents($distRuntime . DS . 'garnet', $contents);
            @chmod($distRuntime . DS . 'garnet', 0o755);
            echo "  garnet CLI -> {$runtimeDirName}/garnet" . PHP_EOL;
        }

        // _shared_index.php — boots the framework from paths in this dir's .env.
        file_put_contents($distRuntime . DS . '_shared_index.php', self::renderSharedIndex());
        echo "  _shared_index.php -> {$runtimeDirName}/_shared_index.php" . PHP_EOL;

        // .env — relative paths so the bundle is portable across hosts.
        // All four sibling dirs are recorded for use by uninstall and deploy tools.
        $runtimeEnv = "APP_NAME={$appName}\n"
            . "BUNDLE_PUBLIC_DIR=../{$publicDirName}\n"
            . "BUNDLE_FRAMEWORK_DIR=../{$frameworkDirName}\n"
            . "BUNDLE_APP_DIR=../{$appDirName}\n"
            . "BUNDLE_WORKDIR_DIR=./WorkDir\n"
            . "BUNDLE_RUNTIME_DIR={$runtimeDirName}\n";
        file_put_contents($distRuntime . DS . '.env', $runtimeEnv);
        echo "  .env -> {$runtimeDirName}/.env" . PHP_EOL;

        // App dir .env — minimal keys needed by GarnetEnv CLI tools which
        // read from GARNET_APP_DIR. The garnet script sets GARNET_APP_DIR
        // to the app dir, so this file must carry APP_NAME + bundle dir names.
        $appEnv = "APP_NAME={$appName}\n"
            . "BUNDLE_PUBLIC_DIR={$publicDirName}\n"
            . "BUNDLE_FRAMEWORK_DIR={$frameworkDirName}\n"
            . "BUNDLE_RUNTIME_DIR={$runtimeDirName}\n"
            . "BUNDLE_WORKDIR_DIR=WorkDir\n";
        file_put_contents($distAppApp . DS . '.env', $appEnv);
        echo "  app .env -> {$appDirName}/.env" . PHP_EOL;

        // WorkDir skeleton — Logger requires Errors/ and System/ to exist on
        // first boot; Routes/ is auto-created. Config/ is where the operator
        // drops their .ini files after deployment.
        $runtimeWorkSubs = [
            'WorkDir',
            'WorkDir' . DS . 'Config',
            'WorkDir' . DS . 'ConfigDev',
            'WorkDir' . DS . 'FileCache',
            'WorkDir' . DS . 'TwigCache',
            'WorkDir' . DS . 'LogJournal',
            'WorkDir' . DS . 'LogJournal' . DS . 'Errors',
            'WorkDir' . DS . 'LogJournal' . DS . 'System',
            'WorkDir' . DS . 'LogJournal' . DS . 'Routes',
            'WorkDir' . DS . 'Upload',
        ];

        foreach ($runtimeWorkSubs as $sub) {
            @mkdir($distRuntime . DS . $sub, 0o775, true);
            @touch($distRuntime . DS . $sub . DS . '.keep');
        }

        // ConfigExample: .ini templates that operators copy to Config/ on setup.
        $configExampleSrc = $appSrc . DS . 'WorkDir' . DS . 'ConfigExample';

        if (is_dir($configExampleSrc)) {
            self::copyDir($configExampleSrc, $distRuntime . DS . 'WorkDir' . DS . 'ConfigExample');
            echo "  ConfigExample -> {$runtimeDirName}/WorkDir/ConfigExample" . PHP_EOL;
        }

        // Config/: production .ini set is server-owned state. Operators
        // edit those files in-place on the host; pushing the developer's
        // local copy into the runtime tree on every deploy is destructive
        // (it silently overwrote live db.ini on a re-deploy once — never
        // again). Default: SKIP. Opt in with --with-config for the first
        // bootstrap of a fresh host, or when you've intentionally rotated
        // creds locally and want to push them up.
        $configProdSrc = $appSrc . DS . 'WorkDir' . DS . 'Config';

        if ($withConfig && is_dir($configProdSrc)) {
            $iniFiles = glob($configProdSrc . DS . '*.ini') ?: [];

            if (!empty($iniFiles)) {
                $configProdDst = $distRuntime . DS . 'WorkDir' . DS . 'Config';
                !is_dir($configProdDst) && mkdir($configProdDst, 0o755, true);

                foreach ($iniFiles as $src) {
                    copy($src, $configProdDst . DS . basename($src));
                }
                echo '  Config (' . count($iniFiles) . " .ini, --with-config) -> {$runtimeDirName}/WorkDir/Config" . PHP_EOL;
            }
        } elseif (!$withConfig && is_dir($configProdSrc) && !empty(glob($configProdSrc . DS . '*.ini') ?: [])) {
            echo '  Config -> SKIPPED (host-owned; rerun with --with-config to push)' . PHP_EOL;
        }
        echo "  WorkDir skeleton -> {$runtimeDirName}/WorkDir/" . PHP_EOL;

        // uninstall.sh — autonomous shell script, no PHP needed on host.
        $uninstallSh = self::renderUninstallScript($publicDirName, $frameworkDirName, $appDirName, $runtimeDirName, $appName);
        file_put_contents($distApp . DS . 'uninstall.sh', $uninstallSh);
        @chmod($distApp . DS . 'uninstall.sh', 0o755);
        echo "  uninstall.sh -> {$distApp}/uninstall.sh" . PHP_EOL . PHP_EOL;

        // 7. Rebrand public paths: rename MyApp → <publicName> in docroot
        // subdirs (assets/<old>/, upload/<old>/) and in *Gen.php URL literals.
        if (strtolower($publicName) !== $appNameLower) {
            self::step('7/7', "Rebranding public paths: {$appName} -> {$publicName}");

            // Rename subdirectories inside docroot: assets/<AppName>, upload/<AppName>
            foreach (['assets', 'upload'] as $sub) {
                $oldDir = $distPublic . DS . $sub . DS . $appNameLower;
                $newDir = $distPublic . DS . $sub . DS . $publicName;

                if (!is_dir($oldDir)) {
                    // Try original case (MyApp vs myapp)
                    $oldDir = $distPublic . DS . $sub . DS . $appName;
                }

                if (is_dir($oldDir) && !is_dir($newDir)) {
                    rename($oldDir, $newDir);
                    echo "  renamed {$sub}/{$appName} -> {$sub}/{$publicName}" . PHP_EOL;
                }
            }

            // Rewrite URL literals via the shared PublicPathRebrander helper.
            $pairs = PublicPathRebrander::rewritePairs($appName, $publicName);
            $find = array_keys($pairs);
            $replace = array_values($pairs);

            $rewriteFile = static function (string $path) use ($find, $replace, &$rewriteCount): void {
                $orig = file_get_contents($path);

                if ($orig === false) {
                    return;
                }
                $rewritten = str_replace($find, $replace, $orig);

                if ($rewritten !== $orig) {
                    file_put_contents($path, $rewritten);
                    $rewriteCount++;
                }
            };

            // a) Gen.php files
            $rewriteCount = 0;
            $genDirs = [$distAppApp, $distFw];

            foreach ($genDirs as $dir) {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iter as $file) {
                    if (!str_ends_with($file->getFilename(), 'Gen.php')) {
                        continue;
                    }
                    $rewriteFile($file->getPathname());
                }
            }
            echo "  rewrote {$rewriteCount} *Gen.php file(s)" . PHP_EOL;

            // b) JS/CSS files under docroot (rspack runtime publicPath
            //    plus inline url() references in CSS)
            $rewriteCount = 0;

            if (is_dir($distPublic)) {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($distPublic, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iter as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }
                    $ext = strtolower($file->getExtension());

                    if (!in_array($ext, ['js', 'css', 'map', 'html', 'svg'], true)) {
                        continue;
                    }
                    $rewriteFile($file->getPathname());
                }
            }
            echo "  rewrote {$rewriteCount} docroot asset file(s)" . PHP_EOL;
            echo PHP_EOL;
        }

        // Summary
        [$files, $bytes] = self::statTree($distApp);
        echo "\033[32m=== Bundle complete ===\033[0m" . PHP_EOL;
        echo "  Path:  {$distApp}" . PHP_EOL;
        echo "  Files: {$files}" . PHP_EOL;
        echo '  Size:  ' . self::humanBytes($bytes) . PHP_EOL;

        if ($makeZip) {
            $tarball = $distRoot . DS . $appName . '.tar.gz';
            echo PHP_EOL . "Creating archive: {$tarball}" . PHP_EOL;
            $cwd = getcwd();
            // On Windows, tar interprets `D:` in any path as a remote
            // host spec and dies. BSD-tar (Win10/11 default) doesn't
            // even accept --force-local. Cleanest fix: chdir to the
            // archive's target dir and pass only relative paths — that
            // way no colon ever reaches tar's argv. Works on every tar.
            $archiveName = $appName . '.tar.gz';

            if ($flatZip) {
                // Pack the *contents* of dist/<App>/ — no wrapper dir.
                // Distros extracting this archive get the sibling dirs
                // (docroot, framework, app) straight into the cwd.
                // chdir into distApp; archive sits one level up in distRoot.
                chdir($distApp);
                $relTarball = '..' . DS . $archiveName;
                passthru('tar -czf ' . escapeshellarg($relTarball) . ' .', $code);
            } else {
                chdir($distRoot);
                passthru('tar -czf ' . escapeshellarg($archiveName) . ' ' . escapeshellarg($appName), $code);
            }
            chdir($cwd);

            if ($code === 0 && is_file($tarball)) {
                echo '  Archive: ' . self::humanBytes(filesize($tarball)) . PHP_EOL;

                if ($flatZip) {
                    echo '  Extract with:  tar -xzf ' . basename($tarball) . ' -C /target/dir' . PHP_EOL;
                }
            } else {
                echo "  Archive creation failed (exit {$code})" . PHP_EOL;
            }
        }

        if ($makePhar) {
            $pharFile = $distRoot . DS . $appName . '.phar';
            echo PHP_EOL . "Creating phar: {$pharFile}" . PHP_EOL;
            self::buildPhar(
                src: $distApp,
                pharFile: $pharFile,
                publicDir: $publicDirName,
                fwDir: $frameworkDirName,
                appDir: $appDirName,
                runtimeDir: $runtimeDirName,
                appName: $appName
            );

            if (is_file($pharFile)) {
                echo '  Phar: ' . self::humanBytes(filesize($pharFile)) . PHP_EOL;
                echo '  Run on host: php ' . basename($pharFile) . PHP_EOL;
            }
        }

        // Drop the unpacked dist/<App>/ tree once the deliverable
        // (zip and/or phar) is safely on disk — the tree was just
        // scratch space. Keep it with --keep-dir when debugging the
        // bundle layout.
        if (($makeZip || $makePhar) && is_dir($distApp)) {
            if (!$keepDir) {
                self::rmrf($distApp);
                echo "  removed unpacked dir: {$distApp}" . PHP_EOL;
            } else {
                echo "  (kept unpacked dir at {$distApp} — --keep-dir)" . PHP_EOL;
            }
        }
    }

    private static function runRspackBuild(string $root): void {
        // Mirrors GarnetBuildCommand::run() (the plain `php garnet build`
        // path, already correct for both layouts and exercised constantly
        // this session) rather than assuming $root itself has a FrontBuilder
        // child — that's only true in the legacy monorepo. FrontBuilder
        // lives inside the FRAMEWORK package in both layouts;
        // COMMON_GARNET_WEB_DIR (where rspack.config.ts expects to find the
        // local `garnet` CLI to spawn `php <dir>/garnet prepare`) is the app
        // dir in app-mode, GARNET_ROOT in legacy mode. Found this the hard
        // way: every earlier test in this session passed --skip-build,
        // which never exercised this method at all.
        $appDir = GarnetRunner::$appDir !== '' ? GarnetRunner::$appDir : $root;
        putenv('COMMON_GARNET_WEB_DIR=' . $appDir . DS);
        $frontDir = GarnetRunner::$frameworkDir . DS . 'FrontBuilder';
        $cwd = getcwd();
        chdir($frontDir);
        $cmd = 'npx cross-env NODE_ENV=production rspack build --config rspack.config.ts';
        echo "  Running: {$cmd}" . PHP_EOL;
        passthru($cmd, $code);
        chdir($cwd);

        if ($code !== 0) {
            self::fail("rspack build failed (exit {$code})");
        }
    }

    private static function copyDir(string $src, string $dst, array $excludeDirs = [], array $excludeFiles = []): void {
        if (!is_dir($dst)) {
            @mkdir($dst, 0o755, true);
        }

        $excludeDirsAbs = array_map(fn ($e) => $src . DS . $e, $excludeDirs);

        // Defence in depth against a destination that turns out to be nested
        // inside its own source (e.g. app-mode's dist/ output landing inside
        // vendor/phpcraftdream/garnet-framework/, which IS the framework
        // copy's own source) — without this, the recursive iterator walks
        // into the not-yet-finished output and copies it into itself,
        // growing without bound rather than erroring. Caller-supplied
        // excludes (e.g. 'dist' above) are the primary defence; this is the
        // backstop for the case where a future caller forgets one. Plain
        // string prefix check (not realpath) to stay consistent with how
        // $path below is matched against $excludeDirsAbs — both are built
        // from the same un-resolved $src string.
        $normSrc = rtrim($src, '/\\');
        $normDst = rtrim($dst, '/\\');

        if ($normDst !== $normSrc && str_starts_with($normDst . DS, $normSrc . DS)) {
            $excludeDirsAbs[] = $normDst;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iter as $item) {
            $path = $item->getPathname();

            // Skip excluded directories (prefix match)
            $skip = false;

            foreach ($excludeDirsAbs as $ex) {
                if ($path === $ex || str_starts_with($path, $ex . DS)) {
                    $skip = true;

                    break;
                }
            }

            if ($skip) {
                continue;
            }

            // Skip excluded files by basename
            if ($item->isFile() && in_array($item->getBasename(), $excludeFiles, true)) {
                continue;
            }

            // Skip *.log
            if ($item->isFile() && str_ends_with($item->getFilename(), '.log')) {
                continue;
            }

            $rel = substr($path, strlen($src) + 1);
            $target = $dst . DS . $rel;

            // SplFileInfo::isDir() can disagree with the OS about NTFS
            // junctions/reparse points (hit this for real: a node_modules
            // junction reported isDir()=false here, then copy() below threw
            // "cannot be a directory"). is_dir() is the native, reliable
            // check — trust it over the cached SplFileInfo type.
            if ($item->isDir() || is_dir($path)) {
                if (!is_dir($target)) {
                    @mkdir($target, 0o755, true);
                }
            } else {
                $tdir = dirname($target);

                if (!is_dir($tdir)) {
                    @mkdir($tdir, 0o755, true);
                }
                copy($path, $target);
            }
        }
    }

    /**
     * Pack the unpacked bundle dir into a self-executing PHP Phar
     * archive. The phar carries a stub that lets the user pick which
     * of the three sibling directories to extract (interactively or
     * via flags), with overwrite enabled by default — so the same
     * phar serves both first-time install and incremental updates
     * (e.g. ship a new framework dir without touching docroot/upload).
     *
     * Requires `phar.readonly=0` at build time. End users don't need
     * any special ini setting to execute the phar.
     */
    private static function buildPhar(
        string $src,
        string $pharFile,
        string $publicDir,
        string $fwDir,
        string $appDir,
        string $runtimeDir,
        string $appName,
    ): void {
        if (file_exists($pharFile)) {
            @unlink($pharFile);
        }

        $phar = new Phar($pharFile, 0, basename($pharFile));
        $phar->startBuffering();
        $phar->buildFromDirectory($src);
        $phar->setStub(self::renderPharStub($publicDir, $fwDir, $appDir, $runtimeDir, $appName));
        $phar->stopBuffering();

        // gzip every file inside — typical Garnet bundle compresses ~2x.
        if (Phar::canCompress(Phar::GZ)) {
            $phar->compressFiles(Phar::GZ);
        }
        @chmod($pharFile, 0o755);
    }

    /**
     * Phar stub: parses CLI flags, lists or extracts the requested
     * sibling dirs into the directory next to the phar (with overwrite).
     * No special ini settings needed on the host to run.
     */
    private static function renderPharStub(string $publicDir, string $fwDir, string $appDir, string $runtimeDir, string $appName): string {
        $q = static fn (string $s): string => "'" . str_replace("'", "\\'", $s) . "'";
        $publicQ = $q($publicDir);
        $fwQ = $q($fwDir);
        $appQ = $q($appDir);
        $runtimeQ = $q($runtimeDir);
        $nameQ = $q($appName);
        $date = date('Y-m-d H:i:s');

        return <<<PHP
#!/usr/bin/env php
<?php
// Garnet bundle phar — generated on {$date}
// Run:  php <this-file.phar> [--all | --public | --framework | --app | --list | --help]
// Without flags drops into an interactive picker.

Phar::mapPhar();

\$APP         = {$nameQ};
\$PUBLIC_DIR  = {$publicQ};
\$FW_DIR      = {$fwQ};
\$APP_DIR     = {$appQ};
\$RUNTIME_DIR = {$runtimeQ};

\$pharPath = __FILE__;
\$target   = getcwd() ?: dirname(\$pharPath);

\$args = \$_SERVER['argv'] ?? [];
array_shift(\$args);

\$pickAll = false;
\$pickPublic = false;
\$pickFw = false;
\$pickApp = false;
\$pickRuntime = false;
\$listOnly = false;
\$wantHelp = false;
\$noConfirm = false;

foreach (\$args as \$arg) {
    switch (\$arg) {
        case '--all':         \$pickAll = true; break;
        case '--public':      \$pickPublic = true; break;
        case '--framework':   \$pickFw = true; break;
        case '--app':         \$pickApp = true; break;
        case '--runtime':     \$pickRuntime = true; break;
        case '--list':        \$listOnly = true; break;
        case '--help':
        case '-h':            \$wantHelp = true; break;
        case '--yes':
        case '-y':            \$noConfirm = true; break;
        default:
            fwrite(STDERR, "Unknown arg: \$arg\\n");
            exit(2);
    }
}

if (\$wantHelp) {
    echo "Garnet deploy phar — {\$APP}\\n";
    echo "Usage: php " . basename(\$pharPath) . " [flags]\\n";
    echo "  --all          extract all three sibling directories (default in interactive mode)\\n";
    echo "  --public       extract only {\$PUBLIC_DIR}/\\n";
    echo "  --framework    extract only {\$FW_DIR}/\\n";
    echo "  --app          extract only {\$APP_DIR}/\\n";
    echo "  --runtime      extract only {\$RUNTIME_DIR}/\\n";
    echo "  --list         list files inside the phar\\n";
    echo "  --yes / -y     skip the confirmation prompt\\n";
    echo "  --help / -h    this message\\n";
    echo "Target dir: \$target (where this phar is invoked from)\\n";
    exit(0);
}

if (\$listOnly) {
    \$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('phar://' . \$pharPath));
    foreach (\$it as \$f) {
        echo str_replace('phar://' . \$pharPath . '/', '', \$f->getPathname()) . "\\n";
    }
    exit(0);
}

// Interactive picker if nothing requested
if (!\$pickAll && !\$pickPublic && !\$pickFw && !\$pickApp && !\$pickRuntime) {
    echo "Garnet deploy: {\$APP}\\n";
    echo "Target dir: \$target\\n";
    echo "Choose what to extract (overwrites existing files):\\n";
    echo "  1) all        — public + framework + app\\n";
    echo "  2) public     — {\$PUBLIC_DIR}/\\n";
    echo "  3) framework  — {\$FW_DIR}/\\n";
    echo "  4) app        — {\$APP_DIR}/\\n";
    echo "  5) runtime    — {\$RUNTIME_DIR}/\\n";
    echo "  q) quit\\n";
    echo "Enter one or more (space-separated, e.g. '3 4'): ";
    \$line = trim((string) fgets(STDIN));
    if (\$line === 'q' || \$line === '') exit(0);
    foreach (preg_split('/\\\\s+/', \$line) as \$p) {
        switch (\$p) {
            case '1': \$pickAll = true; break;
            case '2': \$pickPublic = true; break;
            case '3': \$pickFw = true; break;
            case '4': \$pickApp = true; break;
            case '5': \$pickRuntime = true; break;
        }
    }
    \$noConfirm = true; // already asked
}

\$selected = [];
if (\$pickAll) {
    \$selected = [\$PUBLIC_DIR, \$FW_DIR, \$APP_DIR, \$RUNTIME_DIR];
} else {
    if (\$pickPublic)  \$selected[] = \$PUBLIC_DIR;
    if (\$pickFw)      \$selected[] = \$FW_DIR;
    if (\$pickApp)     \$selected[] = \$APP_DIR;
    if (\$pickRuntime) \$selected[] = \$RUNTIME_DIR;
}
\$selected = array_values(array_unique(\$selected));

if (empty(\$selected)) {
    echo "Nothing selected.\\n";
    exit(0);
}

echo "Will extract into: \$target\\n";
foreach (\$selected as \$d) echo "  - \$d/\\n";

if (!\$noConfirm) {
    echo "Continue? Type YES to confirm: ";
    \$line = trim((string) fgets(STDIN));
    if (\$line !== 'YES') { echo "Aborted.\\n"; exit(1); }
}

\$phar = new Phar(\$pharPath);

// Build the explicit file list for each requested top-level dir.
// Phar::extractTo's "directory name" parameter is finicky across PHP
// versions and platforms (sometimes wants a leading slash, sometimes
// not, sometimes fails entirely). Walking the inner iterator and
// passing the exact list of relative file paths sidesteps all of that.
foreach (\$selected as \$d) {
    echo "  extracting \$d/...\\n";
    \$files = [];
    \$prefix = 'phar://' . \$pharPath . '/' . \$d;
    if (!is_dir(\$prefix)) {
        fwrite(STDERR, "  (skip: \$d not in archive)\\n");
        continue;
    }
    \$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\$prefix));
    \$strip = 'phar://' . \$pharPath . '/';
    \$stripFs = str_replace('\\\\', '/', \$strip);
    foreach (\$it as \$f) {
        if (!\$f->isFile()) continue;
        // Phar uses forward slashes internally; normalize Windows paths
        // BEFORE stripping the prefix (both sides need the same shape).
        \$p = str_replace('\\\\', '/', \$f->getPathname());
        \$files[] = substr(\$p, strlen(\$stripFs));
    }
    if (\$files) \$phar->extractTo(\$target, \$files, true);
}

// Always ship uninstall.sh alongside, if present.
if (file_exists('phar://' . \$pharPath . '/uninstall.sh')) {
    \$phar->extractTo(\$target, 'uninstall.sh', true);
    @chmod(\$target . DIRECTORY_SEPARATOR . 'uninstall.sh', 0755);
}

echo "Done.\\n";

__HALT_COMPILER();
PHP;
    }

    /**
     * Render a standalone uninstall.sh that knows the three sibling dir
     * names from this bundle. It removes whichever of them exist next to
     * itself, then deletes itself. Self-contained — no PHP needed on the
     * host. LF line endings (don't trip up `bash` on Linux).
     */
    private static function renderUninstallScript(string $publicDir, string $fwDir, string $appDir, string $runtimeDir, string $appName): string {
        $date = date('Y-m-d H:i:s');
        // bash single-quoted literals — pass dir names through addslashes
        // for ' just in case someone supplied weird --public-dir=foo'bar.
        $q = static fn (string $s): string => "'" . str_replace("'", "'\\''", $s) . "'";
        $publicQ = $q($publicDir);
        $fwQ = $q($fwDir);
        $appQ = $q($appDir);
        $runtimeQ = $q($runtimeDir);

        return <<<SH
#!/usr/bin/env bash
# Generated by `php garnet bundle` on {$date}
# Removes the three sibling directories this bundle installed
# (docroot, framework, app), relative to wherever this script lives.
#
# Usage:
#   bash uninstall.sh           # prompts before deleting
#   bash uninstall.sh --yes     # no prompt
#   bash uninstall.sh --dry-run # show what would happen, change nothing

set -euo pipefail

DIR="\$(cd "\$(dirname "\$0")" && pwd)"
APP_NAME={$appName}

DIRS=(
    {$publicQ}
    {$fwQ}
    {$appQ}
    {$runtimeQ}
)

YES=0
DRY=0
for arg in "\$@"; do
    case "\$arg" in
        --yes|-y)   YES=1 ;;
        --dry-run)  DRY=1 ;;
        *)
            echo "Unknown arg: \$arg" >&2
            echo "Usage: \$0 [--yes] [--dry-run]" >&2
            exit 2
            ;;
    esac
done

echo "Uninstalling \$APP_NAME bundle at: \$DIR"
echo "  will remove:"
for d in "\${DIRS[@]}"; do
    path="\$DIR/\$d"
    if [ -d "\$path" ]; then
        size=\$(du -sh "\$path" 2>/dev/null | cut -f1)
        echo "    - \$d  (\$size)"
    else
        echo "    - \$d  (missing)"
    fi
done

if [ "\$DRY" -eq 1 ]; then
    echo "(dry-run — nothing removed)"
    exit 0
fi

if [ "\$YES" -ne 1 ]; then
    printf "Type YES to confirm: "
    read -r answer
    if [ "\$answer" != "YES" ]; then
        echo "Aborted."
        exit 1
    fi
fi

for d in "\${DIRS[@]}"; do
    path="\$DIR/\$d"
    if [ -d "\$path" ]; then
        echo "  rm -rf \$d"
        rm -rf "\$path"
    fi
done

# Self-delete so the bundle's footprint is gone.
echo "  rm uninstall.sh"
rm -f "\$DIR/uninstall.sh"

echo "Done."

SH;
    }

    private static function rmrf(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        // chmod-then-delete is needed on Windows because readonly files
        // (.git/objects/**, some composer caches) silently fail unlink().
        // The previous "@unlink" hid those failures and left half the
        // tree in place — that's how a "rebuild" inherited stale files.
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $failed = [];

        foreach ($items as $item) {
            $p = $item->getPathname();
            @chmod($p, 0o777);
            $ok = $item->isDir() ? @rmdir($p) : @unlink($p);

            if (!$ok && file_exists($p)) {
                $failed[] = $p;
            }
        }
        @chmod($dir, 0o777);

        if (!@rmdir($dir) && is_dir($dir)) {
            $failed[] = $dir;
        }

        if ($failed) {
            $list = implode("\n    ", array_slice($failed, 0, 10));
            $more = count($failed) > 10 ? "\n    ... and " . (count($failed) - 10) . ' more' : '';
            self::fail('rmrf could not delete ' . count($failed) . " entries under {$dir}:\n    {$list}{$more}");
        }
    }

    private static function statTree(string $dir): array {
        if (!is_dir($dir)) {
            return [0, 0];
        }
        $files = 0;
        $bytes = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $item) {
            if ($item->isFile()) {
                $files++;
                $bytes += $item->getSize();
            }
        }

        return [$files, $bytes];
    }

    private static function humanBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $b = (float)$bytes;

        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $b, $units[$i]);
    }

    private static function step(string $num, string $label): void {
        echo "\033[1;36m[{$num}]\033[0m {$label}" . PHP_EOL;
    }

    private static function fail(string $msg): void {
        echo "\033[31mError:\033[0m {$msg}" . PHP_EOL;

        exit(1);
    }

    private static function help(): void {
        echo <<<HELP

  \033[1mphp garnet bundle [flags]\033[0m

  \033[1mWHAT IT DOES\033[0m
  ────────────────────────────────────────────────────────────────────────
  Builds a self-contained, portable deploy artifact for the active app:
  4 sibling directories (public / framework / app / runtime), each
  path-agnostic (no hardcoded absolute paths, no dev-only files), ready
  to drop onto a fresh host — first-time install, not an incremental
  update (use \033[36mgarnet deploy:diff\033[0m for that once a host already
  has a bundle on it).

    dist/<AppName>/
      ├── <public-dir>/     copy of Public/, index.php rewritten to boot
      │                     via the runtime dir's _shared_index.php
      ├── <framework-dir>/  framework Kernel/Bundle/vendor (+ generated
      │                     *Gen.php asset-bridge files)
      ├── <app-dir>/        app PHP classes + composer.json/lock (no
      │                     WorkDir, no ./garnet, no .env)
      └── <runtime-dir>/    garnet CLI dispatcher (path-rewritten),
                            _shared_index.php, .env, WorkDir/ skeleton

  Works for both source layouts:
    - \033[1mlegacy monorepo\033[0m — Apps/<App>/ + Framework/ siblings under
      GARNET_ROOT.
    - \033[1mstandalone app\033[0m (composer-vendored framework, the layout
      `app:create` scaffolds) — the app is its own root, framework lives
      in vendor/phpcraftdream/garnet-framework/. The framework-dir copy
      is sourced from there specifically (not a separate framework
      checkout) so it can never disagree with what `php garnet build`
      (run from this app) actually produced — the failure mode a manual
      `ssh:put` of a pristine framework checkout is prone to (a stray
      locally-built *Gen.php sitting in that checkout silently ships a
      stale asset-bridge referencing hashes that no longer exist).

  \033[1mFLAGS\033[0m
  ────────────────────────────────────────────────────────────────────────
    --skip-build             Skip the rspack production build (assumes
                              Public/assets/ is already built).
    --no-vendor               Don't copy any vendor/ directories — use
                              when the host installs dependencies itself.
                              Standalone-app mode: also drops the app's
                              OTHER composer deps (e.g. a real app's own
                              libraries), not just the framework's.
    --with-config             Include WorkDir/Config/*.ini (real DB/SSH
                              credentials) in the runtime tree. OFF by
                              default — Config/ is server-owned state;
                              re-bundling must not silently overwrite a
                              live host's credentials. Only pass this for
                              the very first bootstrap of a brand-new
                              host, or after intentionally rotating creds
                              locally and pushing them up.
    --zip                     Also produce dist/<AppName>.tar.gz.
    --flat-zip                Pack the archive without a wrapper dir
                              (use with --zip) — `tar -xzf … -C ~/www`
                              drops the 4 sibling dirs straight into the
                              target instead of nesting them one level.
    --keep-dir                Keep the unpacked dist/<AppName>/ tree
                              after --zip / the default phar build
                              succeeds (normally removed once the
                              deliverable is on disk — useful when
                              debugging the bundle layout).
    --no-phar                 Skip phar generation. By default `bundle`
                              produces a self-executing dist/<AppName>.phar
                              — the end user runs `php <name>.phar` and
                              picks which sibling dir(s) to extract
                              (interactive picker, or --all / --public /
                              --framework / --app / --runtime flags).
    --public-dir=<name>       Rename the docroot folder (default: public).
    --framework-dir=<name>    Rename the framework folder
                              (default: garnet-framework).
    --app-dir=<name>          Rename the app folder
                              (default: garnet-app-<appname>).
    --runtime-dir=<name>      Rename the runtime folder
                              (default: garnet-runtime-<public-name>).
    --public-name=<name>      Rebrand public URL paths: renames
                              assets/<AppName>/ and upload/<AppName>/
                              docroot subdirs to <name>, and rewrites the
                              matching URL literals in *Gen.php + built
                              JS/CSS/HTML/SVG files.

  Folder-name flags fall back to \033[2mWorkDir/Config*/deploy.ini\033[0m
  (public_dir / framework_dir / app_dir / runtime_dir / public_name) when
  not passed on the CLI, then to the built-in defaults above.

  \033[1mFIRST DEPLOY TO A FRESH HOST\033[0m
  ────────────────────────────────────────────────────────────────────────
    php garnet bundle --with-config          # first boot: push real creds too
    php garnet ssh:put dist/<App>/<public-dir>    "<public-dir>"    --cd-remote
    php garnet ssh:put dist/<App>/<framework-dir> "<framework-dir>" --cd-remote
    php garnet ssh:put dist/<App>/<app-dir>       "<app-dir>"       --cd-remote
    php garnet ssh:put dist/<App>/<runtime-dir>   "<runtime-dir>"   --cd-remote
    # then, from inside <runtime-dir> on the host:
    php garnet deploy                         # maintenance → backup → migrate → cache → off

  Once a host has a bundle on it, prefer \033[36mgarnet deploy:diff\033[0m for
  routine updates — it ships only the delta since the last deploy,
  seconds instead of a full re-upload, and doesn't need the `--with-config`
  question again.

  --help / -h / help     this message
HELP;
        echo PHP_EOL;
    }

    /**
     * Render the _shared_index.php that lives in the runtime folder.
     * Reads .env from its own directory to locate sibling bundle dirs,
     * then sets GARNET_APP_DIR + GARNET_WORKDIR_DIR and boots the app.
     */
    /**
     * Rewrite the repo `garnet` CLI into the runtime-dir variant. Two source
     * shapes exist, selected by `$isAppMode`:
     *
     * - Legacy monorepo `./garnet`: `define('GARNET_ROOT', __DIR__);` +
     *   `require ... 'Framework' . DS . 'vendor' ...`. GARNET_ROOT becomes
     *   the bundle root and GARNET_APP_DIR/NAME/WORKDIR/RUNTIME env vars are
     *   planted relative to it.
     * - Standalone app `./garnet` (composer-vendored framework): a much
     *   thinner file — `require __DIR__ . '/vendor/autoload.php'` +
     *   `GarnetRunner::main(__DIR__, $argv)`, both of which point at
     *   wherever the runtime copy physically sits, which is WRONG once
     *   moved into the runtime-dir sibling (its own vendor/autoload.php
     *   doesn't have the framework's classes — that vendor/ is deliberately
     *   NOT shipped there, only in the framework-dir sibling — and
     *   GarnetRunner::main() needs the APP dir, not the runtime dir, as its
     *   first argument). Silently leaving this content unmodified (the
     *   legacy patterns simply don't match) produced a dispatcher that
     *   failed its own boot check on every single app-mode deploy — caught
     *   only because `deploy:diff`'s sync step already falls back to
     *   keeping the previous dispatcher on a failed boot check, so it never
     *   surfaced as a hard outage, just a dispatcher that silently never
     *   updated.
     *
     * Single source of truth used by both `bundle` and `deploy:diff` (so the
     * runtime dispatcher never drifts from the repo's routes). Returns null
     * if the source file is missing.
     */
    public static function renderRuntimeGarnet(
        string $repoGarnetSrc,
        string $appDirName,
        string $appName,
        string $frameworkDirName,
        bool $isAppMode = false
    ): ?string {
        if (!is_file($repoGarnetSrc)) {
            return null;
        }
        $contents = (string)file_get_contents($repoGarnetSrc);

        if ($isAppMode) {
            return self::rewriteAppModeRuntimeGarnet($contents, $appDirName, $appName, $frameworkDirName);
        }

        $contents = str_replace(
            "define('GARNET_ROOT', __DIR__);",
            "define('GARNET_ROOT', dirname(__DIR__));\n"
            . "putenv('GARNET_APP_DIR=' . GARNET_ROOT . DS . '{$appDirName}');\n"
            . "putenv('GARNET_APP_NAME={$appName}');\n"
            . "putenv('GARNET_WORKDIR_DIR=' . __DIR__ . DS . 'WorkDir');\n"
            . "putenv('GARNET_RUNTIME_DIR=' . __DIR__);",
            $contents
        );

        $contents = str_replace(
            "GARNET_ROOT . DS . 'Framework' . DS . 'vendor' . DS . 'autoload.php'",
            "GARNET_ROOT . DS . '{$frameworkDirName}' . DS . 'vendor' . DS . 'autoload.php'",
            $contents
        );

        return $contents;
    }

    /**
     * Transform for the standalone-app `./garnet` shape — see the canonical
     * scaffold at Templates/Application/garnet, which every `app:create`d
     * app starts from verbatim:
     *
     *   require_once __DIR__ . '/vendor/autoload.php';
     *   putenv('GARNET_APP_DIR=' . __DIR__);
     *   \…\GarnetRunner::main(__DIR__, $argv);
     *
     * All three `__DIR__` uses assume the file runs from the app's own
     * root — true for local dev, false once copied into the runtime-dir
     * sibling: the runtime copy's own vendor/ deliberately does NOT carry
     * the framework (that's the framework-dir sibling, kept separate so it
     * can never drift from what the app actually built), and the app dir
     * is a sibling, not `__DIR__` itself.
     *
     * Rewrites, in order:
     *   1. autoload → load the FRAMEWORK sibling's vendor/autoload.php
     *      (it has the Kernel/GarnetCli classes + the framework's own
     *      deps), not the app's.
     *   2. putenv → GARNET_APP_DIR/FRAMEWORK_DIR point at the sibling app/
     *      framework dirs; GARNET_APP_NAME/WORKDIR_DIR/RUNTIME_DIR added to
     *      match what the legacy transform plants (GARNET_FRAMEWORK_DIR is
     *      the one app-mode needs that legacy mode doesn't — legacy always
     *      resolves the framework relative to GARNET_ROOT, but app-mode's
     *      whole premise is that the framework can live anywhere).
     *   3. GarnetRunner::main()'s first argument → the sibling app dir,
     *      not wherever this dispatcher physically sits.
     *
     * Falls back to returning $contents unmodified if the expected scaffold
     * lines aren't found (e.g. a hand-edited `./garnet`) rather than
     * guessing — callers should treat an unchanged-looking result as a sign
     * to verify the runtime dispatcher by hand.
     */
    private static function rewriteAppModeRuntimeGarnet(
        string $contents,
        string $appDirName,
        string $appName,
        string $frameworkDirName
    ): string {
        $contents = str_replace(
            "require_once __DIR__ . '/vendor/autoload.php';",
            "require_once dirname(__DIR__) . '/{$frameworkDirName}/vendor/autoload.php';",
            $contents
        );

        $contents = str_replace(
            "putenv('GARNET_APP_DIR=' . __DIR__);",
            "putenv('GARNET_APP_DIR=' . dirname(__DIR__) . DIRECTORY_SEPARATOR . '{$appDirName}');\n"
            . "putenv('GARNET_FRAMEWORK_DIR=' . dirname(__DIR__) . DIRECTORY_SEPARATOR . '{$frameworkDirName}');\n"
            . "putenv('GARNET_APP_NAME={$appName}');\n"
            . "putenv('GARNET_WORKDIR_DIR=' . __DIR__ . DIRECTORY_SEPARATOR . 'WorkDir');\n"
            . "putenv('GARNET_RUNTIME_DIR=' . __DIR__);",
            $contents
        );

        $contents = str_replace(
            'GarnetRunner::main(__DIR__, $argv);',
            'GarnetRunner::main(dirname(__DIR__) . DIRECTORY_SEPARATOR . \'' . $appDirName . '\', $argv);',
            $contents
        );

        return $contents;
    }

    private static function renderSharedIndex(): string {
        return <<<'PHP'
<?php declare(strict_types=1);
// Garnet runtime bootstrap — reads .env from this directory (the runtime
// folder) and delegates to the app's run_web.php.
$_gr = __DIR__;
$_env = @parse_ini_file($_gr . '/.env');
if (!is_array($_env)) {
    http_response_code(503);
    echo 'Garnet: runtime .env missing or unreadable';
    exit(1);
}
$_fw  = realpath($_gr . '/' . ($_env['BUNDLE_FRAMEWORK_DIR'] ?? ''));
$_app = realpath($_gr . '/' . ($_env['BUNDLE_APP_DIR']       ?? ''));
$_wd  = realpath($_gr . '/' . ($_env['BUNDLE_WORKDIR_DIR']   ?? ''));
$_pub = realpath($_gr . '/' . ($_env['BUNDLE_PUBLIC_DIR']    ?? ''));
if (!$_fw || !$_app) {
    http_response_code(503);
    echo 'Garnet: bundle dirs not found — check runtime .env';
    exit(1);
}
putenv("GARNET_APP_DIR={$_app}");
putenv("GARNET_FRAMEWORK_DIR={$_fw}");
if ($_wd)  putenv("GARNET_WORKDIR_DIR={$_wd}");
if ($_pub) putenv("GARNET_PUBLIC_DIR={$_pub}");
$_run = $_app . '/run_web.php';
unset($_gr, $_env, $_fw, $_wd, $_app, $_pub);
require_once $_run;
PHP;
    }

    /**
     * Try to bootstrap the active app so that IniConfig::deploy() is
     * available, then return the deployment-layout keys from deploy.ini.
     * Completely non-fatal — returns [] on any failure (missing app,
     * missing run_cmd.php, missing deploy.ini, missing keys).
     */
    private static function readDeployDefaults(): array {
        try {
            $appName = GarnetEnv::readAppName();

            if ($appName === '') {
                return [];
            }
            $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

            if (!file_exists($runCmd)) {
                return [];
            }
            $GLOBALS['argv'] = [$runCmd, 'noop'];
            $GLOBALS['argc'] = 2;
            ob_start();
            require $runCmd;
            ob_end_clean();
            $deploy = \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::deploy();

            return [
                'public_dir' => $deploy->paramString('public_dir',    ''),
                'framework_dir' => $deploy->paramString('framework_dir', ''),
                'app_dir' => $deploy->paramString('app_dir',       ''),
                'runtime_dir' => $deploy->paramString('runtime_dir',   ''),
                'public_name' => $deploy->paramString('public_name',   ''),
            ];
        } catch (Throwable) {
            return [];
        }
    }
}
