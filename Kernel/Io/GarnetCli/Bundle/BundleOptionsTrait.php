<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Bundle {
    use Phar;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    /**
     * Что просили собрать: флаги, имена четырёх каталогов и справка.
     *
     * Имена решаются в три уровня — флаг командной строки, deploy.ini,
     * встроенное значение по умолчанию, — и команда обязана сказать вслух,
     * откуда взяла каждое: перепутанное имя каталога означает выкладку мимо
     * живого места, а такую ошибку хочется увидеть до заливки, а не после.
     */
    trait BundleOptionsTrait {
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
                $deploy = IniConfig::deploy();

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

        private static function help(): void {
            echo <<<HELP

  php garnet bundle [flags]

  WHAT IT DOES
  ────────────────────────────────────────────────────────────────────────
  Builds a self-contained, portable deploy artifact for the active app:
  4 sibling directories (public / framework / app / runtime), each
  path-agnostic (no hardcoded absolute paths, no dev-only files), ready
  to drop onto a fresh host — first-time install, not an incremental
  update (use garnet deploy:diff for that once a host already
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
    - legacy monorepo — Apps/<App>/ + Framework/ siblings under
      GARNET_ROOT.
    - standalone app (composer-vendored framework, the layout
      `app:create` scaffolds) — the app is its own root, framework lives
      in vendor/phpcraftdream/garnet-framework/. The framework-dir copy
      is sourced from there specifically (not a separate framework
      checkout) so it can never disagree with what `php garnet build`
      (run from this app) actually produced — the failure mode a manual
      `ssh:put` of a pristine framework checkout is prone to (a stray
      locally-built *Gen.php sitting in that checkout silently ships a
      stale asset-bridge referencing hashes that no longer exist).

  FLAGS
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

  Folder-name flags fall back to WorkDir/Config*/deploy.ini
  (public_dir / framework_dir / app_dir / runtime_dir / public_name) when
  not passed on the CLI, then to the built-in defaults above.

  FIRST DEPLOY TO A FRESH HOST
  ────────────────────────────────────────────────────────────────────────
    php garnet bundle --with-config          # first boot: push real creds too
    php garnet ssh:put dist/<App>/<public-dir>    "<public-dir>"    --cd-remote
    php garnet ssh:put dist/<App>/<framework-dir> "<framework-dir>" --cd-remote
    php garnet ssh:put dist/<App>/<app-dir>       "<app-dir>"       --cd-remote
    php garnet ssh:put dist/<App>/<runtime-dir>   "<runtime-dir>"   --cd-remote
    # then, from inside <runtime-dir> on the host:
    php garnet deploy                         # maintenance → backup → migrate → cache → off

  Once a host has a bundle on it, prefer garnet deploy:diff for
  routine updates — it ships only the delta since the last deploy,
  seconds instead of a full re-upload, and doesn't need the `--with-config`
  question again.

  --help / -h / help     this message
HELP;
            echo PHP_EOL;
        }

        /**
         * Разобрать аргументы и решить, что и куда собирается.
         *
         * Имена четырёх каталогов-соседей решаются в три уровня — флаг,
         * deploy.ini, значение по умолчанию — и команда печатает, откуда взяла
         * каждое: перепутанный каталог означает выкладку мимо живого места.
         *
         * @return array<string, mixed> контекст сборки для шагов ниже
         */
        private static function resolveOptions(array $args): array {
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
                    echo 'Warning: phar.readonly is still 1 after re-exec — skipping phar.' . PHP_EOL;
                    $makePhar = false;
                } else {
                    echo 'Note: relaunching with phar.readonly=0 (auto)' . PHP_EOL . PHP_EOL;
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
            echo "=== Garnet Bundle: {$appName} ===" . PHP_EOL;
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

            return ['appName' => $appName, 'appNameLower' => $appNameLower, 'isAppMode' => $isAppMode, 'skipBuild' => $skipBuild, 'noVendor' => $noVendor, 'withConfig' => $withConfig, 'makeZip' => $makeZip, 'flatZip' => $flatZip, 'keepDir' => $keepDir, 'makePhar' => $makePhar, 'publicDirName' => $publicDirName, 'frameworkDirName' => $frameworkDirName, 'appDirName' => $appDirName, 'publicName' => $publicName, 'runtimeDirName' => $runtimeDirName, 'root' => $root, 'distRoot' => $distRoot, 'distApp' => $distApp, 'publicSrc' => $publicSrc, 'appSrc' => $appSrc, 'frameworkSrc' => $frameworkSrc];
        }
    }
}
