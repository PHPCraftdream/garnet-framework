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

        // Порядок важен. Сначала зависимости: ссылка на пустой каталог
        // выглядит целой (`is_dir()` по ней истинна), поэтому проверка ссылки
        // первой навсегда закрыла бы путь к починке — сборка продолжала бы
        // падать на разрешении модулей при формально существующей ссылке.
        //
        // composer update сносит каталог пакета целиком, поэтому пропадает не
        // только ссылка, но и сами модули. Ставим их сами: prepare и есть
        // документированный шаг подготовки к сборке, и отправлять человека
        // выполнять одну команду руками — значит оставить грабли на месте.
        if (!self::hasModules($target) && !self::installFrontBuilderModules(dirname($target))) {
            return;
        }

        // Junction или симлинк отвечают на is_dir() истиной — этим и покрыты
        // оба случая: «уже связано» и «сюда кто-то поставил модули всерьёз».
        if (is_dir($link) || is_link($link)) {
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

    /**
     * Зависимости считаются установленными, только если каталог непустой.
     *
     * Проверять существование недостаточно: прерванный или упавший npm
     * оставляет пустой `node_modules`, и по `is_dir()` он неотличим от
     * готового. На такой каталог была бы наведена ссылка, а сборка упала бы
     * ровно тем же «Can't resolve», который здесь и лечится.
     */
    private static function hasModules(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        $entries = @scandir($dir);

        return $entries !== false && count(array_diff($entries, ['.', '..'])) > 0;
    }

    /**
     * `npm ci` в FrontBuilder. Долгая операция, поэтому о ней сообщаем.
     *
     * `ci`, а не `install`: lock-файл в пакете есть, и воспроизводимость
     * сборки важнее возможности подтянуть свежие версии по диапазону.
     */
    private static function installFrontBuilderModules(string $frontBuilderDir): bool {
        if (!is_file($frontBuilderDir . DIRECTORY_SEPARATOR . 'package.json')) {
            fwrite(STDERR, "prepare: {$frontBuilderDir}/package.json не найден — зависимости сборки не восстановить\n");

            return false;
        }
        fwrite(STDERR, "prepare: зависимости сборки отсутствуют, ставлю (npm ci в {$frontBuilderDir}) — это займёт минуту\n");

        // Меняем каталог процессом, а не флагом npm и не `cd` в командной
        // строке: `--prefix` у `ci` ведёт себя не так, как у `install`, а
        // синтаксис `cd` различается между cmd и sh.
        $prevDir = getcwd();
        chdir($frontBuilderDir);
        exec('npm ci --no-audit --no-fund 2>&1', $out, $code);

        if ($prevDir !== false) {
            chdir($prevDir);
        }

        if ($code !== 0) {
            fwrite(STDERR, "prepare: npm ci завершился с кодом {$code}\n" . implode("\n", array_slice($out, -5)) . "\n");

            return false;
        }
        fwrite(STDERR, "prepare: зависимости сборки установлены\n");

        return true;
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
