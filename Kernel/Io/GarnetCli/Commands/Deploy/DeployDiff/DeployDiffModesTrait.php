<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\DeployDiff {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\PublicPathRebrander;
    use PHPCraftdream\Garnet\Kernel\Io\Services\Ssh\SshClient;

    /**
     * Два режима, обходящие git-диф: точечная досылка и полный public.
     *
     * Первый шлёт перечисленные файлы как есть (быстрая правка, ещё не в
     * коммите), второй заново выкладывает весь собранный фронт (первый деплой,
     * восстановление после того, как на хосте вычистили /assets/). Оба не
     * двигают отметку последнего выложенного коммита: они не описывают
     * состояние «выложено до сюда».
     */
    trait DeployDiffModesTrait {
        // ───────────────────────────────────────────────────────────────────────
        // Files mode (point-deploy): ship specific working-tree files
        // ───────────────────────────────────────────────────────────────────────

        private static function doRunFilesMode(array $opts, array $layout, SshClient $ssh): void {
            $appName = self::getAppName();
            $journal = self::journal($opts);
            self::warnAboutInterruptedRuns($journal->runId());
            $journal->context([
                'mode' => 'files ' . ($opts['apply'] ? 'apply' : 'dry-run'),
                'selectors' => self::describeSelectors($opts),
                'host' => self::sshDisplay(),
                'remote_path' => (string)($layout['remote_path'] ?? ''),
            ]);

            // Warn if git selectors were also passed (they're ignored in files mode)
            $gitSelectors = array_filter([
                $opts['since'], $opts['from'], $opts['after'],
                $opts['range'], $opts['branch'],
            ], fn ($v) => $v !== '');

            if (!empty($gitSelectors) || !empty($opts['commits'])) {
                echo "  note: git selectors ignored in files mode (--file/--files)\n";
            }

            // Validate every path exists locally
            $paths = array_values(array_unique($opts['files']));
            $repoRoot = self::gitRepoRoot();
            $root = $repoRoot . DS;
            $missing = [];

            foreach ($paths as $rel) {
                $abs = $root . str_replace('/', DS, $rel);

                if (!file_exists($abs)) {
                    $missing[] = $rel;
                }
            }

            if (!empty($missing)) {
                self::fail('file(s) not found in working tree: ' . implode(', ', $missing));
            }

            // Auto-include Gen.php when any public file is in scope
            $hasPublicFile = false;

            foreach ($paths as $p) {
                $abs = $root . str_replace('/', DS, $p);
                $cat0 = self::categorizeAbsPath($abs);

                if ($cat0 !== null && $cat0['bucket'] === 'public') {
                    $hasPublicFile = true;

                    break;
                }
            }

            if ($hasPublicFile) {
                $genFiles = PublicPathRebrander::genFiles($appName);
                $added = 0;

                foreach ($genFiles as $absPath) {
                    $relRepo = str_replace('\\', '/', substr($absPath, strlen($root)));

                    if (!in_array($relRepo, $paths, true)) {
                        $paths[] = $relRepo;
                        $added++;
                    }
                }

                if ($added > 0) {
                    echo "  auto-included {$added} *Gen.php (frontend assets in scope)\n";
                }
            }

            // Categorise each path
            $cat = ['framework' => [], 'app' => [], 'runtime' => [], 'public' => [], 'skip' => []];

            foreach ($paths as $rel) {
                $abs = $root . str_replace('/', DS, $rel);
                $resolved = self::categorizeAbsPath($abs);

                if ($resolved === null) {
                    // Fallback: try categorizeSinglePath for legacy compat
                    $resolved = self::categorizeSinglePath($rel, $appName);
                }

                if ($resolved === null) {
                    self::fail("path out of scope: {$rel} — must be under the framework or app directory");
                }
                $cat[$resolved['bucket']][] = [
                    'status' => 'M',
                    'path' => $rel,
                    'old' => null,
                    'rel_remote' => $resolved['rel_remote'],
                ];
            }

            // Rebrand shadow: rewrite /assets/<appName>/ → /assets/<publicName>/
            $publicName = $layout['public_name'];
            $rebrandNeeded = strtolower($publicName) !== strtolower($appName);

            $rebrandedDocroot = 0;
            $rebrandedGen = 0;

            if ($rebrandNeeded) {
                $pairs = PublicPathRebrander::rewritePairs($appName, $publicName);
                $shadowDir = self::shadowDir();

                if (is_dir($shadowDir)) {
                    self::rmrfShadow($shadowDir);
                }
                @mkdir($shadowDir, 0o755, true);

                foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
                    foreach ($cat[$bucket] as $i => $row) {
                        $localAbs = $root . str_replace('/', DS, $row['path']);

                        if (self::needsRebrand($row['path'])) {
                            $shadowAbs = self::rebrandFileToShadow(
                                $localAbs, $row['path'], $pairs, $shadowDir,
                            );
                            $cat[$bucket][$i]['local_abs'] = $shadowAbs;

                            if (str_ends_with($row['path'], 'Gen.php')) {
                                $rebrandedGen++;
                            } else {
                                $rebrandedDocroot++;
                            }
                        }
                        // Files that don't need rebrand: planBatches() will fall
                        // back to $row['path'] when local_abs is absent, and
                        // SshClient::put resolves it relative to CWD.
                    }
                }

                $total = $rebrandedDocroot + $rebrandedGen;

                if ($total > 0) {
                    echo "  rebrand: rewrote {$rebrandedDocroot} file(s) + {$rebrandedGen} *Gen.php to /assets/{$publicName}/\n";
                }
            }

            // Attach local_abs for rows that didn't go through rebrand
            foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
                foreach ($cat[$bucket] as $i => $row) {
                    if (!isset($row['local_abs'])) {
                        $cat[$bucket][$i]['local_abs'] = $root . str_replace('/', DS, $row['path']);
                    }
                }
            }

            // Rewrite per-app index.php shim
            $shimRewritten = self::rewritePerAppIndexShim($cat, $appName, $layout['runtime_dir']);

            if ($shimRewritten > 0) {
                echo "  index.php shim rewritten -> runtime: {$layout['runtime_dir']}\n";
            }

            // Safety limit
            $total = self::preflightFileLimit($cat, $opts['limit']);

            // Plan batches (no deletes in files mode)
            $plan = self::planBatches($cat, $layout, $appName, array_merge($opts, ['no_delete' => true]));

            // Preview — files mode shows file list without commit info
            echo "=== deploy:diff preview (files mode) ===\n";
            $sshCfg = self::sshDisplay();
            echo "  host        : {$sshCfg}\n";
            echo "  remote_path : {$layout['remote_path']}\n";
            echo "  framework   : {$layout['framework_dir']}\n";
            echo "  app         : {$layout['app_dir']}\n";
            echo "  runtime     : {$layout['runtime_dir']}\n\n";

            echo "Files ({$total}):\n";

            foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
                if (empty($cat[$bucket])) {
                    continue;
                }

                foreach ($cat[$bucket] as $row) {
                    $label = str_pad($bucket, 10);
                    echo "  {$label} M  {$row['path']}\n";
                    $remote = self::remoteFor($row, $layout, $bucket, $appName);
                    echo "                  → {$remote}\n";
                }
            }
            echo "\n";
            echo 'Batches: ' . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . " uploads, 0 deletes\n";

            if (!$opts['apply']) {
                echo "\n(dry-run — pass --apply to execute)\n";
                $journal->finish(0, 'dry-run, nothing shipped');

                exit(0);
            }

            echo "\n";

            if (!self::confirm($opts['yes'])) {
                echo "Aborted.\n";
                $journal->finish(1, 'aborted at confirmation prompt');

                exit(1);
            }

            $result = self::applyBatches($ssh, $plan, $opts['verbose'], true);

            echo "\n";

            if (!empty($result['errors'])) {
                echo "Errors:\n";

                foreach ($result['errors'] as $err) {
                    echo "  - {$err}\n";
                }
                echo "\n{$result['uploaded']} uploaded, 0 deleted, " . count($result['errors']) . " failed ({$result['duration']}s)\n";
                $journal->finish(1, count($result['errors']) . ' upload(s) failed');

                exit(1);
            }
            echo "{$result['uploaded']} uploaded, 0 deleted, 0 failed ({$result['duration']}s)\n";

            // No remote deploy-sha advance in files mode — point-deploys are
            // surgical hot-fixes, not "the new last-known-deployed state".

            if ($opts['reset_opcache']) {
                self::resetRemoteOpcache($ssh, $layout);
            }

            self::syncRemoteRuntimeGarnet($ssh, $layout, $appName);
            $booted = $opts['boot_check'] ? self::remoteBootCheck($ssh, $layout) : true;
            $journal->finish($booted ? 0 : 1, sprintf(
                '%d uploaded; boot check %s',
                $result['uploaded'],
                $booted ? 'ok' : 'FAILED',
            ));

            exit($booted ? 0 : 1);
        }

        /**
         * Full-public mode: re-ship every Apps/<App>/Public/ file + 5 *Gen.php.
         *
         * No git diff, no marker check. Pre-snapshot is forced empty so every
         * file appears as 'A'. Rspack rebuild runs when --apply.
         */
        private static function doRunFullPublicMode(array $opts, array $layout, SshClient $ssh): void {
            $appName = self::getAppName();
            $journal = self::journal($opts);
            self::warnAboutInterruptedRuns($journal->runId());
            $journal->context([
                'mode' => 'full-public ' . ($opts['apply'] ? 'apply' : 'dry-run'),
                'selectors' => self::describeSelectors($opts),
                'host' => self::sshDisplay(),
                'remote_path' => (string)($layout['remote_path'] ?? ''),
            ]);

            // Warn if git selectors were also passed (they're ignored)
            $gitSelectors = array_filter([
                $opts['since'], $opts['from'], $opts['after'],
                $opts['range'], $opts['branch'],
            ], fn ($v) => $v !== '');

            if (!empty($gitSelectors) || !empty($opts['commits'])) {
                echo "  warn: --full-public ignores commit selectors\n";
            }

            $assetsDir = self::resolveAppPublicDir($appName);

            if (!$opts['apply']) {
                $count = 0;

                if (is_dir($assetsDir)) {
                    $after = self::snapshotAssetsDir($assetsDir);
                    $count = count($after);
                }
                echo "  full-public mode: pre-snapshot forced empty (rebuild on --apply)\n";
                echo "  {$count} file(s) would ship after rebuild\n";

                // Per-app index.php shim will be rewritten on apply
                if (is_file($assetsDir . DS . 'index.php')) {
                    echo "  index.php shim rewritten -> runtime: {$layout['runtime_dir']}\n";
                }
                echo "\n(dry-run — pass --apply to execute)\n";
                $journal->finish(0, 'dry-run, nothing shipped');

                exit(0);
            }

            // Apply path: run rspack, snapshot, treat pre as empty
            echo "  full-public mode: forcing rspack rebuild…\n";
            self::runFrontendBuild();

            $after = self::snapshotAssetsDir($assetsDir);
            $cat = ['framework' => [], 'app' => [], 'runtime' => [], 'public' => [], 'skip' => []];

            // Every file is an 'A' row (pre is empty)
            $publicGitPrefix = self::appGitPrefix($appName) . 'Public/';

            foreach ($after as $rel => $sig) {
                $cat['public'][] = [
                    'status' => 'A',
                    'path' => $publicGitPrefix . $rel,
                    'old' => null,
                    'rel_remote' => $rel,
                    'local_abs' => $assetsDir . DS . str_replace('/', DS, $rel),
                ];
            }
            echo '  public files: ' . count($cat['public']) . "\n";

            // Rebrand shadow — reuse the same logic as the normal pipeline
            $publicName = $layout['public_name'];
            $rebrandNeeded = strtolower($publicName) !== strtolower($appName);

            $rebrandedDocroot = 0;
            $rebrandedGen = 0;

            if ($rebrandNeeded) {
                $pairs = PublicPathRebrander::rewritePairs($appName, $publicName);
                $shadowDir = self::shadowDir();

                if (is_dir($shadowDir)) {
                    self::rmrfShadow($shadowDir);
                }
                @mkdir($shadowDir, 0o755, true);

                // Rewrite text assets
                $textExt = ['js', 'css', 'map', 'html', 'svg'];

                foreach ($cat['public'] as $i => $row) {
                    $localAbs = $row['local_abs'];
                    $ext = strtolower(pathinfo($localAbs, PATHINFO_EXTENSION));

                    if (!in_array($ext, $textExt, true)) {
                        continue;
                    }

                    $shadowAbs = self::rebrandFileToShadow($localAbs, $row['path'], $pairs, $shadowDir);
                    $cat['public'][$i]['local_abs'] = $shadowAbs;
                    $rebrandedDocroot++;
                }

                // Inject the five *Gen.php files
                self::injectGenFiles($cat, $appName, $rebrandedGen, $pairs, $shadowDir);

                echo "  rebrand: rewrote {$rebrandedDocroot} docroot file(s) + {$rebrandedGen} *Gen.php to /assets/{$publicName}/\n";
            } else {
                // Still include Gen.php even without rebrand
                self::injectGenFiles($cat, $appName, $rebrandedGen);
            }

            // Safety limit
            $total = self::preflightFileLimit($cat, $opts['limit']);

            // Rewrite per-app index.php shim
            $shimRewritten = self::rewritePerAppIndexShim($cat, $appName, $layout['runtime_dir']);

            if ($shimRewritten > 0) {
                echo "  index.php shim rewritten -> runtime: {$layout['runtime_dir']}\n";
            }

            // Plan batches — noDelete=true defensively
            $plan = self::planBatches($cat, $layout, $appName, array_merge($opts, ['no_delete' => true]));

            // Preview
            echo "\n=== deploy:diff preview (full-public mode) ===\n";
            $sshCfg = self::sshDisplay();
            echo "  host        : {$sshCfg}\n";
            echo "  remote_path : {$layout['remote_path']}\n";
            echo "  framework   : {$layout['framework_dir']}\n";
            echo "  app         : {$layout['app_dir']}\n";
            echo "  runtime     : {$layout['runtime_dir']}\n\n";

            echo "Files ({$total}):\n";

            foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
                if (empty($cat[$bucket])) {
                    continue;
                }

                foreach ($cat[$bucket] as $row) {
                    $label = str_pad($bucket, 10);
                    $stat = $row['status'];
                    $statusColor = self::statusColor($stat);
                    echo "  {$label} {$statusColor}{$stat}  {$row['path']}\n";
                    $remote = self::remoteFor($row, $layout, $bucket, $appName);
                    echo "                  → {$remote}\n";
                }
            }
            echo "\n";
            echo 'Batches: ' . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . " uploads, 0 deletes\n";

            echo "\n";

            if (!self::confirm($opts['yes'])) {
                echo "Aborted.\n";
                $journal->finish(1, 'aborted at confirmation prompt');

                exit(1);
            }

            $result = self::applyBatches($ssh, $plan, $opts['verbose'], true);

            echo "\n";

            if (!empty($result['errors'])) {
                echo "Errors:\n";

                foreach ($result['errors'] as $err) {
                    echo "  - {$err}\n";
                }
                echo "\n{$result['uploaded']} uploaded, 0 deleted, " . count($result['errors']) . " failed ({$result['duration']}s)\n";
                $journal->finish(1, count($result['errors']) . ' upload(s) failed');

                exit(1);
            }
            echo "{$result['uploaded']} uploaded, 0 deleted, 0 failed ({$result['duration']}s)\n";

            // Advance marker to HEAD — host is in a known-good state
            $headSha = trim(self::gitOut(['rev-parse', 'HEAD']));
            self::writeRemoteDeploySha($ssh, $layout, $headSha);
            $journal->note('remote deploy-sha → ' . $headSha);

            if ($opts['reset_opcache']) {
                self::resetRemoteOpcache($ssh, $layout);
            }
            $journal->finish(0, $result['uploaded'] . ' uploaded (full public re-ship)');

            exit(0);
        }
    }
}
