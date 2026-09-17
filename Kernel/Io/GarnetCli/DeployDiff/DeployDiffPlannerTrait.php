<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff {
    /**
     * Из списка файлов — план: что создать, что залить, что удалить.
     *
     * Раскладка по бакетам и сборка батчей. План — единственный источник правды
     * о том, куда поедет файл: превью обязано показывать ровно его, иначе
     * превью врёт (и однажды врало — путь в предпросмотре расходился с путём
     * загрузки на величину ребрендинга).
     */
    trait DeployDiffPlannerTrait {
        private static function categorize(array $diff, string $appName, array $exclude): array {
            $out = ['framework' => [], 'app' => [], 'runtime' => [], 'public' => [], 'skip' => []];

            foreach ($diff as $row) {
                $path = $row['path'];
                $status = $row['status'];

                // Excluded by user
                foreach ($exclude as $glob) {
                    if (fnmatch($glob, $path)) {
                        $out['skip'][] = ['status' => $status, 'path' => $path, 'reason' => 'excluded'];

                        continue 2;
                    }
                }

                // Kahlan specs are test code (run via `php garnet` locally, never on
                // the host). They live in `*/Spec/` dirs alongside source under both
                // Framework/ and Apps/, so skip them before the framework/app buckets
                // would otherwise ship them as runtime (and fail to mkdir the absent
                // remote `Spec/` dirs).
                if (str_contains($path, '/Spec/') || str_ends_with($path, 'Spec.php')) {
                    $out['skip'][] = ['status' => $status, 'path' => $path, 'reason' => 'test spec'];

                    continue;
                }

                // garnet (root) — bundle rewrites GARNET_ROOT, framework-dir
                // name and several putenv() lines before shipping. The raw
                // checkout would set wrong paths on the host (`Framework/`
                // instead of `garnet-framework-<ver>/`), which breaks
                // autoload immediately. _shared_index.php is generated from
                // scratch (renderSharedIndex) and is not committed; if a
                // stray copy turns up in the diff it's still not safe to
                // ship raw. Both files must go through `php garnet bundle`
                // + `ssh:put`.
                if ($path === 'garnet' || $path === '_shared_index.php') {
                    $out['skip'][] = [
                        'status' => $status,
                        'path' => $path,
                        'reason' => 'patched-by-bundle (run `php garnet bundle` then `ssh:put`)',
                    ];

                    continue;
                }

                // Use absolute-path-based categorization (works in both modes)
                $abs = self::gitPathToAbs($path);
                $resolved = self::categorizeAbsPath($abs);

                if ($resolved !== null) {
                    if ($resolved['bucket'] === 'skip') {
                        $out['skip'][] = ['status' => $status, 'path' => $path, 'reason' => 'tests dir'];

                        continue;
                    }
                    $out[$resolved['bucket']][] = [
                        'status' => $status,
                        'path' => $path,
                        'old' => $row['old'],
                        'rel_remote' => $resolved['rel_remote'],
                    ];

                    continue;
                }

                // Everything else → skip
                $out['skip'][] = ['status' => $status, 'path' => $path, 'reason' => 'out of scope'];
            }

            return $out;
        }

        // -------------------------------------------------------------------------
        // 7. Plan batches
        // -------------------------------------------------------------------------

        private static function planBatches(array $cat, array $layout, string $appName, array $opts): array {
            // Public docroot uses rebrand: local `public/<AppName>/` lands at
            // remote `<public_dir>/` (e.g. `example.com/`), and any internal
            // path that contains a bare `<appNameLower>` segment is rewritten
            // to `<public_name>` to mirror what bundle does on the dist tree.
            $targets = [
                'framework' => $layout['framework_dir'],
                'app' => $layout['app_dir'],
                'runtime' => $layout['runtime_dir'],
                'public' => $layout['public_dir'],
            ];

            $uploads = []; // [['local'=>..., 'remote'=>..., 'remote_dir'=>..., 'chmod_x'=>bool]]
            $deletes = []; // [['remote'=>...]]
            $remoteDirs = [];

            $appLow = strtolower($appName);
            // Any configured public_name rebrands, including one that differs from
            // the app name only in case: `bundle` lowercases `assets/MyApp/` to
            // `assets/myapp/`, so the host convention is the lowercased form and
            // the upload must match it. When the segment already equals
            // public_name the rewrite below simply replaces it with itself.
            $rebrandPublicSegment = $layout['public_name'] !== '';

            // Public first: hashed bundle filenames make asset uploads additive
            // and safe to land ahead of the code that will reference them — old
            // pages keep working on old files. Uploading code (framework/app/
            // runtime) before its assets exist is the "new code, no assets yet"
            // window that took prod down for 12h (see #390).
            foreach (['public', 'framework', 'app', 'runtime'] as $bucket) {
                $base = rtrim($layout['remote_path'], '/') . '/' . $targets[$bucket];

                foreach ($cat[$bucket] as $row) {
                    $rel = $row['rel_remote'];

                    // Public bucket carries the rebrand from bundle: a relative
                    // path like `assets/MyApp/...` lives at `assets/myapp/...`
                    // on the host. Mirror that here so files land in the same
                    // place bundle would have put them.
                    if ($bucket === 'public' && $rebrandPublicSegment) {
                        $rel = self::rebrandPublicRel($rel, $appName, $layout['public_name']);
                    }

                    $remote = "{$base}/{$rel}";
                    $status = $row['status'];
                    // Public-bucket rows carry the absolute path on disk
                    // (snapshotted from Apps/<App>/Public/); other buckets use the
                    // git-relative path verbatim.
                    $local = $row['local_abs'] ?? $row['path'];

                    if ($status === 'D') {
                        $deletes[] = ['remote' => $remote, 'path' => $row['path']];

                        continue;
                    }

                    if ($status === 'R') {
                        // old → delete, new → upload
                        $oldRel = self::relRemoteFor($row['old'], $appName);

                        if ($oldRel !== null) {
                            $oldBase = rtrim($layout['remote_path'], '/') . '/' . $targets[$oldRel['bucket']];
                            $deletes[] = ['remote' => "{$oldBase}/{$oldRel['rel']}", 'path' => $row['old']];
                        }
                        // new upload (status A semantics)
                        $rDir = dirname($remote);
                        $remoteDirs[$rDir] = true;
                        $uploads[] = ['local' => $local, 'remote' => $remote, 'remote_dir' => $rDir, 'chmod_x' => !empty($row['chmod_x'])];

                        continue;
                    }
                    // A / M / T
                    $rDir = dirname($remote);
                    $remoteDirs[$rDir] = true;
                    $uploads[] = ['local' => $local, 'remote' => $remote, 'remote_dir' => $rDir, 'chmod_x' => !empty($row['chmod_x'])];
                }
            }

            if ($opts['no_delete']) {
                $deletes = [];
            }

            return [
                'uploads' => $uploads,
                'deletes' => $deletes,
                'mkdirs' => array_keys($remoteDirs),
            ];
        }

        /** Тронул ли набор изменений composer.json / composer.lock. */
        public static function touchesComposerFiles(array $diff): bool {
            foreach ($diff as $row) {
                $base = basename((string)($row['path'] ?? ''));

                if ($base === 'composer.json' || $base === 'composer.lock') {
                    return true;
                }
            }

            return false;
        }

        private static function computeWarnings(array $diff): array {
            $warns = [];
            $touched = array_map(fn ($r) => $r['path'], $diff);

            foreach ($touched as $p) {
                $base = basename($p);

                if ($base === 'composer.json' || $base === 'composer.lock') {
                    // В vendor-режиме «запустите composer вручную» — плохой совет:
                    // на хосте лежит не vendor-дерево, а выложенный каталог
                    // фреймворка, и composer там не запускается вовсе. Что делать
                    // на самом деле, говорит сверка версий ниже.
                    $warns[] = self::isVendorMode()
                        ? "{$p} modified — зависимости на хосте не обновляются сами (см. сверку версии фреймворка ниже)"
                        : "{$p} modified — composer install does not run automatically (run it manually after deploying)";
                }

                if ($base === 'package.json' || $base === 'package-lock.json') {
                    $warns[] = "{$p} modified — npm install does not run automatically (run it manually after deploying)";
                }
            }

            return array_unique($warns);
        }

        /**
         * Abort loudly (never silently truncate) when scope exceeds the safety
         * cap. A partial deploy that exits 0 is worse than no deploy — #390.
         */
        private static function preflightFileLimit(array $cat, int $limit): int {
            $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public']);

            if ($total > $limit) {
                self::fail("safety limit: {$total} files in scope > limit {$limit}. Pass --limit=N to override.");
            }

            return $total;
        }
    }
}
