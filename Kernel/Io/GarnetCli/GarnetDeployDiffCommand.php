<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Exceptions\SshException;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffAssetsTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffInputTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffModesTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffPathsTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffPlannerTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffRemoteTrait;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff\DeployDiffReportTrait;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use ReflectionClass;
use Throwable;

/**
 * Push file changes from a set of git commits to the remote host via SSH.
 *
 * Operates on THREE remote sibling folders (framework / app / runtime),
 * NOT on the public docroot (frontend assets are handled by a separate
 * follow-up, see task #171).
 *
 * Connection params (host, user, identity_*, strict_host_key_checking) come
 * from WorkDir/Config*\/ssh.ini. Layout params (remote_path, framework_dir,
 * app_dir, runtime_dir) come from WorkDir/Config*\/deploy.ini. CLI flags
 * override.
 *
 * Selectors and behaviour: see `deploy:diff:help`.
 */
final class GarnetDeployDiffCommand {
    use DeployDiffPathsTrait;
    use DeployDiffInputTrait;
    use DeployDiffAssetsTrait;
    use DeployDiffPlannerTrait;
    use DeployDiffRemoteTrait;
    use DeployDiffReportTrait;
    use DeployDiffModesTrait;

    private const SAFETY_LIMIT_DEFAULT = 200;

    private const MKDIR_CHUNK = 100;

    private const SCP_CHUNK = 50;

    private const RM_CHUNK = 100;

    /** Remote marker file: last sha known to be deployed. Relative to runtime_dir. */
    private const DEPLOY_SHA_FILE = 'WorkDir/.deploy-sha';

    /** Отметка о версии фреймворка, выложенной на хост целиком. */
    private const FRAMEWORK_REF_FILE = '.framework-ref';

    /** Пакет фреймворка в composer.lock приложения (vendor-режим). */
    private const FRAMEWORK_PACKAGE = 'phpcraftdream/garnet-framework';

    public static function run(array $args): void {
        // sub-command dispatch
        $sub = $args[0] ?? '';

        if ($sub === 'help' || in_array('--help', $args, true) || in_array('-h', $args, true)) {
            self::help();

            exit(0);
        }

        try {
            $opts = self::parseArgs($args);
            self::doRun($opts);
        } catch (Throwable $e) {
            $short = (new ReflectionClass($e))->getShortName();

            // Закрыть журнал прежде, чем уйти с ошибкой. Признак прерванного
            // прогона — отсутствие финальной строки, и он должен означать
            // «прибили на полпути», а не «команда честно отказалась на
            // первом шаге». Пока этого не было, предупреждение о партиальном
            // деплое печаталось после каждого «не выбраны коммиты» — и
            // переставало читаться ровно к тому разу, когда оно правда.
            if (self::$journal !== null) {
                self::$journal->finish(1, 'error: ' . $short . ': ' . $e->getMessage());
            }

            fwrite(STDERR, "\n✖ " . $short . ': ' . $e->getMessage() . "\n\n");

            exit(1);
        }
    }

    private static ?DeployJournal $journal = null;

    /** Защёлка: предупреждение о прерванном прогоне печатается один раз. */
    private static bool $interruptedWarned = false;

    /**
     * The run journal. Created on first use so every path through the command
     * — including the early-return modes — records itself without each having
     * to remember to open one.
     */
    private static function journal(array $opts = []): DeployJournal {
        if (self::$journal === null) {
            self::$journal = new DeployJournal(
                self::journalDir(),
                'deploy:diff',
                empty($opts['no_log']),
            );
        }

        return self::$journal;
    }

    /**
     * Tell the operator that a previous run stopped halfway, at the one moment
     * the information is actionable: just before starting another one.
     *
     * A deploy killed between shipping assets and shipping the code that
     * references them leaves the host in a state that looks fine from the
     * outside — and the only prior evidence was file timestamps on the host,
     * read by hand. Warn, don't block: the usual and correct response is to
     * let this run finish the job.
     */
    private static function warnAboutInterruptedRuns(string $currentRunId): void {
        // Один прогон — одно предупреждение. Режимы вызывают этот метод
        // после doRun, и без защёлки один и тот же незакрытый прогон
        // печатался дважды подряд: повтор выглядит как два разных
        // происшествия и обесценивает само предупреждение.
        if (self::$interruptedWarned) {
            return;
        }
        self::$interruptedWarned = true;

        $unfinished = DeployJournal::findUnfinished(self::journalDir(), $currentRunId);

        if ($unfinished === []) {
            return;
        }
        $last = $unfinished[array_key_last($unfinished)];
        $landed = DeployJournal::landedCount($last);
        echo "  ! previous run {$last['id']} ({$last['command']}, started {$last['started']}) "
            . "never finished — {$landed} file(s) reported landed before it stopped.\n";
        echo '    The host may be holding a partial deploy; this run should complete it. '
            . "Details: php garnet deploy:log --run={$last['id']}\n";
    }

    /** Where run journals land: <app>/WorkDir/LogJournal/Deploy/. */
    private static function journalDir(): string {
        $appName = self::getAppName();

        return GarnetEnv::getAppDir($appName) . DS . 'WorkDir' . DS . 'LogJournal' . DS . 'Deploy';
    }

    private static function doRun(array $opts): void {
        // 1. Bootstrap app so IniConfig::ssh() / ::deploy() work
        self::bootstrapApp();

        // 2. Resolve layout (CLI flag → deploy.ini → built-in default → empty)
        $layout = self::resolveLayout($opts);

        $journal = self::journal($opts);
        self::warnAboutInterruptedRuns($journal->runId());
        $journal->context([
            'mode' => $opts['apply'] ? 'apply' : 'dry-run',
            'selectors' => self::describeSelectors($opts),
            'host' => self::sshDisplay(),
            'remote_path' => (string)($layout['remote_path'] ?? ''),
            'public_dir' => (string)($layout['public_dir'] ?? ''),
            'public_name' => (string)($layout['public_name'] ?? ''),
        ]);

        // 3. SshClient + preflight
        $ssh = SshClient::fromIniConfig();
        $ssh->validate();   // throws SshException on empty host/user

        self::preflightLayout($layout);

        // ── Full-public mode (re-ship every public asset) ───────────────
        if (!empty($opts['full_public'])) {
            self::doRunFullPublicMode($opts, $layout, $ssh);

            return;
        }

        // ── Files mode (point-deploy) ────────────────────────────────────
        if (!empty($opts['files'])) {
            self::doRunFilesMode($opts, $layout, $ssh);

            return;
        }

        // 4. Build sha list from selectors. If the user passed none, fall back
        // to the remote deploy-sha marker: deploy everything strictly after
        // the last sha we know is already on the host.
        if (self::hasNoSelectors($opts)) {
            $remoteSha = self::readRemoteDeploySha($ssh, $layout);

            if ($remoteSha !== null) {
                if (!self::commitExistsLocally($remoteSha)) {
                    self::fail("remote deploy-sha is {$remoteSha} but that commit is not in the local repo. Did you rebase/force-push? Pass --after=SHA / --range=A..B explicitly.");
                }
                $opts['after'] = $remoteSha;
                echo "  using remote deploy-sha: --after={$remoteSha} (from {$layout['runtime_dir']}/" . self::DEPLOY_SHA_FILE . ")\n";
                $autoResumed = true;
            }
        }
        $shas = self::buildShaList($opts);

        // Хост уже на HEAD — это не ошибка, а самый обычный исход: «нечего
        // выкладывать». Раньше он печатался тем же красным исключением, что
        // и «ты не указал селектор», и приучал не читать ошибки этой команды.
        if (empty($shas) && ($autoResumed ?? false)) {
            echo "\nНечего выкладывать: хост на том же коммите, что и локальный HEAD.\n";
            $journal->finish(0, 'nothing to ship — host already at HEAD');

            exit(0);
        }

        if (empty($shas)) {
            self::fail('no commits selected. Pass --since=DATE / --from=SHA / --after=SHA / --range=A..B / --commit=SHA / --branch=NAME, or seed the remote deploy-sha marker. Run `php garnet deploy:diff:help`.');
        }
        self::validateShas($shas);

        // 4b. Pre-flight gap check. With explicit selectors the shipped set can
        // skip commits between the remote marker and the selection — and a
        // skipped commit is how a deploy ends up referencing undeployed code
        // (e.g. shipping a file that `use`s a class introduced in a commit you
        // didn't include). Surface it in the preview so the operator can switch
        // to auto-resume before pushing a half-coherent set.
        if (!self::hasNoSelectors($opts)) {
            $marker = self::readRemoteDeploySha($ssh, $layout);
            $gap = self::computeUndeployedGap($shas, $marker);

            if (!empty($gap)) {
                echo "\n! gap warning: " . count($gap) . ' commit(s) between the remote marker'
                   . " ({$marker}) and your selection are NOT being shipped:\n";

                foreach ($gap as $g) {
                    echo "    {$g}\n";
                }
                echo "  If the shipped files reference code from those commits, the app will\n";
                echo "  fatal on boot. Prefer php garnet deploy:diff with NO selector\n";
                echo "  (auto-resume ships everything since the marker). The post-apply boot\n";
                echo "  check will also catch it.\n";
            }
        }

        // 5. Compound diff
        $appName = self::getAppName();
        $diff = self::computeDiff($shas);
        $cat = self::categorize($diff, $appName, $opts['exclude']);

        // 5b. Frontend delta. If any source under FrontBuilder/, Framework
        // /Bundle/Front/ or Apps/<App>/Front* changed, rspack output in
        // Apps/<App>/Public/assets/ is now stale. Snapshot → rebuild → snapshot
        // → ship only the files that actually changed (hashed asset names
        // mean unchanged sources keep the same filename and are skipped).
        // Auto-detect by default; --frontend forces, --no-frontend skips.
        $needFrontend = match ($opts['frontend']) {
            true => true,
            false => false,
            null => self::hasFrontendSourceChanges($diff, $appName),
        };
        $cat['public'] = [];

        if ($needFrontend) {
            // Dry-run shouldn't burn 5–60s on rspack. Only rebuild when the
            // user actually intends to apply; otherwise just declare that
            // a rebuild will be required at apply time.
            if (!$opts['apply']) {
                echo "  frontend source changes detected — rebuild will run on --apply.\n";
            } else {
                $journal = self::journal();
                $assetsDir = self::resolveAppPublicDir($appName);
                $before = self::snapshotAssetsDir($assetsDir);
                echo '  frontend rebuild (pre-snapshot: ' . count($before) . " files)…\n";
                $journal->phaseStart('frontend rebuild');
                self::runFrontendBuild();
                $journal->phaseEnd('frontend rebuild');
                $after = self::snapshotAssetsDir($assetsDir);
                $localDelta = self::publicDeltaRows($before, $after, $assetsDir);

                // The before/after diff only catches what THIS run's rebuild
                // changed. If the local build was already current (e.g.
                // `php garnet build` ran by hand before this command, or a
                // previous deploy:diff attempt rebuilt then failed before
                // shipping) before == after and the diff is empty — "0 files
                // to upload" — even though the remote host has never received
                // them. That gap is exactly how a deploy can ship code
                // referencing a bundle hash the host doesn't have. Cross-check
                // the full current local asset set against what the remote
                // actually has, independent of git and independent of
                // whether a rebuild just happened.
                $assetsSubdir = $assetsDir . DS . 'assets';
                $localAssets = self::snapshotAssetsDir($assetsSubdir);
                $remoteAssetsRoot = rtrim($layout['remote_path'], '/') . '/'
                    . ($layout['public_dir'] ?? 'public') . '/assets';
                // Ask the host only about the assets we actually have locally,
                // in the path form the host stores them under (rebrand applied).
                $wantedRemote = array_map(
                    static fn (string $rel): string => self::rebrandAssetRel($rel, $appName, $layout['public_name']),
                    array_keys($localAssets)
                );
                $journal->phaseStart('remote asset probe');
                $remoteAssets = self::remoteAssetsSizes($ssh, $remoteAssetsRoot, $wantedRemote);
                $journal->phaseEnd('remote asset probe', count($wantedRemote) . ' asked, ' . count($remoteAssets) . ' present');
                $missingOnRemote = self::publicRowsMissingRemote(
                    $localAssets,
                    $remoteAssets,
                    $assetsSubdir,
                    $appName,
                    $layout['public_name'],
                );

                // Merge by rel_remote: the local before/after diff wins where
                // both agree (it has the correct A/M/D status from an actual
                // git-adjacent rebuild). The remote cross-check only ever
                // contributes A/M — a file present on remote but absent
                // locally is NOT scheduled for deletion here. Cleaning up
                // superseded hashed bundles needs an age-based retention
                // policy (someone's open tab may still reference the old
                // hash), which is a separate decision, not this command's.
                $merged = [];

                foreach ($localDelta as $row) {
                    $merged[$row['rel_remote']] = $row;
                }

                foreach ($missingOnRemote as $row) {
                    if (!isset($merged[$row['rel_remote']])) {
                        $merged[$row['rel_remote']] = $row;
                    }
                }
                $cat['public'] = array_values($merged);
                echo '  frontend rebuild delta: ' . count($localDelta) . ' file(s) from this rebuild, '
                    . count($missingOnRemote) . ' file(s) missing on remote regardless -> '
                    . count($cat['public']) . " total to upload\n";
            }
        }

        // 5c. Rebrand shadow: rewrite /assets/<appName>/ → /assets/<publicName>/
        // in public assets and *Gen.php without touching local dev files.
        // Only needed when the deployed public name differs from the app name.
        $publicName = $layout['public_name'];
        $rebrandNeeded = $needFrontend
            && $opts['apply']
            && strtolower($publicName) !== strtolower($appName);

        $rebrandedDocroot = 0;
        $rebrandedGen = 0;

        if ($rebrandNeeded) {
            $pairs = PublicPathRebrander::rewritePairs($appName, $publicName);
            $shadowDir = self::shadowDir();

            // Wipe & recreate for idempotency
            if (is_dir($shadowDir)) {
                self::rmrfShadow($shadowDir);
            }
            @mkdir($shadowDir, 0o755, true);

            // a) Rewrite text-asset rows (js/css/map/html/svg) into the
            //    shadow dir and repoint local_abs. Binary files (fonts,
            //    images) are left alone — they don't contain URL literals.
            $textExt = ['js', 'css', 'map', 'html', 'svg'];

            foreach ($cat['public'] as $i => $row) {
                if ($row['status'] === 'D') {
                    continue;
                }
                $localAbs = $row['local_abs'] ?? '';

                if ($localAbs === '') {
                    continue;
                }
                $ext = strtolower(pathinfo($localAbs, PATHINFO_EXTENSION));

                if (!in_array($ext, $textExt, true)) {
                    continue;
                }

                $rel = str_replace('\\', '/', $row['rel_remote']);
                $shadowAbs = $shadowDir . DS . 'public' . DS . str_replace('/', DS, $rel);
                $shadowSub = dirname($shadowAbs);

                if (!is_dir($shadowSub)) {
                    @mkdir($shadowSub, 0o755, true);
                }

                $orig = file_get_contents($localAbs);
                $rewritten = PublicPathRebrander::rewriteContent($orig, $pairs);
                file_put_contents($shadowAbs, $rewritten);

                $cat['public'][$i]['local_abs'] = $shadowAbs;
                $rebrandedDocroot++;
            }

            // b) Inject the four *Gen.php files. They are regenerated by
            //    every rspack run so must be shipped regardless of whether
            //    git diff saw them change. Rewrite into shadow copies.
            $genFiles = PublicPathRebrander::genFiles($appName);

            foreach ($genFiles as $absPath) {
                if (!is_file($absPath)) {
                    continue;
                }

                // Categorise via absolute path
                $catResult = self::categorizeAbsPath($absPath);

                if ($catResult === null || ($catResult['bucket'] !== 'app' && $catResult['bucket'] !== 'framework')) {
                    continue;
                }
                $bucket = $catResult['bucket'];
                $relRemoteDir = $catResult['rel_remote'];

                // Build a repo-relative path for display/shadow
                $repoRoot = self::gitRepoRoot();
                $relRepo = str_replace('\\', '/', substr($absPath, strlen($repoRoot . DS)));

                // Rewrite into shadow
                $shadowAbs = $shadowDir . DS . str_replace('/', DS, $relRepo);
                $shadowSub = dirname($shadowAbs);

                if (!is_dir($shadowSub)) {
                    @mkdir($shadowSub, 0o755, true);
                }

                $orig = file_get_contents($absPath);
                $rewritten = PublicPathRebrander::rewriteContent($orig, $pairs);
                file_put_contents($shadowAbs, $rewritten);

                $cat[$bucket][] = [
                    'status' => 'M',
                    'path' => $relRepo,
                    'old' => null,
                    'rel_remote' => $relRemoteDir,
                    'local_abs' => $shadowAbs,
                ];
                $rebrandedGen++;
            }

            echo "  rebrand: rewrote {$rebrandedDocroot} docroot file(s) + {$rebrandedGen} *Gen.php to /assets/{$publicName}/\n";
        }

        // 5d. Rewrite per-app index.php shim to boot via runtime _shared_index.php.
        //     This is what bundle does on the dist tree — deploy:diff must produce
        //     the same content so the host doesn't get the dev-only shim.
        $shimRewritten = self::rewritePerAppIndexShim($cat, $appName, $layout['runtime_dir']);

        if ($shimRewritten > 0) {
            echo "  index.php shim rewritten -> runtime: {$layout['runtime_dir']}\n";
        }

        // 6. Preflight on file count
        $total = self::preflightFileLimit($cat, $opts['limit']);

        if ($opts['strict'] && !empty($cat['skip'])) {
            self::fail('--strict: ' . count($cat['skip']) . ' skipped files (use without --strict to proceed).');
        }

        // 7. Build batches
        $plan = self::planBatches($cat, $layout, $appName, $opts);
        $journal->note(sprintf(
            'planned: %d upload(s), %d delete(s), %d mkdir(s) across %d selected file(s)',
            count($plan['uploads']),
            count($plan['deletes']),
            count($plan['mkdirs']),
            $total,
        ));

        // 8. Preview
        $warns = self::computeWarnings($diff);
        $warns = array_merge($warns, self::frameworkRefWarnings(
            self::localFrameworkRef(),
            self::readRemoteFrameworkRef($ssh, $layout),
            self::touchesComposerFiles($diff),
        ));
        self::printPreview($shas, $cat, $plan, $layout, $ssh, $warns, $appName);

        // 9. Apply or stop at dry-run
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

        $journal->phaseStart('upload');
        $result = self::applyBatches($ssh, $plan, $opts['verbose'], $opts['no_delete']);
        $journal->phaseEnd('upload', sprintf(
            '%d uploaded, %d deleted, %d failed',
            $result['uploaded'],
            $result['deleted'],
            count($result['errors']),
        ));

        // 10. Final report
        echo "\n";

        if (!empty($result['errors'])) {
            echo "Errors:\n";

            foreach ($result['errors'] as $err) {
                echo "  - {$err}\n";
                $journal->note('error: ' . $err);
            }
            echo "\n{$result['uploaded']} uploaded, {$result['deleted']} deleted, " . count($result['errors']) . " failed ({$result['duration']}s)\n";
            $journal->finish(1, count($result['errors']) . ' upload(s) failed');

            exit(1);
        }
        echo "{$result['uploaded']} uploaded, {$result['deleted']} deleted, 0 failed ({$result['duration']}s)\n";

        // Advance the remote deploy-sha marker so next `deploy:diff` (without
        // selectors) knows where to resume from. Use the newest sha actually
        // shipped — for a HEAD-deploy that's HEAD; for `--commit=abc` it's abc.
        self::writeRemoteDeploySha($ssh, $layout, $shas[0]);
        $journal->note('remote deploy-sha → ' . $shas[0]);

        if ($opts['reset_opcache']) {
            self::resetRemoteOpcache($ssh, $layout);
        }

        // Boot check: confirm the host can actually start the app after the
        // push. A green upload that leaves the site 500-ing is worse than a
        // failed one — surface it now and exit non-zero so it isn't missed.
        self::syncRemoteRuntimeGarnet($ssh, $layout, $appName);
        $booted = $opts['boot_check'] ? self::remoteBootCheck($ssh, $layout) : true;

        if ($booted) {
            // Tell the FPM worker pool to drop its compiled bytecode — the CLI
            // `php garnet cache` only resets the CLI OPcache, FPM keeps its own.
            // No-op when no opcache_token is configured.
            self::tryOpcacheReset();
        }
        $journal->finish($booted ? 0 : 1, sprintf(
            '%d uploaded, %d deleted; boot check %s',
            $result['uploaded'],
            $result['deleted'],
            $booted ? 'ok' : 'FAILED',
        ));

        exit($booted ? 0 : 1);
    }

    /**
     * List files under a remote directory as relative-path => size (bytes).
     * Empty array on any failure — missing directory, SSH error, `find`
     * unavailable. That's the SAFE default here: an empty remote listing
     * makes every local file look "missing on remote", so the caller uploads
     * it. Worst case on a false-empty is a harmless re-upload of files the
     * host already has; the alternative (treating a failed listing as "remote
     * has everything") is how a stale bundle reference survives a deploy.
     * Only ever used to find what's MISSING remotely — never for deletions.
     */
    /** Paths per `stat` call — keeps the command line well under any ARG_MAX. */
    private const REMOTE_STAT_CHUNK = 400;
}
