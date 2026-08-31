<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use Throwable;

/**
 * One command, safe by construction, that takes a release all the way to
 * live: build the app fresh (via GarnetBundleCommand's own build+package
 * logic — the same code path `php garnet bundle` uses, already fixed and
 * verified for both the legacy monorepo and standalone-app layouts), put
 * the host into maintenance, push public/framework/app to an EXISTING host
 * (never touching the runtime dir's WorkDir/ — real Config/*.ini, real DB
 * backups, real logs, the one thing on the host that isn't safe to
 * blindly overwrite), then trigger the existing, already-safe
 * `php garnet deploy` (maintenance -> backup -> migrate -> cache -> off)
 * remotely over SSH as the final step.
 *
 * Exists because the alternative — a manual sequence of `bundle` +
 * several `ssh:put` calls + remembering to also force a `deploy:diff
 * --frontend` resync + remembering to SSH in afterwards and run
 * `php garnet deploy` for any pending migration — is exactly the failure
 * mode that broke production twice in one session: an operator (human or
 * agent) forgetting one step out of several, with no single command
 * making that structurally impossible. This command has no such gap:
 * build, ship, and migrate are one call, so there is no intermediate
 * state — and no separate manual step — to forget to complete.
 *
 * framework-dir/app-dir are shipped via upload-to-a-fresh-name-then-rename
 * (see shipDir()), not rm -rf + re-upload — a real production incident
 * showed the latter leaves the live dir missing/partial for the whole
 * transfer, and a request landing in that window gets a raw fatal error
 * (worse: maintenance mode alone can't fully cover this, since
 * MaintenanceMiddleware is itself a framework class). Maintenance mode is
 * still set for the whole window as defense-in-depth against everything
 * else a swap can't fix on its own (stale asset references, cache).
 *
 * Migrations are triggered via the EXISTING `deploy` command rather than
 * reimplemented here, specifically so its safety guarantees (maintenance
 * stays ON on any failure, backup-before-migrate) are reused, not
 * duplicated or risked drifting. --skip-migrate opts out for whoever
 * wants to review the shipped code live before touching the schema —
 * maintenance is lifted by this command itself in that case, since nothing
 * downstream will do it.
 */
class GarnetDeployFullCommand {
    public static function run(array $args): void {
        if (in_array('--help', $args, true) || in_array('-h', $args, true) || ($args[0] ?? '') === 'help') {
            self::help();

            return;
        }

        $appName = GarnetEnv::requireAppName();
        $noBootCheck = in_array('--no-boot-check', $args, true);
        $skipBuild = in_array('--skip-build', $args, true);
        $skipMigrate = in_array('--skip-migrate', $args, true);
        $skipBackup = in_array('--skip-backup', $args, true);

        // Boot the app once, up front, so IniConfig::deploy() (deploy.ini)
        // is actually readable — resolveLayout() needs it and would
        // otherwise silently fall back to bare defaults (caught by its own
        // try/catch), exactly the bug this command's first real run hit.
        self::bootstrapApp($appName);

        $layout = self::resolveLayout();
        self::preflightLayout($layout);

        $ssh = SshClient::fromIniConfig();
        $ssh->validate();

        // 1. Build — reuses GarnetBundleCommand's own build+package logic
        // unmodified (no phar/zip; --keep-dir so the tree survives for us
        // to read). Same code path `php garnet bundle` uses on its own.
        //
        // Sibling dir names are passed EXPLICITLY (not left for bundle to
        // re-derive from deploy.ini itself) — bundle's own internal
        // deploy.ini read (readDeployDefaults()) does its own
        // `require run_cmd.php` bootstrap attempt, which — now that this
        // process already bootstrapped once above — would redeclare
        // classes and get silently swallowed by that method's own
        // try/catch, falling back to bare defaults that could disagree
        // with $layout above. Passing the flags sidesteps that entirely:
        // CLI flags take precedence over any ini read bundle might attempt.
        echo "\033[1m=== Garnet Deploy (full): {$appName} ===\033[0m" . PHP_EOL . PHP_EOL;
        self::step('1/6', 'Building fresh (php garnet bundle --keep-dir --no-phar)');
        $bundleArgs = [
            '--keep-dir', '--no-phar',
            '--public-dir=' . $layout['public_dir'],
            '--framework-dir=' . $layout['framework_dir'],
            '--app-dir=' . $layout['app_dir'],
            '--runtime-dir=' . $layout['runtime_dir'],
            '--public-name=' . $layout['public_name'],
        ];

        if ($skipBuild) {
            $bundleArgs[] = '--skip-build';
        }
        GarnetBundleCommand::run($bundleArgs);
        echo PHP_EOL;

        $distApp = self::distAppPath($appName);

        if (!is_dir($distApp)) {
            self::fail("Expected bundle output at {$distApp} but it's missing — bundle must have failed silently.");
        }

        // Bundle names its own sibling dirs from the SAME deploy.ini this
        // command already read, so these always agree with $layout.
        $distPublic = $distApp . DS . $layout['public_dir'];
        $distFw = $distApp . DS . $layout['framework_dir'];
        $distAppDir = $distApp . DS . $layout['app_dir'];
        $distRuntime = $distApp . DS . $layout['runtime_dir'];

        foreach (['public_dir' => $distPublic, 'framework_dir' => $distFw, 'app_dir' => $distAppDir] as $label => $path) {
            if (!is_dir($path)) {
                self::fail("Bundle output is missing its {$label} ({$path}) — layout mismatch between this command and bundle?");
            }
        }

        $remoteRoot = rtrim($layout['remote_path'], '/');

        $remoteRuntime = "{$remoteRoot}/{$layout['runtime_dir']}";

        // 2. Maintenance ON before anything remote gets touched. This is
        // NOT optional/defense-in-depth here — a real production incident
        // showed why: framework-dir's swap once left a window where
        // Bundle/Middlewares/MaintenanceMiddleware itself (a framework
        // class) couldn't be autoloaded, so even the maintenance page
        // can't be trusted to render if the swap below isn't also made
        // effectively instantaneous (see shipDir()'s doc comment). Both
        // fixes together — instant atomic swap AND a maintenance flag for
        // everything else (mixed old/new asset refs, cache staleness) —
        // are what make this safe. Mirrors `php garnet deploy`'s own
        // policy: stays ON on any failure below, only lifted once this
        // command's own responsibility (ship + boot check) is verified.
        self::step('2/6', 'Maintenance mode ON (remote)');
        // `php garnet maintenance on` run directly on the host with no IP
        // args auto-detects ITS OWN public IP as the allow-list (fine when
        // an operator SSHes in from elsewhere, useless here) — it would
        // lock out the very operator running this deploy. Detect the local
        // machine's public IP the same way `maintenance:remote` does and
        // pass it through explicitly.
        $operatorIp = self::detectLocalPublicIp();
        $maintOnCmd = 'cd ' . escapeshellarg($remoteRuntime) . ' && php garnet maintenance on'
            . ($operatorIp !== '' ? ' ' . escapeshellarg($operatorIp) : '');
        $maintOn = $ssh->run($maintOnCmd, ['stream' => false]);

        if (!$maintOn->ok()) {
            self::fail("Could not enable maintenance mode on the host (exit {$maintOn->exitCode}): {$maintOn->stderr}");
        }
        echo PHP_EOL;

        // 3. Ship framework/app dirs — both 100% app-controlled, no
        // WorkDir/Config/state and nothing the hosting provider ever
        // injects there, so a full replace is safe in principle. shipDir()
        // itself uploads to a fresh sibling name and swaps it in with a
        // near-instant rename rather than rm -rf + re-upload — see its doc
        // comment for the incident that made this necessary.
        //
        // Public dir is DIFFERENT and must NOT be wholesale-wiped: shared
        // hosting can inject files into the docroot that live outside this
        // app's repo entirely and are never part of a bundle build — hit
        // this for real testing this exact command against slotbook.ru,
        // where an rm -rf of the public dir deleted its `.htaccess`
        // (Apache/reg.ru's mod_rewrite rule routing everything through
        // index.php) and every non-root URL 404'd until it was manually
        // restored. `deploy:diff` never wholesale-replaces public/ either
        // — it only ships the delta — so this merge-only upload (no
        // delete) matches that existing, already-safe precedent.
        self::step('3/6', 'Shipping public (merge, no delete) / framework / app (atomic swap)');
        self::shipPublicDir($ssh, $distPublic, "{$remoteRoot}/{$layout['public_dir']}");
        self::shipDir($ssh, $distFw, "{$remoteRoot}/{$layout['framework_dir']}", 'framework');
        self::shipDir($ssh, $distAppDir, "{$remoteRoot}/{$layout['app_dir']}", 'app');
        echo PHP_EOL;

        // 4. Runtime dir: ONLY the dispatcher + shared bootstrap — NEVER
        // the whole tree. The live runtime-dir/WorkDir carries real
        // Config/*.ini (DB creds, SSH creds), real DB backups, real log
        // journals — none of that exists in a fresh bundle build at all,
        // so a wholesale overwrite would either wipe it (rsync-style) or
        // at minimum risk clobbering it. Only these two files ever need to
        // change release-to-release; both are already idempotent/versioned
        // by content, and the boot check below verifies the swap didn't
        // break anything before we call it done.
        self::step('4/6', 'Syncing runtime dispatcher (garnet + _shared_index.php only — WorkDir left untouched)');

        foreach (['garnet', '_shared_index.php'] as $file) {
            $localFile = $distRuntime . DS . $file;

            if (!is_file($localFile)) {
                continue;
            }
            $put = $ssh->put($localFile, "{$remoteRuntime}/{$file}", ['stream' => false]);

            if (!$put->ok()) {
                self::fail("Failed to upload {$file} to the runtime dir (exit {$put->exitCode}).");
            }
            echo "  -> {$layout['runtime_dir']}/{$file}" . PHP_EOL;
        }
        $ssh->run('chmod 755 ' . escapeshellarg("{$remoteRuntime}/garnet"), ['stream' => false]);
        echo PHP_EOL;

        // 5. Boot check — the same safety net deploy:diff already relies
        // on: confirm the host can actually start the app after the push,
        // rather than finding out from a live 500. Maintenance is still ON
        // here on purpose — this check happens before anyone but the
        // allow-listed operator IP can see the result either way.
        if ($noBootCheck) {
            self::step('5/6', 'Boot check SKIPPED (--no-boot-check)');
            echo PHP_EOL;
        } else {
            self::step('5/6', 'Boot check (php garnet noop on host)');
            $cmd = 'cd ' . escapeshellarg($remoteRuntime) . ' && php garnet noop 2>&1';
            $res = $ssh->run($cmd, ['stream' => false]);

            if ($res->exitCode !== 0) {
                echo "\033[31m  [FAIL] the app does NOT boot after this push:\033[0m" . PHP_EOL;

                foreach (array_filter(explode("\n", trim($res->stdout . "\n" . $res->stderr))) as $line) {
                    echo "    \033[90m{$line}\033[0m" . PHP_EOL;
                }
                self::fail('Boot check failed — the host is left in maintenance mode intentionally. Investigate before trusting this release.');
            }
            echo '  ' . "\033[32m[OK]\033[0m app boots cleanly on the host" . PHP_EOL . PHP_EOL;
        }

        // 6. Migrations — triggers the EXISTING, already-safe `php garnet
        // deploy` (maintenance → backup → migrate → cache → off) remotely
        // over SSH, so this one command really does take a release all the
        // way to "live," not just "files are there, remember to migrate."
        // Kept as a genuinely separate underlying command (not duplicated
        // logic here) because it must run FROM the runtime dir's own
        // context on the host (that's where WorkDir/Config/db.ini
        // resolves) — same command whether triggered remotely here or run
        // by hand later, so its own safety guarantees (maintenance stays
        // ON on any failure) are never bypassed or reimplemented. That
        // command's own maintenance-on call is a harmless idempotent
        // re-write of the same flag we already set in step 2/6; its
        // maintenance-off call at the very end is what finally reopens the
        // site — so the ON we set above simply stays in effect until then.
        if ($skipMigrate) {
            // Nothing downstream will turn maintenance back off — do it
            // ourselves now that shipping + boot check are both verified.
            self::step('6/6', 'Migrations SKIPPED (--skip-migrate) — maintenance mode OFF (remote)');
            $maintOff = $ssh->run('cd ' . escapeshellarg($remoteRuntime) . ' && php garnet maintenance off', ['stream' => false]);

            if (!$maintOff->ok()) {
                self::fail("Ship succeeded but could not disable maintenance mode (exit {$maintOff->exitCode}): {$maintOff->stderr}. Run `php garnet maintenance off` on the host manually.");
            }
            echo "  run \033[36mphp garnet deploy\033[0m from inside {$layout['runtime_dir']}/ on the host when ready to migrate."
                . PHP_EOL;
        } else {
            self::step('6/6', 'Migrating (php garnet deploy on host: maintenance → backup → migrate → cache → off)');
            $deployArgs = $skipBackup ? ' --skip-backup' : '';
            $cmd = 'cd ' . escapeshellarg($remoteRuntime) . ' && php garnet deploy' . $deployArgs;
            $res = $ssh->run($cmd, ['stream' => true]);

            if ($res->exitCode !== 0) {
                self::fail(
                    'Remote `php garnet deploy` failed (exit ' . $res->exitCode . ') — the host is'
                    . ' left in maintenance mode intentionally (see its own output above).'
                    . ' Files are already shipped correctly; only the migration step needs attention.'
                );
            }
        }
        echo PHP_EOL;

        // Advance the deploy-sha marker so a later `deploy:diff` (no
        // selector) auto-resumes from here instead of re-diffing this whole
        // release — this command ships via bundle+atomic-swap, bypassing
        // GarnetDeployDiffCommand's own git-diff pipeline entirely, so
        // nothing else would ever write this marker for it. Non-fatal by
        // design (gitTry(), not gitOut()): a release that already fully
        // shipped and migrated must not exit(1) over a marker bookkeeping
        // failure at the very last step.
        [$gitRc, $headSha] = GarnetDeployDiffCommand::gitTry(['rev-parse', 'HEAD']);

        if ($gitRc === 0) {
            GarnetDeployDiffCommand::writeRemoteDeploySha($ssh, $layout, trim($headSha));
        } else {
            echo "\033[33mwarn:\033[0m could not read local HEAD sha to advance the remote deploy-sha marker "
                . "(git exit {$gitRc}). Run `php garnet deploy:diff --commit=HEAD --apply` once to reseed it, "
                . 'or the next deploy:diff will re-diff this whole release.' . PHP_EOL;
        }

        echo "\033[32m=== Deploy complete — {$appName} is live at {$layout['remote_path']} ===\033[0m" . PHP_EOL;
    }

    /**
     * Upload the fresh local tree to a brand-new sibling name, then swap it
     * into place with two chained `mv`s (plain renames, same filesystem) in
     * one SSH round-trip. Only ever call this on framework-dir/app-dir —
     * both 100% app-controlled with no hosting-injected files. NEVER call
     * this on public-dir (see shipPublicDir()) or the runtime dir (its
     * WorkDir/ carries real Config/*.ini, DB backups, log journals).
     *
     * This used to be rm -rf followed by a fresh scp -r upload — simpler,
     * but it left $remoteDir completely missing/partial for the entire
     * (sometimes multi-second) transfer. A real production incident hit
     * exactly that window: framework-dir's re-upload got interrupted, and a
     * live request landed while vendor/ was half-uploaded, producing a raw
     * "Class ... not found" fatal error straight to the browser — and
     * critically, maintenance mode alone can't fully guard against this,
     * because MaintenanceMiddleware is itself a framework class that needs
     * the same autoloader to render. Uploading to a fresh name first means
     * $remoteDir keeps serving correctly for the whole transfer; only the
     * swap itself (two renames) touches the live name, and that's fast
     * enough to be effectively atomic.
     *
     * `scp -r <local> <newRemoteName>` creates <newRemoteName> as an exact
     * copy of $local's contents as long as <newRemoteName> doesn't already
     * exist yet (same semantics as `cp -r a b` when b is absent) — unlike
     * scp'ing into an existing directory, which nests $local inside it by
     * basename instead (see shipPublicDir()/putRecursive(), which relies on
     * exactly that nesting behavior for its merge semantics).
     */
    private static function shipDir(SshClient $ssh, string $localDir, string $remoteDir, string $label): void {
        $remoteDirNorm = rtrim(str_replace('\\', '/', $remoteDir), '/');
        $remoteParent = dirname($remoteDirNorm);
        $baseName = basename($remoteDirNorm);
        $suffix = bin2hex(random_bytes(4));
        $tmpName = "{$baseName}.new-{$suffix}";
        $bakName = "{$baseName}.bak-{$suffix}";
        $tmpDir = "{$remoteParent}/{$tmpName}";

        $put = $ssh->put($localDir, $tmpDir, ['stream' => false, 'recursive' => true]);

        if (!$put->ok()) {
            self::fail("Upload of {$label} to staging path {$tmpDir} failed (exit {$put->exitCode}). The live {$remoteDirNorm} was never touched.");
        }

        $swap = sprintf(
            'cd %s && (test -e %s && mv %s %s || true) && mv %s %s',
            escapeshellarg($remoteParent),
            escapeshellarg($baseName), escapeshellarg($baseName), escapeshellarg($bakName),
            escapeshellarg($tmpName), escapeshellarg($baseName)
        );
        $res = $ssh->run($swap, ['stream' => false]);

        if (!$res->ok()) {
            self::fail(
                "Atomic swap of {$label} into {$remoteDirNorm} failed (exit {$res->exitCode}): {$res->stderr}."
                . " The new build is staged at {$tmpDir} — investigate before retrying."
            );
        }

        // Best-effort cleanup of the old copy — not fatal if it lingers
        // (disk space only; the live dir is already correct at this point).
        $ssh->run('rm -rf ' . escapeshellarg("{$remoteParent}/{$bakName}"), ['stream' => false]);

        echo "  -> {$remoteDirNorm}" . PHP_EOL;
    }

    /**
     * Ship the public dir WITHOUT ever deleting anything remote first —
     * `scp -r` into an already-existing target directory merges (adds new
     * files, overwrites same-named ones, leaves everything else alone),
     * confirmed by testing against a scratch remote dir. This is the one
     * dir a full redeploy must never wholesale-replace: shared hosting can
     * (and, on this exact host, does) inject files straight into the
     * docroot that are never part of this app's repo or any bundle build
     * at all — a real incident testing `deploy:full` against slotbook.ru:
     * an `rm -rf` of the public dir silently deleted `.htaccess` (the
     * Apache/reg.ru mod_rewrite rule routing every non-file URL through
     * index.php), and every route except the bare "/" 404'd until it was
     * manually restored. `deploy:diff` already ships public assets this
     * same additive way — it only ever pushes the delta, never wipes —
     * so this matches that existing, already-safe precedent rather than
     * introducing new (destructive) behavior for this one dir.
     */
    private static function shipPublicDir(SshClient $ssh, string $localDir, string $remoteDir): void {
        $remoteDirNorm = rtrim(str_replace('\\', '/', $remoteDir), '/');
        $remoteParent = dirname($remoteDirNorm);

        $ensure = $ssh->run('mkdir -p ' . escapeshellarg($remoteDirNorm), ['stream' => false]);

        if (!$ensure->ok()) {
            self::fail("Could not ensure the remote public dir exists ({$remoteDirNorm}): {$ensure->stderr}");
        }

        self::putRecursive($ssh, $localDir, $remoteParent, $remoteDirNorm, 'public');
    }

    /** Shared recursive-upload step used by both shipDir() and shipPublicDir(). */
    private static function putRecursive(SshClient $ssh, string $localDir, string $remoteParent, string $remoteDirNorm, string $label): void {
        // SshClient::put() does NOT auto-detect a directory upload —
        // 'recursive' must be requested explicitly (only GarnetSshCommand,
        // the ssh:put CLI wrapper, does that is_dir() check, and this
        // command calls SshClient directly, bypassing it). Missing this
        // failed in testing too: scp errors "not a regular file" instead
        // of silently doing a plain file copy.
        $put = $ssh->put($localDir, $remoteParent, ['stream' => false, 'recursive' => true]);

        if (!$put->ok()) {
            self::fail("Upload of {$label} to {$remoteDirNorm} failed (exit {$put->exitCode}).");
        }
        echo "  -> {$remoteDirNorm}" . PHP_EOL;
    }

    /**
     * Boot the app (same pattern as GarnetDeployDiffCommand/GarnetDbBackup
     * Command's own bootstrapApp()) so IniConfig::deploy()/::ssh() are
     * actually readable. Must run exactly once, before anything else in
     * this process tries to read deploy.ini — including before
     * GarnetBundleCommand::run(), whose own internal deploy.ini read
     * would otherwise attempt a second `require run_cmd.php` and get
     * silently swallowed by a redeclaration error.
     */
    private static function bootstrapApp(string $appName): void {
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

    /**
     * Detect the operator's own public IP so it can be allow-listed when
     * enabling remote maintenance mode — same detection `maintenance:remote`
     * already uses. Returns '' on failure; the remote command then falls
     * back to allow-listing its own IP (a warning, not fatal, since the
     * operator can still reach the host over SSH regardless).
     */
    private static function detectLocalPublicIp(): string {
        $detected = @file_get_contents('https://api.ipify.org');
        $detected = is_string($detected) ? trim($detected) : '';

        return filter_var($detected, FILTER_VALIDATE_IP) !== false ? $detected : '';
    }

    /**
     * Same path GarnetBundleCommand::run() writes its output to — must
     * mirror its own \$isAppMode computation exactly, since this command
     * calls GarnetBundleCommand::run() in-process just above and then reads
     * back whatever it wrote. GARNET_ROOT alone would be wrong in app-mode
     * (it's the vendored framework package dir there, not the app root).
     */
    private static function distAppPath(string $appName): string {
        $isAppMode = GarnetRunner::$appDir !== ''
            && str_replace('\\', '/', (string)realpath(GarnetRunner::$appDir))
               !== str_replace('\\', '/', (string)realpath(GarnetRunner::$frameworkDir));

        return ($isAppMode ? GarnetRunner::$appDir : GARNET_ROOT) . DS . 'dist' . DS . $appName;
    }

    /** @return array{remote_path:string, public_dir:string, public_name:string, framework_dir:string, app_dir:string, runtime_dir:string} */
    private static function resolveLayout(): array {
        $appName = GarnetEnv::requireAppName();
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
        } catch (Throwable) {
            // deploy.ini absent/unreadable — fall back to defaults below.
        }

        $resolved = [];

        foreach ($defaults as $key => $default) {
            $resolved[$key] = ($fromIni[$key] ?? '') !== '' ? $fromIni[$key] : $default;
        }

        return $resolved;
    }

    private static function preflightLayout(array $layout): void {
        $missing = [];

        foreach (['remote_path', 'public_dir', 'framework_dir', 'app_dir', 'runtime_dir'] as $key) {
            if ($layout[$key] === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            self::fail('deploy.ini: ' . implode(', ', $missing) . ' are empty — edit WorkDir/ConfigDev/deploy.ini.');
        }
    }

    private static function step(string $num, string $label): void {
        echo "\033[1;36m[{$num}]\033[0m {$label}" . PHP_EOL;
    }

    private static function fail(string $msg): void {
        echo "\033[31mError:\033[0m {$msg}" . PHP_EOL;

        exit(1);
    }

    private static function help(): void {
        echo <<<HELP

  \033[1mphp garnet deploy:full [flags]\033[0m

  \033[1mWHAT IT DOES\033[0m
  ────────────────────────────────────────────────────────────────────────
  One command, start to live: build fresh (same code path as
  `php garnet bundle`), push to an EXISTING host — maintenance mode ON,
  public dir merged (never wiped — see below), framework/app dirs
  uploaded to a fresh staging name and swapped into place with a
  near-instant rename (never rm -rf'd first), runtime dir's `garnet`
  dispatcher + _shared_index.php synced individually (WorkDir/ — real
  Config/*.ini, DB backups, log journals — never touched), boot check,
  then \033[36mphp garnet deploy\033[0m (maintenance → backup → migrate
  → cache → off) triggered remotely over SSH as the final step —
  maintenance stays ON the whole time until that command (or, with
  --skip-migrate, this one) turns it back off.

  Exists so a full release can never be left half-done by a forgotten
  intermediate step — build, ship, and migrate happen inside one call.
  Migrations reuse the existing `deploy` command rather than
  reimplementing its safety guarantees here (maintenance stays ON on any
  failure, backup-before-migrate) — same command whether triggered
  remotely by this one or run by hand later.

  \033[1mFLAGS\033[0m
  ────────────────────────────────────────────────────────────────────────
    --skip-build       Skip the rspack production build (assumes
                        Public/assets/ is already built fresh).
    --no-boot-check    Skip the post-push `php garnet noop` sanity check.
    --skip-migrate     Ship the code but don't trigger the remote
                        `php garnet deploy` — review live before migrating,
                        or there's no pending schema change this release.
    --skip-backup      Forwarded to the remote `php garnet deploy` as its
                        own --skip-backup (discouraged — see
                        `php garnet deploy`'s own docs).

  Connection (ssh.ini) and layout (deploy.ini) are read exactly as
  `deploy:diff` / `bundle` already do — see `php garnet deploy:diff --help`
  for the config file details.

  --help / -h / help     this message
HELP;
        echo PHP_EOL;
    }
}
