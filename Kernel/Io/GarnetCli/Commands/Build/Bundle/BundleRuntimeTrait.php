<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\Bundle {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;

    /**
     * Сборка runtime-каталога: чем бандл будет запускаться на хосте.
     *
     * Здесь пишутся garnet, _shared_index.php и два .env с относительными
     * путями до четырёх каталогов-соседей — именно относительными, чтобы
     * бандл можно было развернуть в любом месте хоста.
     */
    trait BundleRuntimeTrait {
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
         * Шаг 6: runtime-каталог — garnet, _shared_index.php, два .env, скелет
         * WorkDir и uninstall.sh.
         *
         * Продуктовый Config по умолчанию НЕ копируется: он принадлежит хосту.
         * Однажды повторная выкладка молча перезаписала живой db.ini — с тех
         * пор только по явному --with-config.
         */
        private static function assembleRuntime(array $c): void {
            ['distApp' => $distApp, 'runtimeDirName' => $runtimeDirName, 'isAppMode' => $isAppMode, 'root' => $root, 'appDirName' => $appDirName, 'appName' => $appName, 'frameworkDirName' => $frameworkDirName, 'publicDirName' => $publicDirName, 'distAppApp' => $distAppApp, 'appSrc' => $appSrc, 'withConfig' => $withConfig] = $c;

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
        }
    }
}
