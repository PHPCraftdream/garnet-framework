<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use Composer\InstalledVersions;

class GarnetPrepareCommand {
    public static function run(array $args): void {
        $app = GarnetEnv::loadApp();

        // The dev server (tooling/server/garnet-serve.mjs) resolves the
        // static root itself from --public, so there's no web-server config
        // to patch here anymore — `prepare`'s job is purely to materialise
        // runtime dirs + assets + the app-info JSON the frontend build reads.

        self::ensureFrontBuilderModulesLink();

        $app->touchDirs();
        $app->copyAssets();
        $app->dumpFrontLang();

        $data = $app->toArray();

        // Ensure all paths are absolute (realpath where possible)
        $pathKeys = ['appDir', 'publicDir', 'assetsDir', 'assetsGenDir', 'assetsDirFw',
            'assetsDirFwJs', 'assetsDirFwCss', 'workDir', 'configProdDir', 'configDevDir',
            'fileCacheDir', 'logErrorDir', 'uploadDir', 'twigCacheDir'];

        foreach ($pathKeys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $real = realpath($data[$key]);

                if ($real !== false) {
                    $data[$key] = str_replace('\\', '/', $real);
                } else {
                    // Path may not exist yet — normalize slashes at minimum
                    $data[$key] = str_replace('\\', '/', $data[$key]);
                }
            }
        }

        // Resolve Framework front-end sources dir via Composer package or fallback
        $frameworkFrontDir = self::resolveFrameworkFrontDir();

        if ($frameworkFrontDir !== null) {
            $data['frameworkFrontDir'] = $frameworkFrontDir;
        }

        // Ensure bundle paths are also absolute
        if (isset($data['bundles']) && is_array($data['bundles'])) {
            $bundlePathKeys = ['bundleDir', 'backendDir', 'frontendDir', 'twigTemplatesDir', 'twigCacheDir', 'workDir'];

            foreach ($data['bundles'] as &$bundle) {
                foreach ($bundlePathKeys as $key) {
                    if (isset($bundle[$key]) && is_string($bundle[$key])) {
                        $real = realpath($bundle[$key]);

                        if ($real !== false) {
                            $bundle[$key] = str_replace('\\', '/', $real);
                        } else {
                            $bundle[$key] = str_replace('\\', '/', $bundle[$key]);
                        }
                    }
                }
            }
            unset($bundle);
        }

        echo json_encode($data, JSON_PRETTY_PRINT);

        exit(0);
    }

    /**
     * Re-link `<package>/node_modules` → `<package>/FrontBuilder/node_modules`.
     *
     * Build dependencies are declared in FrontBuilder/package.json, but the
     * sources that import them live in Bundle/Front/ — and both the bundler
     * and PostCSS resolve modules by walking UP from the importing file, a
     * path that never reaches FrontBuilder/. The link at the package root is
     * what puts node_modules on that walk.
     *
     * `composer update` deletes the package directory wholesale and re-extracts
     * it, so the link (and the installed modules) disappear on every framework
     * upgrade. The build then fails with a message that names neither cause nor
     * cure — `Can't resolve 'tailwindcss'` — which cost a real debugging
     * session to trace back to a missing symlink. Restoring it here, in the
     * documented pre-build step, is cheaper than documenting the workaround.
     *
     * Messages go to STDERR: stdout carries the app-info JSON the build reads.
     */
    private static function ensureFrontBuilderModulesLink(): void {
        $pkgDir = self::resolveFrameworkDir();

        if ($pkgDir === null) {
            return;
        }
        $link = $pkgDir . DIRECTORY_SEPARATOR . 'node_modules';
        $target = $pkgDir . DIRECTORY_SEPARATOR . 'FrontBuilder' . DIRECTORY_SEPARATOR . 'node_modules';

        // A junction/symlink reports as a directory, so this covers "already
        // linked" and "someone installed modules here for real" alike.
        if (is_dir($link) || is_link($link)) {
            return;
        }

        if (!is_dir($target)) {
            fwrite(STDERR, "prepare: FrontBuilder/node_modules отсутствует — сначала выполните `npm ci` в {$pkgDir}/FrontBuilder\n");

            return;
        }

        // Windows: junction, а не симлинк — симлинки там требуют прав
        // администратора или режима разработчика, junction на каталог не
        // требует ничего.
        $ok = DIRECTORY_SEPARATOR === '\\'
            ? self::makeJunction($link, $target)
            : @symlink($target, $link);

        fwrite(STDERR, $ok
            ? "prepare: восстановлена ссылка node_modules → FrontBuilder/node_modules\n"
            : "prepare: не удалось создать ссылку {$link} → {$target}, сборка может упасть на разрешении модулей\n");
    }

    private static function makeJunction(string $link, string $target): bool {
        $cmd = 'mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($target);
        exec('cmd /c ' . escapeshellarg($cmd) . ' 2>&1', $out, $code);

        return $code === 0;
    }

    /**
     * Каталог самого пакета фреймворка (не Bundle/Front внутри него).
     */
    private static function resolveFrameworkDir(): ?string {
        if (class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('phpcraftdream/garnet-framework')) {
            $pkgPath = InstalledVersions::getInstallPath('phpcraftdream/garnet-framework');
            $real = $pkgPath === null ? false : realpath($pkgPath);

            if ($real !== false) {
                return $real;
            }
        }

        if (defined('GARNET_ROOT')) {
            $real = realpath(GARNET_ROOT . DIRECTORY_SEPARATOR . 'Framework');

            if ($real !== false) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Resolve Framework/Bundle/Front/ directory via Composer package or fallback.
     * Returns forward-slash normalized absolute path or null.
     */
    private static function resolveFrameworkFrontDir(): ?string {
        // Try Composer package first (app-mode via vendor/)
        if (class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('phpcraftdream/garnet-framework')) {
            $pkgPath = InstalledVersions::getInstallPath('phpcraftdream/garnet-framework');

            if ($pkgPath !== null) {
                $frontDir = $pkgPath . DIRECTORY_SEPARATOR . 'Bundle' . DIRECTORY_SEPARATOR . 'Front';
                $real = realpath($frontDir);

                if ($real !== false) {
                    return str_replace('\\', '/', $real);
                }
            }
        }

        // Fallback: GARNET_ROOT/Framework/Bundle/Front/ (legacy monorepo)
        if (defined('GARNET_ROOT')) {
            $fallback = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR
                . 'Bundle' . DIRECTORY_SEPARATOR . 'Front';
            $real = realpath($fallback);

            if ($real !== false) {
                return str_replace('\\', '/', $real);
            }
        }

        return null;
    }
}
