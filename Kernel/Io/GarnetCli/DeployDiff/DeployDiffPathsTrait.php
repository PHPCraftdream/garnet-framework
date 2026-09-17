<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetBundleCommand;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\PublicPathRebrander;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Где файл лежит здесь и куда он должен лечь там.
     *
     * Один слой ответственности: перевод путей. Отсюда же ребрендинг сегмента
     * `assets/<app>` → `assets/<public_name>` — тот самый, чья регистрозависимая
     * версия однажды увезла ассеты в мёртвый каталог, пока страница ссылалась на
     * живой. Поэтому одно место, где это решается, и одно, где проверяется.
     */
    trait DeployDiffPathsTrait {
        /**
         * Whether we're running in vendor (Composer-package) mode as opposed to
         * legacy monorepo mode. In vendor mode the framework lives inside
         * vendor/phpcraftdream/garnet-framework and app files live directly under
         * GarnetRunner::$appDir (no Apps/<Name>/ prefix). In legacy mode both
         * framework and apps live under one common GARNET_ROOT with Framework/
         * and Apps/<Name>/ prefixes.
         */
        private static function isVendorMode(): bool {
            if (GarnetRunner::$frameworkDir === '') {
                return false; // anchors not initialised — assume legacy
            }
            $frameworkDir = str_replace('\\', '/', GarnetRunner::$frameworkDir);
            $legacyFw = str_replace('\\', '/', GARNET_ROOT . DIRECTORY_SEPARATOR . 'Framework');

            return $frameworkDir !== $legacyFw;
        }

        /**
         * Resolve the git-repository root directory. In legacy mode this is
         * GARNET_ROOT (the monorepo root where .git lives). In vendor mode
         * this is GarnetRunner::$appDir (the app root where .git lives).
         */
        private static function gitRepoRoot(): string {
            if (self::isVendorMode()) {
                return GarnetRunner::$appDir;
            }

            return GARNET_ROOT;
        }

        /**
         * Convert a git-diff relative path to an absolute filesystem path.
         *
         * In legacy mode git paths are relative to GARNET_ROOT (e.g.
         * "Framework/Kernel/Foo.php", "Apps/MyApp/Bar.php").
         *
         * In vendor mode git paths are relative to GarnetRunner::$appDir (e.g.
         * "vendor/phpcraftdream/garnet-framework/Kernel/Foo.php",
         * "Public/index.php", "Foreground/Bar.php").
         */
        private static function gitPathToAbs(string $gitRelPath): string {
            return self::gitRepoRoot() . DS . str_replace('/', DS, $gitRelPath);
        }

        /**
         * Categorise an absolute filesystem path into a deploy bucket.
         *
         * Works in both legacy and vendor modes by comparing the absolute path
         * against the real framework and app directories on disk.
         *
         * @return ?array{bucket: string, rel_remote: string}
         */
        private static function categorizeAbsPath(string $absPath): ?array {
            $norm = str_replace('\\', '/', $absPath);
            $rawFw = GarnetRunner::$frameworkDir;
            $rawApp = GarnetRunner::$appDir;

            // Guard: if the anchors are not set, can't categorise by absolute path
            if ($rawFw === '' && $rawApp === '') {
                return null;
            }

            $fwDir = $rawFw !== '' ? rtrim(str_replace('\\', '/', $rawFw), '/') . '/' : '';
            $appDir = $rawApp !== '' ? rtrim(str_replace('\\', '/', $rawApp), '/') . '/' : '';

            // Framework files
            if ($fwDir !== '' && str_starts_with($norm, $fwDir)) {
                return ['bucket' => 'framework', 'rel_remote' => substr($norm, strlen($fwDir))];
            }

            // App sub-directories (order: most specific first)
            if ($appDir !== '' && str_starts_with($norm, $appDir)) {
                $relToApp = substr($norm, strlen($appDir));

                if (str_starts_with($relToApp, 'WorkDir/')) {
                    return ['bucket' => 'runtime', 'rel_remote' => $relToApp];
                }

                if (str_starts_with($relToApp, 'Public/')) {
                    return ['bucket' => 'public', 'rel_remote' => substr($relToApp, strlen('Public/'))];
                }

                if (str_starts_with($relToApp, 'Tests/')) {
                    return ['bucket' => 'skip', 'rel_remote' => ''];
                }

                return ['bucket' => 'app', 'rel_remote' => $relToApp];
            }

            return null;
        }

        /**
         * Compute the repo-relative prefix that framework files have in git
         * diff output. In legacy mode this is "Framework/". In vendor mode
         * this is the path from appDir to frameworkDir (e.g.
         * "vendor/phpcraftdream/garnet-framework/").
         */
        private static function frameworkGitPrefix(): string {
            if (!self::isVendorMode()) {
                return 'Framework/';
            }
            $appDir = rtrim(str_replace('\\', '/', GarnetRunner::$appDir), '/') . '/';
            $fwDir = rtrim(str_replace('\\', '/', GarnetRunner::$frameworkDir), '/') . '/';

            if (str_starts_with($fwDir, $appDir)) {
                return substr($fwDir, strlen($appDir));
            }

            // Framework outside app dir — shouldn't happen normally
            return 'vendor/phpcraftdream/garnet-framework/';
        }

        /**
         * Compute the repo-relative prefix that app files have in git diff
         * output. In legacy mode this is "Apps/<AppName>/". In vendor mode
         * app files have no prefix (they're at the repo root).
         */
        private static function appGitPrefix(string $appName): string {
            if (!self::isVendorMode()) {
                return "Apps/{$appName}/";
            }

            return '';
        }

        /**
         * Shadow directory for rebranded files. In vendor mode, keep this under
         * the app root (like GarnetBundleCommand's own dist/) rather than
         * GARNET_ROOT — in that mode GARNET_ROOT is the vendored framework
         * package dir, and even a temp build artefact shouldn't risk a
         * concurrent `composer install` sweeping it mid-write.
         */
        private static function shadowDir(): string {
            return (self::isVendorMode() ? GarnetRunner::$appDir : GARNET_ROOT) . DS . 'dist' . DS . 'deploy-diff-shadow';
        }

        /**
         * Categorise a single repo-relative path into its bucket.
         * Returns ['bucket' => string, 'rel_remote' => string] or null.
         *
         * Works in both legacy mode (paths like "Framework/...",
         * "Apps/<App>/...") and vendor mode (paths like
         * "vendor/phpcraftdream/garnet-framework/...", "Public/...",
         * "Foreground/...", "WorkDir/...").
         */
        private static function categorizeSinglePath(string $path, string $appName): ?array {
            // Primary: resolve to absolute path and use categorizeAbsPath
            // (only when GarnetRunner anchors are initialised)
            if (GarnetRunner::$frameworkDir !== '' || GarnetRunner::$appDir !== '') {
                $abs = self::gitPathToAbs($path);
                $result = self::categorizeAbsPath($abs);

                if ($result !== null) {
                    return $result;
                }
            }

            // Legacy fallback for hardcoded prefixes
            if (str_starts_with($path, 'Framework/')) {
                return ['bucket' => 'framework', 'rel_remote' => substr($path, strlen('Framework/'))];
            }

            $appPrefix = "Apps/{$appName}/";
            $wdPrefix = "{$appPrefix}WorkDir/";
            $pubPrefix = "{$appPrefix}Public/";
            $testPrefix = "{$appPrefix}Tests/";

            if (str_starts_with($path, $wdPrefix)) {
                return ['bucket' => 'runtime', 'rel_remote' => 'WorkDir/' . substr($path, strlen($wdPrefix))];
            }

            if (str_starts_with($path, $pubPrefix)) {
                return ['bucket' => 'public', 'rel_remote' => substr($path, strlen($pubPrefix))];
            }

            if (str_starts_with($path, $testPrefix)) {
                return ['bucket' => 'skip', 'rel_remote' => ''];
            }

            if (str_starts_with($path, $appPrefix)) {
                return ['bucket' => 'app', 'rel_remote' => substr($path, strlen($appPrefix))];
            }

            // WorkDir/... → runtime (shorthand without Apps/<App>/ prefix)
            if (str_starts_with($path, 'WorkDir/')) {
                return ['bucket' => 'runtime', 'rel_remote' => $path];
            }

            return null;
        }

        /**
         * Inject the 4 *Gen.php files into the categorised set.
         *
         * When $pairs and $shadowDir are given the files are rebranded into
         * the shadow directory. Otherwise they're added as-is.
         */
        private static function injectGenFiles(
            array &$cat,
            string $appName,
            int &$genCount,
            ?array $pairs = null,
            ?string $shadowDir = null,
        ): void {
            $genFiles = PublicPathRebrander::genFiles($appName);
            $repoRoot = self::gitRepoRoot();

            foreach ($genFiles as $absPath) {
                if (!is_file($absPath)) {
                    continue;
                }

                $catResult = self::categorizeAbsPath($absPath);

                if ($catResult === null || ($catResult['bucket'] !== 'app' && $catResult['bucket'] !== 'framework')) {
                    continue;
                }

                $bucket = $catResult['bucket'];
                $relRemoteDir = $catResult['rel_remote'];
                $relRepo = str_replace('\\', '/', substr($absPath, strlen($repoRoot . DS)));

                $localAbs = $absPath;

                if ($pairs !== null && $shadowDir !== null) {
                    $localAbs = self::rebrandFileToShadow($absPath, $relRepo, $pairs, $shadowDir);
                    $genCount++;
                }

                $cat[$bucket][] = [
                    'status' => 'M',
                    'path' => $relRepo,
                    'old' => null,
                    'rel_remote' => $relRemoteDir,
                    'local_abs' => $localAbs,
                ];
            }
        }

        /** Whether a file's content needs public-path rebranding. */
        private static function needsRebrand(string $path): bool {
            if (str_ends_with($path, 'Gen.php')) {
                return true;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return in_array($ext, ['js', 'css', 'map', 'html', 'svg'], true);
        }

        /**
         * Rewrite per-app index.php shim rows in $cat['public'] so they boot
         * via the runtime dir's _shared_index.php — identical to what bundle
         * does on the dist tree. Uses the shadow dir pattern (write rewritten
         * content to dist/deploy-diff-shadow/ and repoint local_abs).
         *
         * @param array $cat          Categorised file list (mutated in place)
         * @param string $appName     Active app name
         * @param string $runtimeDir  Runtime dir name from layout
         * @return int Number of rows rewritten (0 or 1)
         */
        private static function rewritePerAppIndexShim(array &$cat, string $appName, string $runtimeDir): int {
            $targetRel = 'index.php';
            $count = 0;

            foreach ($cat['public'] as $i => $row) {
                if ($row['status'] === 'D') {
                    continue;
                }

                if ($row['rel_remote'] !== $targetRel) {
                    continue;
                }

                // Verify this is the root public index.php, not a nested one.
                // In legacy mode path is "Apps/<App>/Public/index.php",
                // in vendor mode it's "Public/index.php".
                $path = $row['path'];
                $appPublicPrefix = self::appGitPrefix($appName) . 'Public/';
                $appPublicPrefixLow = self::isVendorMode()
                    ? 'Public/'
                    : 'Apps/' . strtolower($appName) . '/Public/';

                if ($path !== $appPublicPrefix . $targetRel
                    && $path !== $appPublicPrefixLow . $targetRel
                ) {
                    continue;
                }

                $shadowDir = self::shadowDir();

                if (!is_dir($shadowDir)) {
                    @mkdir($shadowDir, 0o755, true);
                }

                $shadowAbs = $shadowDir . DS . 'public' . DS . $appName . DS . 'index.php';
                $shadowSub = dirname($shadowAbs);

                if (!is_dir($shadowSub)) {
                    @mkdir($shadowSub, 0o755, true);
                }

                file_put_contents($shadowAbs, PublicPathRebrander::perAppIndexContent($runtimeDir));
                $cat['public'][$i]['local_abs'] = $shadowAbs;
                $count++;

                break; // at most one per-app index.php
            }

            return $count;
        }

        /**
         * Read a file, rewrite public paths, write into shadow dir.
         * Returns the shadow absolute path.
         */
        private static function rebrandFileToShadow(
            string $localAbs,
            string $relRepo,
            array $pairs,
            string $shadowDir,
        ): string {
            $shadowAbs = $shadowDir . DS . str_replace('/', DS, $relRepo);
            $shadowSub = dirname($shadowAbs);

            if (!is_dir($shadowSub)) {
                @mkdir($shadowSub, 0o755, true);
            }

            $orig = @file_get_contents($localAbs);

            if ($orig === false) {
                // Чаще всего это отсутствующий *Gen.php: их пишет
                // `php garnet build`, а composer при переустановке пакета
                // уносит вместе с ним. Раньше здесь падал TypeError из
                // глубины ребрендера — сообщение, по которому нельзя
                // догадаться ни о причине, ни о лекарстве, хотя лекарство
                // одно и короткое.
                $hint = str_ends_with($localAbs, 'Gen.php')
                    ? ' Это генерируемый файл: выполните `php garnet build` и повторите.'
                    : '';
                self::fail("не удалось прочитать {$localAbs} (нужен для {$relRepo})." . $hint);
            }
            $rewritten = PublicPathRebrander::rewriteContent($orig, $pairs);
            file_put_contents($shadowAbs, $rewritten);

            return $shadowAbs;
        }

        /**
         * Map a local asset-relative path (`<AppName>/gen/js/x.js`) to the form it
         * takes on the host when the deployment rebrands the app folder
         * (`<public_name>/gen/js/x.js`).
         *
         * Only the FIRST segment is touched, and only when it matches the app name
         * (case-insensitively — `bundle` lowercases it, deploy.ini may not). Paths
         * under any other root — notably `framework/`, which is never rebranded —
         * come back unchanged, as does everything when no rebrand is configured.
         */
        private static function rebrandAssetRel(string $rel, string $appName, string $publicName): string {
            if ($appName === '' || $publicName === '' || strcasecmp($appName, $publicName) === 0) {
                return $rel;
            }
            $slash = strpos($rel, '/');
            $head = $slash === false ? $rel : substr($rel, 0, $slash);

            if (strcasecmp($head, $appName) !== 0) {
                return $rel;
            }

            return $publicName . ($slash === false ? '' : substr($rel, $slash));
        }

        /** @return ?array{bucket:string,rel:string} */
        private static function relRemoteFor(?string $path, string $appName): ?array {
            if ($path === null || $path === '') {
                return null;
            }

            if ($path === 'garnet') {
                return ['bucket' => 'runtime', 'rel' => 'garnet'];
            }

            if ($path === '_shared_index.php') {
                return ['bucket' => 'runtime', 'rel' => '_shared_index.php'];
            }

            // Use absolute-path-based categorization
            $abs = self::gitPathToAbs($path);
            $result = self::categorizeAbsPath($abs);

            if ($result !== null && $result['bucket'] !== 'skip') {
                return ['bucket' => $result['bucket'], 'rel' => $result['rel_remote']];
            }

            // Legacy fallback
            if (str_starts_with($path, 'Framework/')) {
                return ['bucket' => 'framework', 'rel' => substr($path, strlen('Framework/'))];
            }
            $appPrefix = "Apps/{$appName}/";
            $wdPrefix = "{$appPrefix}WorkDir/";

            if (str_starts_with($path, $wdPrefix)) {
                return ['bucket' => 'runtime', 'rel' => 'WorkDir/' . substr($path, strlen($wdPrefix))];
            }

            if (str_starts_with($path, $appPrefix)) {
                return ['bucket' => 'app', 'rel' => substr($path, strlen($appPrefix))];
            }

            return null;
        }

        private static function remoteFor(array $row, array $layout, string $bucket, string $appName = ''): string {
            $targets = [
                'framework' => $layout['framework_dir'],
                'app' => $layout['app_dir'],
                'runtime' => $layout['runtime_dir'],
                'public' => $layout['public_dir'] ?? 'public',
            ];
            $rel = $row['rel_remote'];

            if ($bucket === 'public') {
                $rel = self::rebrandPublicRel($rel, $appName, (string)($layout['public_name'] ?? ''));
            }

            return rtrim($layout['remote_path'], '/') . '/' . $targets[$bucket] . '/' . $rel;
        }

        /**
         * Rewrite the `assets/<app>/` (or `upload/<app>/`) segment of a public
         * path to the name the host uses (`public_name`), mirroring what `bundle`
         * does on the dist tree.
         *
         * Single source of truth on purpose: the upload planner and the dry-run
         * preview both go through here. They used to compute the destination
         * independently, and the preview's copy — which skipped the rebrand
         * entirely — showed a path the deploy never wrote to. That is the one
         * surface someone inspects BEFORE passing --apply, so a preview that
         * disagrees with the deploy is worse than no preview.
         *
         * Case-insensitive on the app segment: it appears as the app is named on
         * disk (`assets/IRabi/…`) while deploy.ini's public_name is typically
         * lowercase. Empty public_name means no rebrand is configured.
         */
        private static function rebrandPublicRel(string $rel, string $appName, string $publicName): string {
            if ($publicName === '' || $appName === '') {
                return $rel;
            }

            return (string)preg_replace(
                '#(^|/)(assets|upload)/' . preg_quote($appName, '#') . '(/|$)#i',
                '$1$2/' . $publicName . '$3',
                $rel,
            );
        }

        /** Path to the active app's public asset root (Windows-safe). */
        private static function resolveAppPublicDir(string $appName): string {
            return GarnetEnv::getPublicDir($appName);
        }

        // -------------------------------------------------------------------------
        // Utilities
        // -------------------------------------------------------------------------

        private static function rmrfShadow(string $dir): void {
            if (!is_dir($dir)) {
                return;
            }
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $p = $item->getPathname();
                @chmod($p, 0o777);
                $item->isDir() ? @rmdir($p) : @unlink($p);
            }
            @chmod($dir, 0o777);
            @rmdir($dir);
        }
    }
}
