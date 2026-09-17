<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\Bundle {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\GarnetBuildCommand;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\PublicPathRebrander;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Копирование деревьев: сборка фронта, чистка цели, три копии.
     *
     * Исключения при копировании — не косметика. Без исключения `dist`
     * сборка заходит в собственный незаконченный результат и копирует его в
     * себя (однажды выросло до 2.5 ГБ, прежде чем процесс убили), а
     * node_modules внутри фреймворка — NTFS-junction, на которой copy()
     * спотыкается вовсе.
     */
    trait BundleCopyTrait {
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

        /**
         * Шаг 1: собрать фронт в production-режиме (или не собирать по --skip-build).
         */
        private static function buildAssets(array $c): void {
            ['skipBuild' => $skipBuild, 'root' => $root] = $c;

            // 1. Production build of frontend assets
            if (!$skipBuild) {
                self::step('1/6', 'Building frontend assets (production)');
                self::runRspackBuild($root);
                echo PHP_EOL;
            } else {
                self::step('1/6', 'Skipping rspack build (--skip-build)');
                echo PHP_EOL;
            }
        }

        /**
         * Шаг 2: очистить цель.
         *
         * Заодно убирается архив от прошлого прогона: иначе сборка без --zip
         * оставляет рядом старый tar.gz, и его легко принять за свежий.
         */
        private static function cleanDist(array $c): void {
            ['distApp' => $distApp, 'distRoot' => $distRoot, 'appName' => $appName] = $c;

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
        }

        /**
         * Шаг 3: docroot, и переписанный index.php, который грузится через
         * runtime-каталог.
         */
        private static function copyPublic(array $c): array {
            ['publicSrc' => $publicSrc, 'distApp' => $distApp, 'publicDirName' => $publicDirName, 'runtimeDirName' => $runtimeDirName] = $c;

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

            return ['distPublic' => $distPublic];
        }

        /**
         * Шаг 4: код приложения без изменяемого состояния.
         *
         * WorkDir целиком уезжает в runtime-каталог, а из vendor исключается
         * копия самого фреймворка: он копируется отдельно и свежим, и две копии
         * рано или поздно разошлись бы.
         */
        private static function copyApp(array $c): array {
            ['appSrc' => $appSrc, 'distApp' => $distApp, 'appDirName' => $appDirName, 'noVendor' => $noVendor, 'isAppMode' => $isAppMode, 'frameworkDirName' => $frameworkDirName] = $c;

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
                // Prod test/debug gates and the admin console auth token — none
                // of these belong in a shipped bundle (see .gitignore comments
                // and Kernel/Io/GarnetCli/Admin/AdminAuth.php).
                '.garnet_admin',
                '.allow_tests',
                '.test-mode',
                '.garnet_debug_token',
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
                $rewritten = str_replace(
                    "require_once __DIR__ . '/vendor/autoload.php';",
                    "require_once dirname(__DIR__) . '/{$frameworkDirName}/vendor/autoload.php';\n"
                    . "require_once __DIR__ . '/vendor/autoload.php';",
                    $rewritten
                );

                if ($rewritten !== $orig) {
                    file_put_contents($appAutoload, $rewritten);
                    echo '  app autoload patched' . PHP_EOL;
                }
            }

            // App-mode's run_cmd.php scaffold (Templates/Application/run_cmd.php,
            // and every app:create'd app's own copy) hardcodes
            // `<App>::setPublicDirInit(__DIR__ . '/WorkDir/public/')` — a local-
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

            return ['distAppApp' => $distAppApp];
        }

        /**
         * Шаг 5: фреймворк — и его собственный vendor, которого у
         * composer-установленной копии нет.
         *
         * Composer расплющивает зависимости пакета в vendor приложения, поэтому
         * в бандле их приходится материализовать заново: отдельный
         * каталог-фреймворк обещает «здесь и классы, и их зависимости».
         */
        private static function copyFramework(array $c): array {
            ['frameworkSrc' => $frameworkSrc, 'distApp' => $distApp, 'frameworkDirName' => $frameworkDirName, 'noVendor' => $noVendor, 'isAppMode' => $isAppMode] = $c;

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
            // in docs/guides/deploy.md) and `node_modules` (its own copy is an NTFS
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

            return ['distFw' => $distFw];
        }
    }
}
