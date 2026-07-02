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
