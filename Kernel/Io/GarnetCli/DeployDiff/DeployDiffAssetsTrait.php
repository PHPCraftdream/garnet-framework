<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff {
    use FilesystemIterator;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;

    /**
     * Собранный фронт: что пересобрать и чего на хосте нет.
     *
     * Здесь живёт сверка с хостом точечным `stat` по нужным путям вместо обхода
     * всего каталога ассетов — обход стоил полутора часов на 671 файле при 62
     * нужных. И здесь же кросс-проверка «файл есть локально, но его нет там»,
     * которая ловит случай, когда пересборка ничего не изменила, а хост
     * нужных файлов всё равно не получал.
     */
    trait DeployDiffAssetsTrait {
        // -------------------------------------------------------------------------
        // 6. Categorize files
        // -------------------------------------------------------------------------

        /**
         * Frontend source detector. Anything that ends up changing rspack
         * output: FrontBuilder/ (TS/TSX/SCSS sources, rspack config),
         * Framework/Bundle/Front/ (shared islands), Apps/<App>/Front*
         * (per-app islands + assets), and any *I18nData*.php (i18n files
         * regenerate the TS translation modules).
         */
        private static function hasFrontendSourceChanges(array $diff, string $appName): bool {
            $fwPrefix = self::frameworkGitPrefix();
            $appPrefix = self::appGitPrefix($appName);

            foreach ($diff as $row) {
                $p = $row['path'];

                // FrontBuilder/ lives inside the framework dir
                if (str_starts_with($p, $fwPrefix . 'FrontBuilder/') || str_starts_with($p, 'FrontBuilder/')) {
                    return true;
                }

                // Framework/Bundle/Front/ — shared islands
                if (str_starts_with($p, $fwPrefix . 'Bundle/Front/')) {
                    return true;
                }

                if (str_starts_with($p, $appPrefix . 'Front')) {
                    return true;
                }          // Front/, Foreground/Front, …

                if (str_starts_with($p, $appPrefix . 'Foreground/Front')) {
                    return true;
                }

                if (str_ends_with($p, 'I18nDataRu.php')) {
                    return true;
                }

                if (str_ends_with($p, 'I18nDataEn.php')) {
                    return true;
                }
            }

            return false;
        }

        /** Map relative path → "{size}:{mtime}" for every file under $root. */
        private static function snapshotAssetsDir(string $root): array {
            $out = [];

            if (!is_dir($root)) {
                return $out;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );
            $base = rtrim($root, '/\\');
            $baseLen = strlen($base) + 1;

            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $abs = $file->getPathname();
                $rel = str_replace('\\', '/', substr($abs, $baseLen));
                $out[$rel] = $file->getSize() . ':' . $file->getMTime();
            }

            return $out;
        }

        /**
         * Sizes of SPECIFIC files under a remote directory, as rel path => size.
         *
         * Deliberately asks about exactly the paths we care about instead of
         * walking the whole remote tree. The tree is not a bounded quantity: the
         * host accumulates every superseded hashed bundle ever deployed (cleanup
         * needs a retention policy, see the caller), so a full `find` there costs
         * more on every deploy and is at the mercy of the host's I/O — measured on
         * a shared host at seconds one minute and still unfinished after several
         * of them the next, for a directory of 671 files where only 62 mattered.
         * A targeted `stat` is bounded by the local asset count instead, and its
         * output is the handful of lines we actually read.
         *
         * Missing files simply produce no line (stderr discarded), which is
         * exactly the "absent on remote" signal the caller wants. Empty array on
         * any failure — SSH error, `stat` unavailable, missing directory. That's
         * the SAFE default: it makes every local file look absent, so the caller
         * re-uploads. Worst case is a harmless re-upload; the alternative
         * (assuming the host has everything) is how a stale bundle reference
         * survives a deploy. Only ever used to find what's MISSING remotely —
         * never for deletions.
         *
         * @param list<string> $relPaths rel paths as the HOST stores them
         * @return array<string, int> rel path => size
         */
        private static function remoteAssetsSizes(SshClient $ssh, string $remoteDir, array $relPaths): array {
            if ($relPaths === []) {
                return [];
            }
            $dir = escapeshellarg($remoteDir);
            $out = [];

            foreach (array_chunk($relPaths, self::REMOTE_STAT_CHUNK) as $chunk) {
                $args = implode(' ', array_map(
                    static fn (string $p): string => escapeshellarg('./' . ltrim($p, './')),
                    $chunk
                ));
                // `--` so a path can never be read as an option; 2>/dev/null drops
                // the per-file "No such file" lines; `|| true` keeps a non-zero
                // exit (every path missing) from being treated as an SSH failure.
                $cmd = "cd {$dir} 2>/dev/null && stat -c '%s %n' -- {$args} 2>/dev/null || true";
                $res = $ssh->run($cmd, ['stream' => false]);

                foreach (self::parseFindSizeOutput($res->stdout) as $path => $size) {
                    // `stat %n` echoes the argument back, i.e. with the './' we added.
                    $out[ltrim($path, './')] = $size;
                }
            }

            return $out;
        }

        /**
         * Parse `find . -type f -printf '%s %P\n'` output into rel path => size.
         * Split out from remoteAssetsListing() so the parsing itself is testable
         * without an SSH connection.
         */
        private static function parseFindSizeOutput(string $stdout): array {
            $out = [];

            foreach (explode("\n", trim($stdout)) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (!preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                    continue;
                }
                $out[$m[2]] = (int)$m[1];
            }

            return $out;
        }

        /**
         * Rows for local files that are either absent from the remote listing or
         * present with a different size. Hashed bundle filenames
         * (name.<hash>.gen.js) make "absent" equivalent to "content changed" for
         * the vast majority of what lives under Public/assets/ — a changed file
         * gets a new name, so a rebuild that produced a new hash is caught by the
         * name simply not existing remotely yet. The size check is the fallback
         * for the minority of non-hashed static files (fonts, images copied
         * as-is) whose name doesn't change when their content does.
         *
         * Local and remote keys do NOT share a namespace when the deployment
         * rebrands the app's asset folder (deploy.ini `public_name` differs from
         * the app name): locally the bundles live under `assets/<AppName>/`, on
         * the host under `assets/<public_name>/`. Comparing the raw keys makes
         * EVERY app asset look absent remotely, so every deploy re-uploads the
         * whole app asset set — minutes of SSH round-trips for files the host
         * already has, on every frontend-touching deploy. Rebrand the local key
         * before the lookup (framework assets live under `assets/framework/` on
         * both sides and are unaffected). `rel_remote` stays in the local form:
         * the destination rebrand happens later in the pipeline.
         *
         * @param array<string, string> $local  rel path => "size:mtime" (snapshotAssetsDir)
         * @param array<string, int> $remote    rel path => size (remoteAssetsListing)
         */
        private static function publicRowsMissingRemote(
            array $local,
            array $remote,
            string $assetsSubdir,
            string $appName = '',
            string $publicName = '',
        ): array {
            $rows = [];

            foreach ($local as $rel => $sig) {
                $size = (int)explode(':', $sig, 2)[0];
                $onRemote = $remote[self::rebrandAssetRel($rel, $appName, $publicName)] ?? null;

                if ($onRemote !== null && $onRemote === $size) {
                    continue; // present remotely with the same size — treat as unchanged
                }
                $relRemote = 'assets/' . $rel;
                $rows[] = [
                    'status' => $onRemote === null ? 'A' : 'M',
                    'path' => $relRemote, // display only — not a real git-diff path
                    'old' => null,
                    'rel_remote' => $relRemote,
                    'local_abs' => $assetsSubdir . DS . str_replace('/', DS, $rel),
                ];
            }

            return $rows;
        }

        /** Convert before/after snapshots into deploy:diff row shape. */
        private static function publicDeltaRows(array $before, array $after, string $assetsDir): array {
            $rows = [];
            $publicGitPrefix = self::appGitPrefix(self::getAppName()) . 'Public/';

            // Added or modified
            foreach ($after as $rel => $sig) {
                if (!isset($before[$rel]) || $before[$rel] !== $sig) {
                    $rows[] = [
                        'status' => isset($before[$rel]) ? 'M' : 'A',
                        'path' => $publicGitPrefix . $rel,
                        'old' => null,
                        'rel_remote' => $rel,
                        'local_abs' => $assetsDir . DS . str_replace('/', DS, $rel),
                    ];
                }
            }

            // Deleted
            foreach ($before as $rel => $sig) {
                if (!isset($after[$rel])) {
                    $rows[] = [
                        'status' => 'D',
                        'path' => $publicGitPrefix . $rel,
                        'old' => null,
                        'rel_remote' => $rel,
                        'local_abs' => $assetsDir . DS . str_replace('/', DS, $rel),
                    ];
                }
            }

            return $rows;
        }

        /** Invoke `php garnet build` synchronously. Throws on non-zero exit. */
        private static function runFrontendBuild(): void {
            // In vendor mode the `garnet` wrapper lives in the app dir, not GARNET_ROOT
            $garnetBin = self::isVendorMode()
                ? GarnetRunner::$appDir . DS . 'garnet'
                : GARNET_ROOT . DS . 'garnet';
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($garnetBin) . ' build';
            passthru($cmd, $code);

            if ($code !== 0) {
                self::fail("frontend build failed (exit {$code}). Fix the build then re-run deploy:diff.");
            }
        }
    }
}
