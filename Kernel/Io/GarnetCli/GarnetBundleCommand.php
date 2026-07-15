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
        $appName = GarnetEnv::requireAppName();
        $appNameLower = strtolower($appName);

        // `bundle` builds a self-contained deploy artifact by copying the
        // monorepo's `Apps/<App>` + `Framework/` siblings. That tree only
        // exists in the legacy monorepo; in the standalone app layout (an app
        // with a composer-vendored framework) the sources are shaped
        // differently and a naively-copied bundle would be subtly broken. Until
        // app-mode bundling is reworked AND verified against a real deploy,
        // fail fast with guidance instead of emitting a bad artifact —
        // incremental deploys already work via `garnet deploy:diff`.
        $isAppMode = GarnetRunner::$appDir !== ''
            && str_replace('\\', '/', (string)realpath(GarnetRunner::$appDir))
               !== str_replace('\\', '/', (string)realpath(GarnetRunner::$frameworkDir));

        if ($isAppMode) {
            self::fail(
                "`garnet bundle` is not supported in the standalone app layout yet.\n"
                . "  Use `garnet deploy:diff` to push changes to an existing host.\n"
                . "  (Full app-mode bundling is a tracked limitation: it needs a\n"
                . '   structural rewrite plus real-deploy verification.)',
            );
        }

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
                $cmd = sprintf(
                    '%s -d phar.readonly=0 %s bundle %s %s',
                    escapeshellarg(PHP_BINARY),
                    escapeshellarg(GARNET_ROOT . DS . 'garnet'),
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
        $distRoot = $root . DS . 'dist';
        $distApp = $distRoot . DS . $appName;

        $publicSrc = GarnetEnv::getPublicDir($appName);
        $appSrc = $root . DS . 'Apps' . DS . $appName;
        $frameworkSrc = $root . DS . 'Framework';

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
        echo "  -> {$distAppApp}" . PHP_EOL . PHP_EOL;

        // 5. Copy framework
        self::step('5/6', 'Copying framework');
        $distFw = $distApp . DS . $frameworkDirName;
        @mkdir($distFw, 0o755, true);

        $fwExcludes = ['.idea', '.vscode', '.vs', '.xcodeproj', '.atom', '.git'];
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
        echo "  -> {$distFw}" . PHP_EOL . PHP_EOL;

        // 6. Assemble runtime directory
        // Contains the garnet CLI, _shared_index.php, .env (with all BUNDLE_*
        // keys), and the WorkDir skeleton. App and framework dirs are now
        // path-agnostic and don't carry any runtime mutable state.
        self::step('6/6', "Assembling runtime dir: {$runtimeDirName}/");
        $distRuntime = $distApp . DS . $runtimeDirName;
        @mkdir($distRuntime, 0o755, true);

        // garnet CLI — lives in runtime dir; sets GARNET_ROOT to bundle root
        // and points GARNET_APP_DIR at the actual app dir sibling.
        $garnetSrc = $root . DS . 'garnet';
        $contents = self::renderRuntimeGarnet($garnetSrc, $appDirName, $appName, $frameworkDirName);

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
        putenv('COMMON_GARNET_WEB_DIR=' . $root . DS);
        $frontDir = $root . DS . 'FrontBuilder';
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

            if ($item->isDir()) {
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

    /**
     * Render the _shared_index.php that lives in the runtime folder.
     * Reads .env from its own directory to locate sibling bundle dirs,
     * then sets GARNET_APP_DIR + GARNET_WORKDIR_DIR and boots the app.
     */
    /**
     * Rewrite the repo `garnet` CLI into the runtime-dir variant: GARNET_ROOT
     * becomes the bundle root, GARNET_APP_DIR/NAME/WORKDIR/RUNTIME env vars are
     * planted, and the framework autoload path points at the versioned
     * framework dir. Single source of truth used by both `bundle` and
     * `deploy:diff` (so the runtime dispatcher never drifts from the repo's
     * routes). Returns null if the source file is missing.
     */
    public static function renderRuntimeGarnet(
        string $repoGarnetSrc,
        string $appDirName,
        string $appName,
        string $frameworkDirName
    ): ?string {
        if (!is_file($repoGarnetSrc)) {
            return null;
        }
        $contents = (string)file_get_contents($repoGarnetSrc);

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
