<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\Bundle {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\PublicPathRebrander;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Переименование публичных путей под домен выкладки.
     */
    trait BundleRebrandTrait {
        /**
         * Шаг 7: переименовать публичные пути под домен.
         *
         * Переименовываются каталоги assets/<app> и upload/<app> внутри docroot
         * и переписываются URL-литералы в *Gen.php и в собранных js/css. Если
         * сделать одно без другого — страница станет ссылаться на каталог,
         * которого нет; так уже было, и кабинет отдавал 404 на бандл.
         */
        private static function rebrandPublicPaths(array $c): void {
            ['publicName' => $publicName, 'appNameLower' => $appNameLower, 'appName' => $appName, 'distPublic' => $distPublic, 'distAppApp' => $distAppApp, 'distFw' => $distFw] = $c;

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
        }
    }
}
