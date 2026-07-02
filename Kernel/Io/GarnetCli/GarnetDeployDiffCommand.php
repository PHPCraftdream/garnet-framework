<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use PHPCraftdream\Garnet\Kernel\Exceptions\SshException;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
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
    private const SAFETY_LIMIT_DEFAULT = 200;

    private const MKDIR_CHUNK = 100;

    private const SCP_CHUNK = 50;

    private const RM_CHUNK = 100;

    /** Remote marker file: last sha known to be deployed. Relative to runtime_dir. */
    private const DEPLOY_SHA_FILE = 'WorkDir/.deploy-sha';

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
            fwrite(STDERR, "\n\033[31m✖ " . (new ReflectionClass($e))->getShortName() . ":\033[0m " . $e->getMessage() . "\n\n");

            exit(1);
        }
    }

    private static function doRun(array $opts): void {
        // 1. Bootstrap app so IniConfig::ssh() / ::deploy() work
        self::bootstrapApp();

        // 2. Resolve layout (CLI flag → deploy.ini → built-in default → empty)
        $layout = self::resolveLayout($opts);

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
            }
        }
        $shas = self::buildShaList($opts);

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
                echo "\n\033[33m! gap warning:\033[0m " . count($gap) . ' commit(s) between the remote marker'
                   . " ({$marker}) and your selection are NOT being shipped:\n";

                foreach ($gap as $g) {
                    echo "    \033[90m{$g}\033[0m\n";
                }
                echo "  If the shipped files reference code from those commits, the app will\n";
                echo "  fatal on boot. Prefer \033[36mphp garnet deploy:diff\033[0m with NO selector\n";
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
                $assetsDir = self::resolveAppPublicDir($appName);
                $before = self::snapshotAssetsDir($assetsDir);
                echo '  frontend rebuild (pre-snapshot: ' . count($before) . " files)…\n";
                self::runFrontendBuild();
                $after = self::snapshotAssetsDir($assetsDir);
                $cat['public'] = self::publicDeltaRows($before, $after, $assetsDir);
                echo '  frontend rebuild delta: ' . count($cat['public']) . " file(s)\n";
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
            $shadowDir = GARNET_ROOT . DS . 'dist' . DS . 'deploy-diff-shadow';

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

                // Normalise to forward slashes — on Windows DS='\\' but
                // bucket prefixes ('Apps/<App>/' / 'Framework/') use '/'.
                // Without this normalisation the str_starts_with() guard
                // below silently fails and Gen.php is never shipped.
                $relRepo = str_replace('\\', '/', substr($absPath, strlen(GARNET_ROOT . DS)));

                // Rewrite into shadow
                $shadowAbs = $shadowDir . DS . str_replace('/', DS, $relRepo);
                $shadowSub = dirname($shadowAbs);

                if (!is_dir($shadowSub)) {
                    @mkdir($shadowSub, 0o755, true);
                }

                $orig = file_get_contents($absPath);
                $rewritten = PublicPathRebrander::rewriteContent($orig, $pairs);
                file_put_contents($shadowAbs, $rewritten);

                // Categorise: app-level vs framework-level
                $appPrefix = "Apps/{$appName}/";
                $fwPrefix = 'Framework/';
                $relRemoteDir = '';
                $bucket = '';

                if (str_starts_with($relRepo, $appPrefix)) {
                    $bucket = 'app';
                    $relRemoteDir = substr($relRepo, strlen($appPrefix));
                } elseif (str_starts_with($relRepo, $fwPrefix)) {
                    $bucket = 'framework';
                    $relRemoteDir = substr($relRepo, strlen($fwPrefix));
                }

                if ($bucket !== '') {
                    $cat[$bucket][] = [
                        'status' => 'M',
                        'path' => $relRepo,
                        'old' => null,
                        'rel_remote' => $relRemoteDir,
                        'local_abs' => $shadowAbs,
                    ];
                    $rebrandedGen++;
                }
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
        $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public']);
        $limit = $opts['limit'];

        if ($total > $limit) {
            self::fail("safety limit: {$total} files in scope > limit {$limit}. Pass --limit=N to override.");
        }

        if ($opts['strict'] && !empty($cat['skip'])) {
            self::fail('--strict: ' . count($cat['skip']) . ' skipped files (use without --strict to proceed).');
        }

        // 7. Build batches
        $plan = self::planBatches($cat, $layout, $appName, $opts);

        // 8. Preview
        $warns = self::computeWarnings($diff);
        self::printPreview($shas, $cat, $plan, $layout, $ssh, $warns);

        // 9. Apply or stop at dry-run
        if (!$opts['apply']) {
            echo "\n\033[90m(dry-run — pass --apply to execute)\033[0m\n";

            exit(0);
        }

        echo "\n";

        if (!self::confirm($opts['yes'])) {
            echo "Aborted.\n";

            exit(1);
        }

        $result = self::applyBatches($ssh, $plan, $opts['verbose'], $opts['no_delete']);

        // 10. Final report
        echo "\n";

        if (!empty($result['errors'])) {
            echo "\033[31mErrors:\033[0m\n";

            foreach ($result['errors'] as $err) {
                echo "  - {$err}\n";
            }
            echo "\n\033[31m{$result['uploaded']} uploaded, {$result['deleted']} deleted, " . count($result['errors']) . " failed\033[0m ({$result['duration']}s)\n";

            exit(1);
        }
        echo "\033[32m{$result['uploaded']} uploaded, {$result['deleted']} deleted, 0 failed\033[0m ({$result['duration']}s)\n";

        // Advance the remote deploy-sha marker so next `deploy:diff` (without
        // selectors) knows where to resume from. Use the newest sha actually
        // shipped — for a HEAD-deploy that's HEAD; for `--commit=abc` it's abc.
        self::writeRemoteDeploySha($ssh, $layout, $shas[0]);

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

        exit($booted ? 0 : 1);
    }

    /**
     * POST /sys/opcache-reset/~run on the live site with the shared secret
     * from `opcache_token` in app.ini. The endpoint refuses anything without
     * that header — leaking the token here would be the only risk, so it's
     * only ever read from the local app.ini (kept out of version control).
     *
     * Best-effort: any failure is logged but does NOT fail the deploy. The
     * site already serves the new code; the FPM workers will pick it up on
     * their next idle recycle even without the explicit reset.
     */
    private static function tryOpcacheReset(): void {
        try {
            $appCfg = IniConfig::app();
            $token = trim($appCfg->paramString('opcache_token', ''));

            if ($token === '') {
                echo "  \033[33m·\033[0m opcache reset: skipped (no `opcache_token` in app.ini)\n";

                return;
            }
            $baseUrl = rtrim($appCfg->paramString('base_url', ''), '/');

            if ($baseUrl === '') {
                echo "  \033[33m·\033[0m opcache reset: skipped (no `base_url` in app.ini)\n";

                return;
            }

            $url = $baseUrl . '/sys/opcache-reset/~run';
            $ch = curl_init($url);

            if ($ch === false) {
                echo "  \033[33m·\033[0m opcache reset: skipped (curl_init failed)\n";

                return;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['X-Garnet-Opcache-Token: ' . $token],
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = (string)curl_error($ch);
            curl_close($ch);

            if ($status >= 200 && $status < 300) {
                echo "  \033[32m[OK]\033[0m opcache reset → {$status}\n";
            } else {
                $tail = $err !== '' ? " ({$err})" : ($body !== false ? ' ' . mb_substr((string)$body, 0, 200) : '');
                echo "  \033[33m·\033[0m opcache reset failed → {$status}{$tail}\n";
            }
        } catch (Throwable $e) {
            echo "  \033[33m·\033[0m opcache reset error: " . $e->getMessage() . "\n";
        }
    }

    // ───────────────────────────────────────────────────────────────────────
    // Files mode (point-deploy): ship specific working-tree files
    // ───────────────────────────────────────────────────────────────────────

    private static function doRunFilesMode(array $opts, array $layout, SshClient $ssh): void {
        $appName = self::getAppName();

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
        $root = GARNET_ROOT . DS;
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

        // Auto-include Gen.php when any Apps/<App>/Public/ file is in scope
        $publicPrefix = "Apps/{$appName}/Public/";
        $publicPrefixLow = 'Apps/' . strtolower($appName) . '/Public/';
        $hasPublicFile = false;

        foreach ($paths as $p) {
            if (str_starts_with($p, $publicPrefix) || str_starts_with($p, $publicPrefixLow)) {
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
            $resolved = self::categorizeSinglePath($rel, $appName);

            if ($resolved === null) {
                self::fail("path out of scope: {$rel} — must be under Framework/, Apps/{$appName}/, or Apps/{$appName}/Public/");
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
            $shadowDir = GARNET_ROOT . DS . 'dist' . DS . 'deploy-diff-shadow';

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
                    // SshClient::put resolves it relative to CWD = GARNET_ROOT.
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
        $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public']);
        $limit = $opts['limit'];

        if ($total > $limit) {
            self::fail("safety limit: {$total} files in scope > limit {$limit}. Pass --limit=N to override.");
        }

        // Plan batches (no deletes in files mode)
        $plan = self::planBatches($cat, $layout, $appName, array_merge($opts, ['no_delete' => true]));

        // Preview — files mode shows file list without commit info
        echo "\033[1m=== deploy:diff preview (files mode) ===\033[0m\n";
        $sshCfg = self::sshDisplay();
        echo "  host        : {$sshCfg}\n";
        echo "  remote_path : {$layout['remote_path']}\n";
        echo "  framework   : {$layout['framework_dir']}\n";
        echo "  app         : {$layout['app_dir']}\n";
        echo "  runtime     : {$layout['runtime_dir']}\n\n";

        echo "\033[1mFiles (\033[0m{$total}\033[1m):\033[0m\n";

        foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
            if (empty($cat[$bucket])) {
                continue;
            }

            foreach ($cat[$bucket] as $row) {
                $label = str_pad($bucket, 10);
                echo "  {$label} \033[33mM\033[0m  {$row['path']}\n";
                $remote = self::remoteFor($row, $layout, $bucket);
                echo "                  → {$remote}\n";
            }
        }
        echo "\n";
        echo "\033[1mBatches:\033[0m " . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . " uploads, 0 deletes\n";

        if (!$opts['apply']) {
            echo "\n\033[90m(dry-run — pass --apply to execute)\033[0m\n";

            exit(0);
        }

        echo "\n";

        if (!self::confirm($opts['yes'])) {
            echo "Aborted.\n";

            exit(1);
        }

        $result = self::applyBatches($ssh, $plan, $opts['verbose'], true);

        echo "\n";

        if (!empty($result['errors'])) {
            echo "\033[31mErrors:\033[0m\n";

            foreach ($result['errors'] as $err) {
                echo "  - {$err}\n";
            }
            echo "\n\033[31m{$result['uploaded']} uploaded, 0 deleted, " . count($result['errors']) . " failed\033[0m ({$result['duration']}s)\n";

            exit(1);
        }
        echo "\033[32m{$result['uploaded']} uploaded, 0 deleted, 0 failed\033[0m ({$result['duration']}s)\n";

        // No remote deploy-sha advance in files mode — point-deploys are
        // surgical hot-fixes, not "the new last-known-deployed state".

        if ($opts['reset_opcache']) {
            self::resetRemoteOpcache($ssh, $layout);
        }

        self::syncRemoteRuntimeGarnet($ssh, $layout, $appName);
        $booted = $opts['boot_check'] ? self::remoteBootCheck($ssh, $layout) : true;

        exit($booted ? 0 : 1);
    }

    /**
     * Categorise a single repo-relative path into its bucket.
     * Returns ['bucket' => string, 'rel_remote' => string] or null.
     */
    private static function categorizeSinglePath(string $path, string $appName): ?array {
        if (str_starts_with($path, 'Framework/')) {
            return ['bucket' => 'framework', 'rel_remote' => substr($path, strlen('Framework/'))];
        }

        $appPrefix = "Apps/{$appName}/";
        $wdPrefix = "{$appPrefix}WorkDir/";
        $pubPrefix = "{$appPrefix}Public/";
        $testPrefix = "{$appPrefix}Tests/";

        // Order: most-specific first → runtime → public → tests (skip) → fallback app
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
     * Full-public mode: re-ship every Apps/<App>/Public/ file + 4 *Gen.php.
     *
     * No git diff, no marker check. Pre-snapshot is forced empty so every
     * file appears as 'A'. Rspack rebuild runs when --apply.
     */
    private static function doRunFullPublicMode(array $opts, array $layout, SshClient $ssh): void {
        $appName = self::getAppName();

        // Warn if git selectors were also passed (they're ignored)
        $gitSelectors = array_filter([
            $opts['since'], $opts['from'], $opts['after'],
            $opts['range'], $opts['branch'],
        ], fn ($v) => $v !== '');

        if (!empty($gitSelectors) || !empty($opts['commits'])) {
            echo "  \033[33mwarn:\033[0m --full-public ignores commit selectors\n";
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
            echo "\n\033[90m(dry-run — pass --apply to execute)\033[0m\n";

            exit(0);
        }

        // Apply path: run rspack, snapshot, treat pre as empty
        echo "  full-public mode: forcing rspack rebuild…\n";
        self::runFrontendBuild();

        $after = self::snapshotAssetsDir($assetsDir);
        $cat = ['framework' => [], 'app' => [], 'runtime' => [], 'public' => [], 'skip' => []];

        // Every file is an 'A' row (pre is empty)
        foreach ($after as $rel => $sig) {
            $cat['public'][] = [
                'status' => 'A',
                'path' => 'Apps/' . self::getAppName() . '/Public/' . $rel,
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
            $shadowDir = GARNET_ROOT . DS . 'dist' . DS . 'deploy-diff-shadow';

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

            // Inject the four *Gen.php files
            $genFiles = PublicPathRebrander::genFiles($appName);

            foreach ($genFiles as $absPath) {
                if (!is_file($absPath)) {
                    continue;
                }

                $relRepo = str_replace('\\', '/', substr($absPath, strlen(GARNET_ROOT . DS)));

                $shadowAbs = self::rebrandFileToShadow($absPath, $relRepo, $pairs, $shadowDir);

                $appPrefix = "Apps/{$appName}/";
                $fwPrefix = 'Framework/';
                $relRemoteDir = '';
                $bucket = '';

                if (str_starts_with($relRepo, $appPrefix)) {
                    $bucket = 'app';
                    $relRemoteDir = substr($relRepo, strlen($appPrefix));
                } elseif (str_starts_with($relRepo, $fwPrefix)) {
                    $bucket = 'framework';
                    $relRemoteDir = substr($relRepo, strlen($fwPrefix));
                }

                if ($bucket !== '') {
                    $cat[$bucket][] = [
                        'status' => 'M',
                        'path' => $relRepo,
                        'old' => null,
                        'rel_remote' => $relRemoteDir,
                        'local_abs' => $shadowAbs,
                    ];
                    $rebrandedGen++;
                }
            }

            echo "  rebrand: rewrote {$rebrandedDocroot} docroot file(s) + {$rebrandedGen} *Gen.php to /assets/{$publicName}/\n";
        } else {
            // Still include Gen.php even without rebrand
            $genFiles = PublicPathRebrander::genFiles($appName);

            foreach ($genFiles as $absPath) {
                if (!is_file($absPath)) {
                    continue;
                }

                $relRepo = str_replace('\\', '/', substr($absPath, strlen(GARNET_ROOT . DS)));

                $appPrefix = "Apps/{$appName}/";
                $fwPrefix = 'Framework/';
                $bucket = '';
                $relRemoteDir = '';

                if (str_starts_with($relRepo, $appPrefix)) {
                    $bucket = 'app';
                    $relRemoteDir = substr($relRepo, strlen($appPrefix));
                } elseif (str_starts_with($relRepo, $fwPrefix)) {
                    $bucket = 'framework';
                    $relRemoteDir = substr($relRepo, strlen($fwPrefix));
                }

                if ($bucket !== '') {
                    $cat[$bucket][] = [
                        'status' => 'M',
                        'path' => $relRepo,
                        'old' => null,
                        'rel_remote' => $relRemoteDir,
                        'local_abs' => $absPath,
                    ];
                }
            }
        }

        // Safety limit
        $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public']);
        $limit = $opts['limit'];

        if ($total > $limit) {
            self::fail("safety limit: {$total} files in scope > limit {$limit}. Pass --limit=N to override.");
        }

        // Rewrite per-app index.php shim
        $shimRewritten = self::rewritePerAppIndexShim($cat, $appName, $layout['runtime_dir']);

        if ($shimRewritten > 0) {
            echo "  index.php shim rewritten -> runtime: {$layout['runtime_dir']}\n";
        }

        // Plan batches — noDelete=true defensively
        $plan = self::planBatches($cat, $layout, $appName, array_merge($opts, ['no_delete' => true]));

        // Preview
        echo "\n\033[1m=== deploy:diff preview (full-public mode) ===\033[0m\n";
        $sshCfg = self::sshDisplay();
        echo "  host        : {$sshCfg}\n";
        echo "  remote_path : {$layout['remote_path']}\n";
        echo "  framework   : {$layout['framework_dir']}\n";
        echo "  app         : {$layout['app_dir']}\n";
        echo "  runtime     : {$layout['runtime_dir']}\n\n";

        echo "\033[1mFiles (\033[0m{$total}\033[1m):\033[0m\n";

        foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
            if (empty($cat[$bucket])) {
                continue;
            }

            foreach ($cat[$bucket] as $row) {
                $label = str_pad($bucket, 10);
                $stat = $row['status'];
                $statusColor = self::statusColor($stat);
                echo "  {$label} {$statusColor}{$stat}\033[0m  {$row['path']}\n";
                $remote = self::remoteFor($row, $layout, $bucket);
                echo "                  → {$remote}\n";
            }
        }
        echo "\n";
        echo "\033[1mBatches:\033[0m " . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . " uploads, 0 deletes\n";

        echo "\n";

        if (!self::confirm($opts['yes'])) {
            echo "Aborted.\n";

            exit(1);
        }

        $result = self::applyBatches($ssh, $plan, $opts['verbose'], true);

        echo "\n";

        if (!empty($result['errors'])) {
            echo "\033[31mErrors:\033[0m\n";

            foreach ($result['errors'] as $err) {
                echo "  - {$err}\n";
            }
            echo "\n\033[31m{$result['uploaded']} uploaded, 0 deleted, " . count($result['errors']) . " failed\033[0m ({$result['duration']}s)\n";

            exit(1);
        }
        echo "\033[32m{$result['uploaded']} uploaded, 0 deleted, 0 failed\033[0m ({$result['duration']}s)\n";

        // Advance marker to HEAD — host is in a known-good state
        $headSha = trim(self::gitOut(['rev-parse', 'HEAD']));
        self::writeRemoteDeploySha($ssh, $layout, $headSha);

        if ($opts['reset_opcache']) {
            self::resetRemoteOpcache($ssh, $layout);
        }

        exit(0);
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
        $appPublicPrefix = "Apps/{$appName}/Public/";
        $appPublicPrefixLow = 'Apps/' . strtolower($appName) . '/Public/';
        $count = 0;

        foreach ($cat['public'] as $i => $row) {
            if ($row['status'] === 'D') {
                continue;
            }

            if ($row['rel_remote'] !== $targetRel) {
                continue;
            }

            // Verify this is Apps/<App>/Public/index.php, not a nested one
            $path = $row['path'];

            if ($path !== $appPublicPrefix . $targetRel
                && $path !== $appPublicPrefixLow . $targetRel
            ) {
                continue;
            }

            $shadowDir = GARNET_ROOT . DS . 'dist' . DS . 'deploy-diff-shadow';

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

        $orig = file_get_contents($localAbs);
        $rewritten = PublicPathRebrander::rewriteContent($orig, $pairs);
        file_put_contents($shadowAbs, $rewritten);

        return $shadowAbs;
    }

    private static function hasNoSelectors(array $opts): bool {
        return $opts['since'] === ''
            && $opts['from'] === ''
            && $opts['after'] === ''
            && $opts['range'] === ''
            && $opts['branch'] === ''
            && empty($opts['commits']);
    }

    /** Reads `runtime_dir/WorkDir/.deploy-sha` over SSH. Returns trimmed sha or null. */
    private static function readRemoteDeploySha(SshClient $ssh, array $layout): ?string {
        $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'] . '/' . self::DEPLOY_SHA_FILE;
        $cmd = 'cat ' . escapeshellarg($remoteFile) . ' 2>/dev/null || true';
        $res = $ssh->run($cmd);
        $sha = trim($res->stdout);

        if ($sha === '' || !preg_match('/^[0-9a-f]{7,40}$/i', $sha)) {
            return null;
        }

        return $sha;
    }

    /** Writes the sha to `runtime_dir/WorkDir/.deploy-sha` over SSH. Non-fatal on failure. */
    private static function writeRemoteDeploySha(SshClient $ssh, array $layout, string $sha): void {
        $sha = trim($sha);

        if ($sha === '') {
            return;
        }
        $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'] . '/' . self::DEPLOY_SHA_FILE;
        $remoteDir = dirname($remoteFile);
        // mkdir -p is harmless if the dir already exists; WorkDir always should.
        $cmd = 'mkdir -p ' . escapeshellarg($remoteDir)
             . " && printf '%s\\n' " . escapeshellarg($sha)
             . ' > ' . escapeshellarg($remoteFile);
        $res = $ssh->run($cmd);
        $err = trim($res->stderr);

        if ($err !== '') {
            echo "\n\033[33mwarn:\033[0m could not write remote deploy-sha: {$err}\n";

            return;
        }
        echo "  remote deploy-sha → {$sha}\n";
    }

    private static function commitExistsLocally(string $sha): bool {
        [$rc] = self::gitTry(['rev-parse', '--verify', "{$sha}^{commit}"]);

        return $rc === 0;
    }

    /**
     * Post-apply safety net: boot the app on the host (`php garnet noop`) and
     * confirm it doesn't fatal. Catches the most damaging failure mode — a
     * shipped file set that leaves the app unbootable (e.g. a file referencing
     * a class from an earlier commit that was never deployed, which is exactly
     * what a cherry-picked `--commit=` can do). Returns true when clean.
     */
    private static function remoteBootCheck(SshClient $ssh, array $layout): bool {
        $target = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'];
        $cmd = 'cd ' . escapeshellarg($target) . ' && php garnet noop 2>&1';

        echo "\n\033[1mboot check\033[0m  (php garnet noop on host)\n";
        $res = $ssh->run($cmd);

        if ($res->exitCode === 0) {
            echo "  \033[32m[OK]\033[0m app boots cleanly on the host\n";

            return true;
        }

        $out = trim($res->stdout . "\n" . $res->stderr);
        echo "  \033[31m[FAIL] the app does NOT boot after this deploy — the site is\n";
        echo "         very likely returning HTTP 500.\033[0m\n";

        foreach (array_filter(explode("\n", $out)) as $line) {
            echo "    \033[90m{$line}\033[0m\n";
        }
        echo "  \033[33mLikely cause:\033[0m a shipped file references code from an earlier\n";
        echo "  commit that was never deployed. Re-run with NO selector to ship every\n";
        echo "  commit since the remote marker:  \033[36mphp garnet deploy:diff --apply\033[0m\n";
        echo "  …or roll back by re-deploying the previous commit's files.\n";

        return false;
    }

    /**
     * Keep the remote runtime `garnet` dispatcher in sync with the repo's
     * `./garnet` on every apply. The runtime dispatcher is a path-rewritten
     * copy that deploy:diff otherwise never touches, so newly-added
     * garnet-level commands (cache, snapshot, maintenance:remote, …) used to
     * silently 404 on the box ("command not found") until hand-patched.
     *
     * Uploads to `garnet.new`, validates it actually boots (`php garnet.new
     * noop`), and only then atomically swaps it in — a broken regenerate can
     * never replace a working dispatcher. Non-fatal: a failure leaves the
     * current dispatcher untouched.
     */
    private static function syncRemoteRuntimeGarnet(SshClient $ssh, array $layout, string $appName): void {
        $contents = GarnetBundleCommand::renderRuntimeGarnet(
            GARNET_ROOT . DIRECTORY_SEPARATOR . 'garnet',
            (string)$layout['app_dir'],
            $appName,
            (string)$layout['framework_dir'],
        );

        if ($contents === null) {
            return; // no repo ./garnet to mirror
        }

        $tmp = tempnam(sys_get_temp_dir(), 'garnet_rt_');
        file_put_contents($tmp, $contents);

        $rd = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'];
        $remoteNew = $rd . '/garnet.new';

        echo "\n\033[1mruntime dispatcher\033[0m  (sync ./garnet routes to the host)\n";
        $put = $ssh->put($tmp, $remoteNew, ['stream' => false]);
        @unlink($tmp);

        if (!$put->ok()) {
            echo "  \033[33m·\033[0m upload failed (exit {$put->exitCode}) — current dispatcher kept\n";

            return;
        }

        $g = escapeshellarg($remoteNew);
        $gFinal = escapeshellarg($rd . '/garnet');
        $cmd = "php {$g} noop >/dev/null 2>&1 && chmod 755 {$g} && mv {$g} {$gFinal} && echo SYNC_OK || { rm -f {$g}; echo SYNC_FAIL; }";
        $res = $ssh->run($cmd, ['stream' => false]);

        if (str_contains($res->stdout, 'SYNC_OK')) {
            echo "  \033[32m[OK]\033[0m runtime garnet dispatcher updated (routes current)\n";
        } else {
            echo "  \033[33m·\033[0m new dispatcher failed its boot check — kept the existing one\n";
        }
    }

    /**
     * When explicit commit selectors are used, detect commits that sit between
     * the remote deploy-sha marker and the shipped set but are NOT being
     * shipped. A skipped commit is how a deploy ends up referencing undeployed
     * code. Returns the skipped sha (short form); empty when there's no gap, no
     * marker, or on any git error.
     *
     * @param list<string> $shas   shipped commits, newest first
     * @return list<string>
     */
    private static function computeUndeployedGap(array $shas, ?string $marker): array {
        if ($marker === null || empty($shas)) {
            return [];
        }
        $newest = $shas[0];
        [$rc, $out] = self::gitTry(['rev-list', "{$marker}..{$newest}"]);

        if ($rc !== 0) {
            return [];
        }
        $range = array_values(array_filter(array_map('trim', explode("\n", $out))));

        // Normalise shipped sha to full form for set membership.
        $shipped = [];

        foreach ($shas as $s) {
            [$r, $full] = self::gitTry(['rev-parse', $s]);

            if ($r === 0) {
                $shipped[trim($full)] = true;
            }
        }

        $gap = [];

        foreach ($range as $full) {
            if (!isset($shipped[$full])) {
                $gap[] = substr($full, 0, 8);
            }
        }

        return $gap;
    }

    /**
     * Best-effort opcache invalidation over SSH. Runs `php -r 'opcache_reset();'`
     * on the host — only effective if the FPM pool shares opcache with CLI
     * (uncommon on shared hosting, where opcache.enable_cli=0 is the default).
     * Prints the host's stdout so the operator sees what actually happened.
     */
    private static function resetRemoteOpcache(SshClient $ssh, array $layout): void {
        $remoteRoot = rtrim($layout['remote_path'], '/');
        $runtime = $layout['runtime_dir'];
        // cd into runtime so the host has access to `.env` if any helper
        // tools want it later; then run a one-liner that returns "ok",
        // "noop" or a PHP error string we can read back.
        $php = "if (function_exists('opcache_reset')) { var_export(opcache_reset()); } else { echo 'noop'; }";
        $cmd = 'cd ' . escapeshellarg($remoteRoot . '/' . $runtime) . ' && php -r ' . escapeshellarg($php);

        echo "\n\033[1mopcache reset (best-effort)\033[0m\n";
        $res = $ssh->run($cmd);
        $out = trim($res->stdout . $res->stderr);

        if ($out === '') {
            echo "  (no output)\n";
        } else {
            echo "  host: {$out}\n";
        }

        if ($out === 'noop') {
            echo "  \033[33mhint:\033[0m CLI opcache disabled on host — restart php-fpm or hit any web URL\n";
            echo "        to invalidate by mtime (opcache.validate_timestamps=1 is the default).\n";
        }
    }

    // -------------------------------------------------------------------------
    // 1. Bootstrap (load IniConfig)
    // -------------------------------------------------------------------------

    private static function bootstrapApp(): void {
        $appName = GarnetEnv::readAppName();

        if ($appName === '') {
            self::fail('active app not set. Run `php garnet app:use <Name>` first.');
        }
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            self::fail("app has no run_cmd.php at {$runCmd}");
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();
    }

    private static function getAppName(): string {
        return GarnetEnv::readAppName();
    }

    // -------------------------------------------------------------------------
    // 2. Arg parsing
    // -------------------------------------------------------------------------

    private static function parseArgs(array $args): array {
        $opts = [
            'since' => '',
            'from' => '',
            'after' => '',
            'range' => '',
            'commits' => [],
            'branch' => '',
            'apply' => false,
            'yes' => false,
            'no_delete' => false,
            'exclude' => [],
            'limit' => self::SAFETY_LIMIT_DEFAULT,
            'verbose' => false,
            'strict' => false,
            'public_dir' => '',
            'public_name' => '',
            'framework_dir' => '',
            'app_dir' => '',
            'runtime_dir' => '',
            'frontend' => null, // null = auto, false = skip, true = force
            'reset_opcache' => false,
            'boot_check' => true,  // post-apply `php garnet noop` smoke; --no-boot-check disables
            'files' => [],   // --file=PATH (repeatable) / --files=A,B,C
            'full_public' => false,
        ];

        $hasDryRun = false;

        foreach ($args as $arg) {
            if ($arg === '--apply') {
                $opts['apply'] = true;

                continue;
            }

            if ($arg === '--dry-run') {
                $hasDryRun = true;

                continue;
            }

            if ($arg === '--yes' || $arg === '-y') {
                $opts['yes'] = true;

                continue;
            }

            if ($arg === '--no-delete') {
                $opts['no_delete'] = true;

                continue;
            }

            if ($arg === '--verbose' || $arg === '-v') {
                $opts['verbose'] = true;

                continue;
            }

            if ($arg === '--strict') {
                $opts['strict'] = true;

                continue;
            }

            if ($arg === '--frontend') {
                $opts['frontend'] = true;

                continue;
            }

            if ($arg === '--no-frontend') {
                $opts['frontend'] = false;

                continue;
            }

            if ($arg === '--reset-opcache') {
                $opts['reset_opcache'] = true;

                continue;
            }

            if ($arg === '--no-boot-check') {
                $opts['boot_check'] = false;

                continue;
            }

            if (str_starts_with($arg, '--since=')) {
                $opts['since'] = substr($arg, 8);

                continue;
            }

            if (str_starts_with($arg, '--from=')) {
                $opts['from'] = substr($arg, 7);

                continue;
            }

            if (str_starts_with($arg, '--after=')) {
                $opts['after'] = substr($arg, 8);

                continue;
            }

            if (str_starts_with($arg, '--range=')) {
                $opts['range'] = substr($arg, 8);

                continue;
            }

            if (str_starts_with($arg, '--commit=')) {
                $opts['commits'][] = substr($arg, 9);

                continue;
            }

            if (str_starts_with($arg, '--branch=')) {
                $opts['branch'] = substr($arg, 9);

                continue;
            }

            if (str_starts_with($arg, '--exclude=')) {
                $opts['exclude'][] = substr($arg, 10);

                continue;
            }

            if (str_starts_with($arg, '--limit=')) {
                $opts['limit'] = max(1, (int)substr($arg, 8));

                continue;
            }

            if (str_starts_with($arg, '--public-dir=')) {
                $opts['public_dir'] = substr($arg, 13);

                continue;
            }

            if (str_starts_with($arg, '--public-name=')) {
                $opts['public_name'] = substr($arg, 14);

                continue;
            }

            if (str_starts_with($arg, '--framework-dir=')) {
                $opts['framework_dir'] = substr($arg, 16);

                continue;
            }

            if (str_starts_with($arg, '--app-dir=')) {
                $opts['app_dir'] = substr($arg, 10);

                continue;
            }

            if (str_starts_with($arg, '--runtime-dir=')) {
                $opts['runtime_dir'] = substr($arg, 14);

                continue;
            }

            if (str_starts_with($arg, '--file=')) {
                $opts['files'][] = substr($arg, 7);

                continue;
            }

            if (str_starts_with($arg, '--files=')) {
                foreach (explode(',', substr($arg, 8)) as $f) {
                    $f = trim($f);

                    if ($f !== '') {
                        $opts['files'][] = $f;
                    }
                }

                continue;
            }

            if ($arg === '--full-public') {
                $opts['full_public'] = true;

                continue;
            }

            self::fail("unknown argument: {$arg}");
        }

        if ($opts['apply'] && $hasDryRun) {
            self::fail('--apply and --dry-run cannot be combined.');
        }

        return $opts;
    }

    // -------------------------------------------------------------------------
    // 3. Layout resolution (CLI → deploy.ini → defaults)
    // -------------------------------------------------------------------------

    private static function resolveLayout(array $opts): array {
        $appName = self::getAppName();
        $appLow = strtolower($appName);

        $defaults = [
            'remote_path' => '',
            'public_dir' => 'public',
            'public_name' => $appLow,
            'framework_dir' => 'garnet-framework',
            'app_dir' => "garnet-app-{$appLow}",
            'runtime_dir' => "garnet-runtime-{$appLow}",
        ];

        $fromIni = [];

        try {
            $deploy = IniConfig::deploy();
            $fromIni = [
                'remote_path' => $deploy->paramString('remote_path', ''),
                'public_dir' => $deploy->paramString('public_dir', ''),
                'public_name' => $deploy->paramString('public_name', ''),
                'framework_dir' => $deploy->paramString('framework_dir', ''),
                'app_dir' => $deploy->paramString('app_dir', ''),
                'runtime_dir' => $deploy->paramString('runtime_dir', ''),
            ];
        } catch (Throwable) { /* deploy.ini absent — fall back */
        }

        $resolved = [];

        foreach (['remote_path', 'public_dir', 'public_name', 'framework_dir', 'app_dir', 'runtime_dir'] as $key) {
            $cliKey = $key;
            $val = $opts[$cliKey] ?? '';

            if ($val !== '') {
                $resolved[$key] = $val;

                continue;
            }

            if (!empty($fromIni[$key])) {
                $resolved[$key] = $fromIni[$key];

                continue;
            }
            $resolved[$key] = $defaults[$key];
        }

        return $resolved;
    }

    private static function preflightLayout(array $layout): void {
        // public_dir / public_name are only required when a frontend
        // rebuild lands in scope — preflight catches the rest. Missing
        // public_* fall back to defaults in resolveLayout, so they're
        // never empty here anyway.
        $missing = [];

        foreach (['remote_path', 'framework_dir', 'app_dir', 'runtime_dir'] as $key) {
            if ($layout[$key] === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            self::fail('deploy.ini: ' . implode(', ', $missing) . " are empty. Edit WorkDir/ConfigDev/deploy.ini or pass --{$missing[0]}=NAME on CLI.");
        }
    }

    // -------------------------------------------------------------------------
    // 4. Build SHA list from selectors
    // -------------------------------------------------------------------------

    private static function buildShaList(array $opts): array {
        $shas = [];

        if ($opts['since'] !== '') {
            $out = self::gitOut(['log', "--since={$opts['since']}", '--pretty=%H']);

            foreach (explode("\n", trim($out)) as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $shas[] = $line;
                }
            }
        }

        if ($opts['from'] !== '') {
            $parent = self::resolveParent($opts['from']);
            $range = $parent !== '' ? "{$parent}..HEAD" : self::shaListAll($opts['from']);

            if (is_string($range)) {
                foreach (self::shasInRange($range) as $s) {
                    $shas[] = $s;
                }
            } else {
                foreach ($range as $s) {
                    $shas[] = $s;
                }
            }
        }

        if ($opts['after'] !== '') {
            foreach (self::shasInRange("{$opts['after']}..HEAD") as $s) {
                $shas[] = $s;
            }
        }

        if ($opts['range'] !== '') {
            foreach (self::shasInRange($opts['range']) as $s) {
                $shas[] = $s;
            }
        }

        foreach ($opts['commits'] as $c) {
            $shas[] = $c;
        }

        if ($opts['branch'] !== '') {
            $mergeBase = trim(self::gitOut(['merge-base', 'master', $opts['branch']]));

            if ($mergeBase === '') {
                self::fail("--branch={$opts['branch']}: cannot find merge-base with master.");
            }

            foreach (self::shasInRange("{$mergeBase}..{$opts['branch']}") as $s) {
                $shas[] = $s;
            }
        }

        // unique, preserve order
        return array_values(array_unique($shas));
    }

    /** Returns parent sha or '' if commit is the repo's initial commit. */
    private static function resolveParent(string $sha): string {
        [$rc, $out] = self::gitTry(['rev-parse', '--verify', "{$sha}^"]);

        return $rc === 0 ? trim($out) : '';
    }

    /** @return list<string> */
    private static function shaListAll(string $sha): array {
        // Used when sha has no parent (initial commit) — return just it
        return [$sha];
    }

    /** @return list<string> */
    private static function shasInRange(string $range): array {
        $out = self::gitOut(['log', '--pretty=%H', $range]);
        $out = trim($out);

        if ($out === '') {
            return [];
        }

        return explode("\n", $out);
    }

    private static function validateShas(array $shas): void {
        foreach ($shas as $sha) {
            [$rc, $out] = self::gitTry(['cat-file', '-t', $sha]);

            if ($rc !== 0 || trim($out) !== 'commit') {
                self::fail("unknown commit: {$sha}");
            }
        }
    }

    // -------------------------------------------------------------------------
    // 5. Compound diff
    // -------------------------------------------------------------------------

    private static function computeDiff(array $shas): array {
        // Find the chain endpoints by ANCESTRY, not commit date. Commits
        // authored seconds apart can share an identical %ct; sorting by date
        // then picks an arbitrary "oldest" among the tied ones. If the picked
        // oldest is actually the SECOND commit, parent(oldest) lands on the
        // first commit and `parent(oldest)..newest` silently drops the first
        // commit's files from the shipped set. Ancestry is the source of truth.
        $oldest = self::ancestryEndpoint($shas, true);
        $newest = self::ancestryEndpoint($shas, false);

        // Range
        $parent = self::resolveParent($oldest);

        if ($parent !== '') {
            $range = "{$parent}..{$newest}";
            $out = self::gitOut(['diff', '--name-status', '--diff-filter=ACDMRT', $range]);
        } else {
            // Initial commit — use `git show` for the whole tree
            $out = self::gitOut(['show', '--name-status', '--pretty=format:', '--diff-filter=ACDMRT', $oldest]);

            if ($oldest !== $newest) {
                // plus diff from oldest..newest
                $out2 = self::gitOut(['diff', '--name-status', '--diff-filter=ACDMRT', "{$oldest}..{$newest}"]);
                $out = trim($out) . "\n" . trim($out2);
            }
        }

        return self::parseDiffOutput($out);
    }

    /**
     * Pick the ancestry endpoint of a commit set: the commit that is an
     * ancestor of every other (oldest=true) or a descendant of every other
     * (oldest=false). Linear deploy ranges always have such an endpoint.
     * Falls back to commit-date order for non-linear sets (branchy history).
     */
    private static function ancestryEndpoint(array $shas, bool $oldest): string {
        $shas = array_values(array_unique($shas));

        if (count($shas) <= 1) {
            return $shas[0] ?? '';
        }

        foreach ($shas as $candidate) {
            $isEndpoint = true;

            foreach ($shas as $other) {
                if ($other === $candidate) {
                    continue;
                }
                // oldest: candidate must be an ancestor of every other.
                // newest: every other must be an ancestor of candidate.
                $anc = $oldest ? $candidate : $other;
                $desc = $oldest ? $other : $candidate;
                [$rc] = self::gitTry(['merge-base', '--is-ancestor', $anc, $desc]);

                if ($rc !== 0) {
                    $isEndpoint = false;

                    break;
                }
            }

            if ($isEndpoint) {
                return $candidate;
            }
        }

        // Fallback: commit-date order (best effort for non-linear sets).
        $byDate = [];

        foreach ($shas as $sha) {
            $byDate[$sha] = (int)trim(self::gitOut(['show', '-s', '--format=%ct', $sha]));
        }
        asort($byDate);
        $sorted = array_keys($byDate);

        return $oldest ? $sorted[0] : (string)end($sorted);
    }

    /** @return list<array{status:string, path:string, old:?string}> */
    private static function parseDiffOutput(string $out): array {
        $rows = [];

        foreach (explode("\n", trim($out)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\t/', $line);

            if (!$parts || count($parts) < 2) {
                continue;
            }
            $status = $parts[0];

            // Rename: "R100\told\tnew" — keep both
            if (str_starts_with($status, 'R')) {
                if (count($parts) < 3) {
                    continue;
                }
                $rows[] = ['status' => 'R', 'path' => $parts[2], 'old' => $parts[1]];

                continue;
            }

            // C: copy — treat as added
            if (str_starts_with($status, 'C')) {
                if (count($parts) < 3) {
                    continue;
                }
                $rows[] = ['status' => 'A', 'path' => $parts[2], 'old' => null];

                continue;
            }
            $rows[] = ['status' => $status[0], 'path' => $parts[1], 'old' => null];
        }
        // De-duplicate by path: last status wins. R rows kept as-is.
        $byPath = [];

        foreach ($rows as $r) {
            $byPath[$r['path']] = $r;
        }

        return array_values($byPath);
    }

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
        $appPrefix = "Apps/{$appName}/";

        foreach ($diff as $row) {
            $p = $row['path'];

            if (str_starts_with($p, 'FrontBuilder/')) {
                return true;
            }

            if (str_starts_with($p, 'Framework/Bundle/Front/')) {
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

    /** Path to the active app's public asset root (Windows-safe). */
    private static function resolveAppPublicDir(string $appName): string {
        return GarnetEnv::getPublicDir($appName);
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

    /** Convert before/after snapshots into deploy:diff row shape. */
    private static function publicDeltaRows(array $before, array $after, string $assetsDir): array {
        $rows = [];

        // Added or modified
        foreach ($after as $rel => $sig) {
            if (!isset($before[$rel]) || $before[$rel] !== $sig) {
                $rows[] = [
                    'status' => isset($before[$rel]) ? 'M' : 'A',
                    'path' => 'Apps/' . self::getAppName() . '/Public/' . $rel,
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
                    'path' => 'Apps/' . self::getAppName() . '/Public/' . $rel,
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
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(GARNET_ROOT . DS . 'garnet') . ' build';
        passthru($cmd, $code);

        if ($code !== 0) {
            self::fail("frontend build failed (exit {$code}). Fix the build then re-run deploy:diff.");
        }
    }

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

            // Framework/<rest>
            if (str_starts_with($path, 'Framework/')) {
                $rel = substr($path, strlen('Framework/'));
                $out['framework'][] = ['status' => $status, 'path' => $path, 'old' => $row['old'], 'rel_remote' => $rel];

                continue;
            }

            // Apps/<AppName>/{WorkDir,Public,Tests}/<rest> — order matters:
            // more-specific subtrees must be matched before the general
            // app fallback, otherwise Public/* would land in <app_dir>/Public/
            // and Tests/* would ship as production code.
            $appPrefix = "Apps/{$appName}/";
            $wdPrefix = "{$appPrefix}WorkDir/";
            $pubPrefix = "{$appPrefix}Public/";
            $testPrefix = "{$appPrefix}Tests/";

            if (str_starts_with($path, $wdPrefix)) {
                $rel = 'WorkDir/' . substr($path, strlen($wdPrefix));
                $out['runtime'][] = ['status' => $status, 'path' => $path, 'old' => $row['old'], 'rel_remote' => $rel];

                continue;
            }

            if (str_starts_with($path, $pubPrefix)) {
                $rel = substr($path, strlen($pubPrefix));
                $out['public'][] = ['status' => $status, 'path' => $path, 'old' => $row['old'], 'rel_remote' => $rel];

                continue;
            }

            if (str_starts_with($path, $testPrefix)) {
                $out['skip'][] = ['status' => $status, 'path' => $path, 'reason' => 'tests dir'];

                continue;
            }

            // Apps/<AppName>/<rest>  → app (fallback)
            if (str_starts_with($path, $appPrefix)) {
                $rel = substr($path, strlen($appPrefix));
                $out['app'][] = ['status' => $status, 'path' => $path, 'old' => $row['old'], 'rel_remote' => $rel];

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
        $rebrandPublicSegment = $layout['public_name'] !== '' && $layout['public_name'] !== $appLow;

        foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
            $base = rtrim($layout['remote_path'], '/') . '/' . $targets[$bucket];

            foreach ($cat[$bucket] as $row) {
                $rel = $row['rel_remote'];

                // Public bucket carries the rebrand from bundle: a relative
                // path like `assets/MyApp/...` lives at `assets/myapp/...`
                // on the host. Mirror that here so files land in the same
                // place bundle would have put them.
                if ($bucket === 'public' && $rebrandPublicSegment) {
                    $rel = preg_replace(
                        '#(^|/)(assets|upload)/' . preg_quote($appLow, '#') . '(/|$)#',
                        '$1$2/' . $layout['public_name'] . '$3',
                        $rel,
                    );
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

    private static function computeWarnings(array $diff): array {
        $warns = [];
        $touched = array_map(fn ($r) => $r['path'], $diff);

        foreach ($touched as $p) {
            $base = basename($p);

            if ($base === 'composer.json' || $base === 'composer.lock') {
                $warns[] = "{$p} modified — composer install does not run automatically (run it manually after deploying)";
            }

            if ($base === 'package.json' || $base === 'package-lock.json') {
                $warns[] = "{$p} modified — npm install does not run automatically (run it manually after deploying)";
            }
        }

        return array_unique($warns);
    }

    // -------------------------------------------------------------------------
    // 8. Preview
    // -------------------------------------------------------------------------

    private static function printPreview(array $shas, array $cat, array $plan, array $layout, SshClient $ssh, array $warns): void {
        $sshCfg = self::sshDisplay();

        echo "\033[1m=== deploy:diff preview ===\033[0m\n";
        echo "  host        : {$sshCfg}\n";
        echo "  remote_path : {$layout['remote_path']}\n";
        echo "  framework   : {$layout['framework_dir']}\n";
        echo "  app         : {$layout['app_dir']}\n";
        echo "  runtime     : {$layout['runtime_dir']}\n\n";

        echo "\033[1mCommits (\033[0m" . count($shas) . "\033[1m):\033[0m\n";

        foreach ($shas as $sha) {
            $info = trim(self::gitOut(['log', '-1', '--format=%h  %ci  %s', $sha]));
            echo "  {$info}\n";
        }
        echo "\n";

        $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public'] ?? []);
        $skipCount = count($cat['skip']);
        echo "\033[1mChanges (\033[0m{$total} files, {$skipCount} skipped\033[1m):\033[0m\n";

        foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
            if (empty($cat[$bucket] ?? [])) {
                continue;
            }

            foreach ($cat[$bucket] as $row) {
                $statusColor = self::statusColor($row['status']);
                $label = str_pad($bucket, 10);
                $stat = $row['status'];
                echo "  {$label} {$statusColor}{$stat}\033[0m  {$row['path']}\n";
                $remote = self::remoteFor($row, $layout, $bucket);
                $arrow = $stat === 'D' ? "rm {$remote}" : $remote;
                $extra = !empty($row['chmod_x']) ? "  \033[36m(+chmod +x)\033[0m" : '';
                echo "                  → {$arrow}{$extra}\n";
            }
        }

        if ($skipCount > 0) {
            echo "  --\n";
            $names = array_map(fn ($r) => $r['path'], $cat['skip']);
            $shown = array_slice($names, 0, 5);
            $more = $skipCount > 5 ? ' (+' . ($skipCount - 5) . ' more)' : '';
            echo "  Skipped ({$skipCount}): " . implode(', ', $shown) . $more . "\n";

            // Loud callout for bundle-patched files: silent skip lured an
            // operator into ssh:put-ing the raw garnet CLI once and that
            // broke autoload on the host. Make it impossible to miss.
            $patched = array_filter(
                $cat['skip'],
                static fn ($r) => isset($r['reason']) && str_starts_with($r['reason'], 'patched-by-bundle'),
            );

            if (!empty($patched)) {
                echo "\n";

                foreach ($patched as $row) {
                    echo "  \033[33m! patched-by-bundle:\033[0m {$row['path']} — run `php garnet bundle` and `ssh:put` the result.\n";
                }
            }
        }
        echo "\n";

        echo "\033[1mBatches:\033[0m " . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . ' uploads, ' . count($plan['deletes']) . " deletes\n";

        if (!empty($warns)) {
            echo "\n";

            foreach ($warns as $w) {
                echo "\033[33mWARN:\033[0m {$w}\n";
            }
        }
    }

    private static function sshDisplay(): string {
        $ssh = IniConfig::ssh();

        return $ssh->paramString('user', '?') . '@' . $ssh->paramString('host', '?') . ':' . $ssh->paramInt('port', 22);
    }

    private static function remoteFor(array $row, array $layout, string $bucket): string {
        $targets = [
            'framework' => $layout['framework_dir'],
            'app' => $layout['app_dir'],
            'runtime' => $layout['runtime_dir'],
            'public' => $layout['public_dir'] ?? 'public',
        ];

        return rtrim($layout['remote_path'], '/') . '/' . $targets[$bucket] . '/' . $row['rel_remote'];
    }

    private static function statusColor(string $s): string {
        return match ($s) {
            'A' => "\033[32m",  // green
            'D' => "\033[31m",  // red
            'M' => "\033[33m",  // yellow
            default => "\033[36m",
        };
    }

    // -------------------------------------------------------------------------
    // 9. Confirm
    // -------------------------------------------------------------------------

    private static function confirm(bool $skipToken): bool {
        if ($skipToken) {
            echo "\033[33mProceeding (--yes, no prompt).\033[0m\n";

            return true;
        }
        $token = self::randToken(4);
        echo "Type \033[1;36m{$token}\033[0m to confirm: ";
        $entered = trim((string)fgets(STDIN));

        return $entered === $token;
    }

    private static function randToken(int $len): string {
        $alphabet = 'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $out = '';

        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // 10. Apply batches
    // -------------------------------------------------------------------------

    private static function applyBatches(SshClient $ssh, array $plan, bool $verbose, bool $noDelete): array {
        $start = microtime(true);
        $uploaded = 0;
        $deleted = 0;
        $errors = [];
        $chmodFix = [];   // remote paths needing +x after upload

        // 1. mkdir-p (chunk)
        if (!empty($plan['mkdirs'])) {
            foreach (array_chunk($plan['mkdirs'], self::MKDIR_CHUNK) as $i => $chunk) {
                $args = implode(' ', array_map(fn ($d) => "'" . str_replace("'", "'\\''", $d) . "'", $chunk));
                echo '  [mkdir-p ' . ($i + 1) . '] ' . count($chunk) . ' dirs ... ';

                if ($verbose) {
                    echo "\n    ssh mkdir -p {$args}\n  ";
                }
                $r = $ssh->run("mkdir -p {$args}", ['stream' => false]);

                if (!$r->ok()) {
                    echo "\033[31mFAIL\033[0m (exit {$r->exitCode})\n";
                    $errors[] = 'mkdir-p chunk #' . ($i + 1) . ': ' . trim($r->stderr);
                } else {
                    echo "\033[32mOK\033[0m\n";
                }
            }
        }

        // 2. Uploads, grouped by remote_dir, chunked
        $byDir = [];

        foreach ($plan['uploads'] as $up) {
            $byDir[$up['remote_dir']][] = $up;
        }
        $groupIdx = 0;
        $groupTotal = count($byDir);

        foreach ($byDir as $rDir => $items) {
            $groupIdx++;

            foreach (array_chunk($items, self::SCP_CHUNK) as $chunk) {
                $localFiles = array_map(fn ($u) => $u['local'], $chunk);
                echo "  [scp {$groupIdx}/{$groupTotal}] " . count($chunk) . " files → {$rDir}/ ... ";

                if ($verbose) {
                    echo "\n    " . implode(' ', $localFiles) . "\n  ";
                }

                $ok = self::scpMulti($ssh, $localFiles, $rDir, $verbose);

                if (!$ok['ok']) {
                    echo "\033[31mFAIL\033[0m\n";

                    foreach ($ok['errors'] as $e) {
                        $errors[] = $e;
                    }
                } else {
                    echo "\033[32mOK\033[0m\n";
                    $uploaded += count($chunk);

                    // Collect chmod +x targets
                    foreach ($chunk as $up) {
                        if (!empty($up['chmod_x'])) {
                            $chmodFix[] = $up['remote'];
                        }
                    }
                }
            }
        }

        // 3. Deletes (chunked)
        if (!$noDelete && !empty($plan['deletes'])) {
            foreach (array_chunk($plan['deletes'], self::RM_CHUNK) as $i => $chunk) {
                $args = implode(' ', array_map(fn ($d) => "'" . str_replace("'", "'\\''", $d['remote']) . "'", $chunk));
                echo '  [rm ' . ($i + 1) . '] ' . count($chunk) . ' files ... ';

                if ($verbose) {
                    echo "\n    ssh rm -f {$args}\n  ";
                }
                $r = $ssh->run("rm -f {$args}", ['stream' => false]);

                if (!$r->ok()) {
                    echo "\033[31mFAIL\033[0m (exit {$r->exitCode})\n";
                    $errors[] = 'rm chunk #' . ($i + 1) . ': ' . trim($r->stderr);
                } else {
                    echo "\033[32mOK\033[0m\n";
                    $deleted += count($chunk);
                }
            }
        }

        // 4. chmod +x for garnet
        if (!empty($chmodFix)) {
            $args = implode(' ', array_map(fn ($p) => "'" . str_replace("'", "'\\''", $p) . "'", $chmodFix));
            echo '  [chmod +x] ' . count($chmodFix) . ' file(s) ... ';
            $r = $ssh->run("chmod +x {$args}", ['stream' => false]);

            if (!$r->ok()) {
                echo "\033[31mFAIL\033[0m (exit {$r->exitCode})\n";
                $errors[] = 'chmod +x: ' . trim($r->stderr);
            } else {
                echo "\033[32mOK\033[0m\n";
            }
        }

        $duration = round(microtime(true) - $start, 1);

        return compact('uploaded', 'deleted', 'errors', 'duration');
    }

    /**
     * Run scp with multiple local sources into one remote dir.
     * Returns ['ok' => bool, 'errors' => string[]].
     */
    private static function scpMulti(SshClient $ssh, array $locals, string $remoteDir, bool $verbose): array {
        // Build base scp argv via SshClient with one of the locals, then patch
        // We can't easily get the multi-arg scp via SshClient::put — it's
        // single-file. So construct argv inline by replicating buildPutArgv's
        // base-flags portion via single-file put for the first local, then
        // splice extra locals.
        $argv = $ssh->buildPutArgv($locals[0], $remoteDir . '/');
        // argv ends with: ..., $locals[0], "{$user}@{$host}:{$remoteDir}/"
        // Insert extra locals before the destination
        $dest = array_pop($argv);

        foreach (array_slice($locals, 1) as $extra) {
            $argv[] = $extra;
        }
        $argv[] = $dest;

        // Execute via proc_open (capture mode for clean output)
        $sshCfg = SshClient::fromIniConfig();  // for tempkey handling — but we already have $ssh
        // Use the existing $ssh.execute path indirectly: call ::run on the SshClient with a dummy
        // and re-use its tempkey logic? Simpler: use proc_open here directly.

        $tempfile = '';
        // Resolve identity_key tempfile
        $ssh_cfg = IniConfig::ssh();
        $ikey = $ssh_cfg->paramString('identity_key', '');

        if ($ikey !== '') {
            $tempfile = tempnam(sys_get_temp_dir(), 'garnet_ssh_');
            file_put_contents($tempfile, $ikey);
            chmod($tempfile, 0o600);
            $argv = array_map(
                fn ($a) => $a === '<REDACTED-tempfile-path-to-be-generated>' ? $tempfile : $a,
                $argv
            );
        }

        try {
            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = proc_open($argv, $desc, $pipes, null, null, ['bypass_shell' => true]);

            if ($proc === false) {
                return ['ok' => false, 'errors' => ['scp: proc_open failed']];
            }
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($proc);

            if ($exit !== 0) {
                $msg = trim($stderr) !== '' ? trim($stderr) : "scp exit {$exit}";

                return ['ok' => false, 'errors' => ["scp → {$remoteDir}/: {$msg}"]];
            }

            return ['ok' => true, 'errors' => []];
        } finally {
            if ($tempfile !== '' && file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Git helpers
    // -------------------------------------------------------------------------

    private static function gitOut(array $args): string {
        [$rc, $out] = self::gitTry($args);

        if ($rc !== 0) {
            $cmd = 'git ' . implode(' ', $args);
            self::fail("git failed: {$cmd}\n{$out}");
        }

        return $out;
    }

    /** @return array{int, string} */
    private static function gitTry(array $args): array {
        // proc_open with array argv: bypasses shell — no escaping bugs on Windows
        // (escapeshellarg corrupts % and other special chars).
        $argv = array_merge(['git'], $args);
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($argv, $desc, $pipes, null, null, ['bypass_shell' => true]);

        if ($proc === false) {
            return [127, 'proc_open failed for git'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $rc = proc_close($proc);
        $combined = $stdout . ($stderr !== '' ? "\n{$stderr}" : '');

        return [$rc, $combined];
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

    private static function fail(string $msg): void {
        throw new RuntimeException($msg);
    }

    private static function help(): void {
        echo <<<HELP

  \033[1mphp garnet deploy:diff [selector(s)] [flags]\033[0m

  \033[1mWHAT IT DOES\033[0m
  ────────────────────────────────────────────────────────────────────────
  Takes a set of git commits, computes the union of files they touched,
  maps each path to its place on the remote host, and uploads only the
  delta via SSH/SCP. No tarballs, no `git pull` on the server, no full
  redeploy — point-fix changes in seconds.

  Default mode is \033[1mDRY-RUN\033[0m: you see a preview (file list,
  remote targets, batch counts) and nothing is touched on the host. Pass
  \033[36m--apply\033[0m to actually push.

  \033[1mCONNECTION & LAYOUT\033[0m
  ────────────────────────────────────────────────────────────────────────
  Host, user, identity key, strict_host_key_checking →
      \033[2mApps/<App>/WorkDir/Config*/ssh.ini\033[0m
  Remote layout (where things land on the host) →
      \033[2mApps/<App>/WorkDir/Config*/deploy.ini\033[0m
      remote_path   = "/var/www/u…/data/www"
      public_dir    = "example.com"                   ← docroot
      public_name   = "myapp"                          ← rebrand segment
      framework_dir = "garnet-framework"
      app_dir       = "garnet-app-myapp"
      runtime_dir   = "garnet-runtime-myapp"
  Any field is overridable per-invocation via the matching CLI flag.

  \033[1mPATH MAPPING\033[0m  (what goes where on the host)
  ────────────────────────────────────────────────────────────────────────
    Framework/<rest>            → <framework_dir>/<rest>
    Apps/<App>/WorkDir/<rest>   → <runtime_dir>/WorkDir/<rest>
    Apps/<App>/<rest>           → <app_dir>/<rest>
    Apps/<App>/Public/<rest>         → <public_dir>/<rest>   (rebrand applied)
    garnet  /  _shared_index.php → SKIPPED — see "Bundle-patched" below
    everything else (tests/, docs/, tooling/, FrontBuilder/, …) → skipped

  Rebrand: `assets/<appLower>/…` and `upload/<appLower>/…` segments
  are rewritten to `assets/<public_name>/…` etc., matching what `bundle`
  would have done in the dist tree.

  \033[1mFRONTEND AUTO-REBUILD\033[0m
  ────────────────────────────────────────────────────────────────────────
  When any of these change, `Apps/<App>/Public/assets/…` is stale:
      FrontBuilder/**             (TS/TSX/SCSS sources, rspack config)
      Framework/Bundle/Front/**   (shared islands, common JS/CSS)
      Apps/<App>/Front*/**        (per-app islands, assets)
      Apps/<App>/**/I18nDataRu.php, I18nDataEn.php  (regenerate TS i18n)

  Auto-detect is ON by default:
    \033[36mdry-run\033[0m: prints "frontend source changes detected — rebuild
              will run on --apply" and skips rspack.
    \033[36m--apply\033[0m: snapshots `Apps/<App>/Public/`, runs `php garnet build`,
              snapshots again, ships ONLY the delta (added/modified/
              deleted files). Hashed asset names mean unchanged sources
              keep the same filename and stay off the wire.

  Toggles:
    \033[36m--frontend\033[0m       force the rebuild even with no detected source changes
    \033[36m--no-frontend\033[0m    skip the rebuild even if sources changed
                     (use when you've already built locally and just
                     want to push the PHP delta)

  \033[36m--full-public\033[0m    re-ship every file under `Apps/<App>/Public/` plus the 4
                     *Gen.php — no git diff, no marker check, forced rspack
                     rebuild. The pre-snapshot is forced empty so every file
                     appears as an add. Use for initial deploy, disaster
                     recovery (e.g. remote /assets/ was wiped), or to guarantee
                     the host's public tree matches local exactly. Dry-run
                     prints a count of files that would ship without running
                     rspack. Combine with --apply to execute.

  \033[1mBUNDLE-PATCHED FILES\033[0m  (NEVER auto-shipped raw)
  ────────────────────────────────────────────────────────────────────────
  `garnet` (the root CLI) and `_shared_index.php` are rewritten by
  `php garnet bundle` before they're safe on the host (GARNET_ROOT,
  framework-dir name, autoload path). deploy:diff refuses to ship them
  raw — instead it prints a yellow callout pointing you at:
      \033[36mphp garnet bundle\033[0m   then   \033[36mphp garnet ssh:put dist/<App>/<runtime>/garnet …\033[0m

  \033[1mDEPLOY-SHA MARKER\033[0m  (auto-resume from previous deploy)
  ────────────────────────────────────────────────────────────────────────
  After every successful `--apply`, the newest sha shipped is written to
  \033[36m<runtime>/WorkDir/.deploy-sha\033[0m on the host. The next time you run
  `deploy:diff` WITHOUT any commit selector, the file is read and used
  as `--after=SHA` automatically — you just type `php garnet deploy:diff`
  and ship everything new since the last deploy. If the remote sha is
  missing from your local history (e.g. after a rebase/force-push), the
  command stops and asks for an explicit selector.

  \033[1mFILES MODE\033[0m  (--file=PATH / --files=A,B,C)
  ────────────────────────────────────────────────────────────────────────
  Ship specific working-tree files without going through git. Useful for
  hot-fixes when committing first would waste time, or for pushing freshly
  built assets from `Apps/<App>/Public/` without a full commit→deploy cycle.

  Pass one or more repo-relative paths:
    \033[36m--file=PATH\033[0m       repeatable: --file=A --file=B --file=C
    \033[36m--files=A,B,C\033[0m     comma-separated alias (splits on commas)

  Behaviour:
    - Git selectors (--commit, --range, --after, --since, --from,
      --branch) are ignored. A one-line note is printed if any are present.
    - No rspack rebuild. Trusts that you already built locally (or are
      hot-fixing PHP only).
    - No remote deploy-sha marker advance. Point-deploys are surgical;
      they don't represent "the new last-known-deployed state".
    - Every listed file is treated as status 'M' (modified). Deletes are
      not supported — fall back to git-diff mode for that.
    - Auto-include: when any file is under `Apps/<App>/Public/`, the 4
      *Gen.php files are appended automatically (rebranded via shadow).
    - Rebrand is applied when public_name ≠ appName, same as the normal
      commit-based pipeline. Uses PublicPathRebrander — no duplication.
    - Paths outside known buckets (Framework/, Apps/<App>/, WorkDir/,
      Apps/<App>/Public/) produce a clear error.

  \033[1mCOMMIT SELECTORS\033[0m  (combine freely; union of all sha is taken)
  ────────────────────────────────────────────────────────────────────────
    \033[36m(none)\033[0m             auto-resume from remote deploy-sha marker
                       (see above). Use this for the common case.
    \033[36m--since=DATE\033[0m       e.g. "2 days ago", "yesterday", "2026-05-18"
                       — passes straight to `git log --since=…`
    \033[36m--from=SHA\033[0m         SHA itself and every commit AFTER it (≈ SHA^..HEAD).
                       Include the SHA you name.
    \033[36m--after=SHA\033[0m        every commit STRICTLY AFTER the SHA (SHA..HEAD).
                       Use when you've already deployed up to and
                       including SHA, and want only what came later.
    \033[36m--range=A..B\033[0m       literal git range — A excluded, B included
                       (use A..B for a clean range, A...B for symmetric).
    \033[36m--commit=SHA\033[0m       a single commit (any rev: SHA, HEAD, HEAD~3, tag).
                       Repeatable: --commit=abc --commit=def
    \033[36m--branch=NAME\033[0m      $(merge-base master NAME)..NAME — everything
                       on a feature branch since it diverged from master.

  \033[1mFLAGS\033[0m
  ────────────────────────────────────────────────────────────────────────
    \033[36m--apply\033[0m            actually push (default is dry-run preview)
    \033[36m--dry-run\033[0m          explicit dry-run (default if --apply absent)
    \033[36m--yes, -y\033[0m          skip the typed-token confirmation prompt
    \033[36m--no-delete\033[0m        keep removed files on the host (skip D ops)
    \033[36m--exclude=GLOB\033[0m     fnmatch on the local path; repeatable
                       e.g. --exclude='Apps/MyApp/Migrations/*'
    \033[36m--limit=N\033[0m          raise the 200-file safety cap
    \033[36m--verbose, -v\033[0m      print every ssh/scp argv (debug)
    \033[36m--strict\033[0m           fail if any file ends up in 'skipped'
    \033[36m--frontend\033[0m / \033[36m--no-frontend\033[0m
                       force / skip frontend rebuild (see above)
    \033[36m--reset-opcache\033[0m    after a successful apply, ssh into the host and run
                       `php -r 'opcache_reset();'`. Only effective when the
                       FPM pool shares opcache with CLI (atypical on shared
                       hosting where opcache.enable_cli=0 is the default).
                       With opcache.validate_timestamps=1 (default on most
                       hosts) the new files are picked up automatically on
                       the next request anyway — this flag is for the rare
                       prod profile that disables timestamp checks.
    \033[36m--no-boot-check\033[0m    skip the post-apply boot smoke. By default, after a
                       successful upload the command runs `php garnet noop` on
                       the host and FAILS (exit 1) if the app no longer boots —
                       catching a half-coherent file set that leaves the site
                       returning 500 (e.g. a cherry-picked --commit that skipped
                       a dependency). Disable only when the host can't run the
                       CLI for unrelated reasons.
    \033[36m--public-dir=NAME\033[0m / \033[36m--public-name=NAME\033[0m
    \033[36m--framework-dir=NAME\033[0m / \033[36m--app-dir=NAME\033[0m
    \033[36m--runtime-dir=NAME\033[0m
                       override deploy.ini values per invocation
    \033[36m--file=PATH\033[0m         repo-relative path; repeatable.
                       Activates files mode (see FILES MODE above).
    \033[36m--files=A,B,C\033[0m      comma-separated alias for --file=
    \033[36m--full-public\033[0m    re-ship every Apps/<App>/Public/ file + *Gen.php (see above)

  \033[1mEXAMPLES — COPY/PASTE FRIENDLY\033[0m
  ────────────────────────────────────────────────────────────────────────
  # 1. Preview the latest commit (safe, no side effects).
  php garnet deploy:diff --commit=HEAD

  # 2. Apply the latest commit. PHP + auto-rebuilt frontend delta.
  php garnet deploy:diff --commit=HEAD --apply

  # 3. Catch the server up to HEAD when last applied was abc1234.
  php garnet deploy:diff --after=abc1234 --apply

  # 4. Everything from a feature branch since it forked off master.
  php garnet deploy:diff --branch=feature/foo --apply

  # 5. PHP hot-patch — skip the rebuild entirely (you built locally).
  php garnet deploy:diff --commit=HEAD --no-frontend --apply

  # 6. Force frontend rebuild even when only PHP changed
  #    (e.g. someone touched a *Gen.php without bumping a TS file).
  php garnet deploy:diff --commit=HEAD --frontend --apply

  # 7. Deploy last 24h, skip migrations from the diff.
  php garnet deploy:diff --since="1 day ago" \\
       --exclude='Apps/MyApp/Migrations/*' --apply

  # 8. Cherry-pick two specific commits, no confirmation.
  php garnet deploy:diff --commit=abc1234 --commit=def5678 --apply --yes

  # 9. Big release; raise safety cap.
  php garnet deploy:diff --range=v1.2..HEAD --limit=500 --apply

  # 10. Investigate: what would change without applying?
  php garnet deploy:diff --after=v1.2 -v        # verbose dry-run

  # 11. Hot PHP patch + opcache reset (server with shared CLI/FPM opcache).
  php garnet deploy:diff --commit=HEAD --no-frontend --reset-opcache --apply

  # 12. Hot-fix two PHP files (rebrand auto-applied if needed)
  php garnet deploy:diff \\
       --file=Apps/MyApp/Foreground/Controllers/Foo.php \\
       --file=Apps/MyApp/Foreground/Controllers/Bar.php --apply

  # 13. Push freshly-built assets without committing — Gen.php auto-included
  php garnet deploy:diff \\
       --file=Apps/MyApp/Public/assets/myapp/gen/js/foreground.foreground.XXX.gen.js \\
       --apply

  # 14. Recovery: re-ship every Apps/<App>/Public/ file with fresh rebrand.
  # Use after wiping remote /assets/ or to guarantee parity with local.
  php garnet deploy:diff --full-public --apply --reset-opcache

  \033[1mWHAT IT DOES NOT DO\033[0m
  ────────────────────────────────────────────────────────────────────────
  - Run DB migrations.        Use `php garnet deploy` (full release) or
                              call `migration` over ssh.
  - Toggle maintenance mode.  Same — `php garnet deploy` handles that.
  - Reset opcache for FPM.    Only `--reset-opcache` (CLI opcache) is
                              wired up. On shared hosts where CLI and
                              FPM opcaches are separate (the common case),
                              the FPM pool needs `validate_timestamps=1`
                              (default) to pick up the new files, or a
                              manual restart of the pool.
  - Ship `garnet` / `_shared_index.php` / `.env`.  Use `bundle` + `ssh:put`.
  - Ship `WorkDir/Config/*.ini`.  Server-owned state; bundle has the
                                  same default.

  \033[1mSEE ALSO\033[0m
  ────────────────────────────────────────────────────────────────────────
  php garnet deploy             full release (maintenance / migrate /
                                cache / off)
  php garnet bundle             build dist tree for a fresh host bootstrap
  php garnet ssh:put <l> [r]    one-off file copy (for bundle-patched files)
  php garnet sql "<query>"      run a query against the active app's DB


HELP;
    }
}
