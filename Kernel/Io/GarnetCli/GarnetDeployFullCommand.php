<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use Throwable;

/**
 * One command, safe by construction, that takes a release all the way to
 * live: build the app fresh (via GarnetBundleCommand's own build+package
 * logic — the same code path `php garnet bundle` uses, already fixed and
 * verified for both the legacy monorepo and standalone-app layouts), push
 * public/framework/app to an EXISTING host (never touching the runtime
 * dir's WorkDir/ — real Config/*.ini, real DB backups, real logs, the one
 * thing on the host that isn't safe to blindly overwrite), then trigger
 * the existing, already-safe `php garnet deploy` (maintenance -> backup ->
 * migrate -> cache -> off) remotely over SSH as the final step.
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
 * Migrations are triggered via the EXISTING `deploy` command rather than
 * reimplemented here, specifically so its safety guarantees (maintenance
 * stays ON on any failure, backup-before-migrate) are reused, not
 * duplicated or risked drifting. --skip-migrate opts out for whoever
 * wants to review the shipped code live before touching the schema.
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

        $layout = self::resolveLayout();
        self::preflightLayout($layout);

        $ssh = SshClient::fromIniConfig();
        $ssh->validate();

        // 1. Build — reuses GarnetBundleCommand's own build+package logic
        // unmodified (no phar/zip; --keep-dir so the tree survives for us
        // to read). Same code path `php garnet bundle` uses on its own.
        echo "\033[1m=== Garnet Deploy (full): {$appName} ===\033[0m" . PHP_EOL . PHP_EOL;
        self::step('1/5', 'Building fresh (php garnet bundle --keep-dir --no-phar)');
        $bundleArgs = ['--keep-dir', '--no-phar'];

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

        // 2. Ship the three STATELESS dirs — wholesale wipe + re-upload is
        // safe for all three: no WorkDir, no Config, no persisted state of
        // any kind lives in public/framework-dir/app-dir by design.
        self::step('2/5', 'Shipping public / framework / app (wipe + re-upload)');
        self::shipDir($ssh, $distPublic, "{$remoteRoot}/{$layout['public_dir']}", 'public');
        self::shipDir($ssh, $distFw, "{$remoteRoot}/{$layout['framework_dir']}", 'framework');
        self::shipDir($ssh, $distAppDir, "{$remoteRoot}/{$layout['app_dir']}", 'app');
        echo PHP_EOL;

        // 3. Runtime dir: ONLY the dispatcher + shared bootstrap — NEVER
        // the whole tree. The live runtime-dir/WorkDir carries real
        // Config/*.ini (DB creds, SSH creds), real DB backups, real log
        // journals — none of that exists in a fresh bundle build at all,
        // so a wholesale overwrite would either wipe it (rsync-style) or
        // at minimum risk clobbering it. Only these two files ever need to
        // change release-to-release; both are already idempotent/versioned
        // by content, and the boot check below verifies the swap didn't
        // break anything before we call it done.
        self::step('3/5', 'Syncing runtime dispatcher (garnet + _shared_index.php only — WorkDir left untouched)');
        $remoteRuntime = "{$remoteRoot}/{$layout['runtime_dir']}";

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

        // 4. Boot check — the same safety net deploy:diff already relies
        // on: confirm the host can actually start the app after the push,
        // rather than finding out from a live 500.
        if ($noBootCheck) {
            self::step('4/5', 'Boot check SKIPPED (--no-boot-check)');
            echo PHP_EOL;
        } else {
            self::step('4/5', 'Boot check (php garnet noop on host)');
            $cmd = 'cd ' . escapeshellarg($remoteRuntime) . ' && php garnet noop 2>&1';
            $res = $ssh->run($cmd, ['stream' => false]);

            if ($res->exitCode !== 0) {
                echo "\033[31m  [FAIL] the app does NOT boot after this push:\033[0m" . PHP_EOL;

                foreach (array_filter(explode("\n", trim($res->stdout . "\n" . $res->stderr))) as $line) {
                    echo "    \033[90m{$line}\033[0m" . PHP_EOL;
                }
                self::fail('Boot check failed — investigate before trusting this release.');
            }
            echo '  ' . "\033[32m[OK]\033[0m app boots cleanly on the host" . PHP_EOL . PHP_EOL;
        }

        // 5. Migrations — triggers the EXISTING, already-safe `php garnet
        // deploy` (maintenance → backup → migrate → cache → off) remotely
        // over SSH, so this one command really does take a release all the
        // way to "live," not just "files are there, remember to migrate."
        // Kept as a genuinely separate underlying command (not duplicated
        // logic here) because it must run FROM the runtime dir's own
        // context on the host (that's where WorkDir/Config/db.ini
        // resolves) — same command whether triggered remotely here or run
        // by hand later, so its own safety guarantees (maintenance stays
        // ON on any failure) are never bypassed or reimplemented.
        if ($skipMigrate) {
            echo "\033[33m  Migrations SKIPPED (--skip-migrate)\033[0m — run "
                . "\033[36mphp garnet deploy\033[0m from inside {$layout['runtime_dir']}/ on the host when ready."
                . PHP_EOL;
        } else {
            self::step('5/5', 'Migrating (php garnet deploy on host: maintenance → backup → migrate → cache → off)');
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

        echo "\033[32m=== Deploy complete — {$appName} is live at {$layout['remote_path']} ===\033[0m" . PHP_EOL;
    }

    /**
     * Remove the remote target dir entirely, then re-upload the fresh local
     * tree wholesale. Safe here specifically because all three callers
     * (public/framework/app) are stateless by design — never call this on
     * the runtime dir.
     *
     * `scp -r <local> <remote>` does NOT copy $local's CONTENTS into
     * $remote — it copies $local itself AS A SUBDIRECTORY of $remote (same
     * semantics as `cp -r`). Found this the hard way testing against a
     * scratch remote dir first: files landed at
     * "$remoteDir/$basename($localDir)/…" instead of directly under
     * $remoteDir. The fix relies on bundle always naming its sibling dirs
     * (public/framework-dir/app-dir) identically to this same deploy.ini's
     * own configured names — i.e. basename($localDir) === basename($remoteDir)
     * always holds — so scp'ing into dirname($remoteDir) recreates exactly
     * $remoteDir with the right contents, no merge/overwrite ambiguity
     * since the old one was just removed outright.
     */
    private static function shipDir(SshClient $ssh, string $localDir, string $remoteDir, string $label): void {
        $remoteDirNorm = rtrim(str_replace('\\', '/', $remoteDir), '/');
        $remoteParent = dirname($remoteDirNorm);

        $wipe = $ssh->run('rm -rf ' . escapeshellarg($remoteDirNorm), ['stream' => false]);

        if (!$wipe->ok()) {
            self::fail("Could not clear the remote {$label} dir ({$remoteDirNorm}): {$wipe->stderr}");
        }

        // SshClient::put() does NOT auto-detect a directory upload —
        // 'recursive' must be requested explicitly (only GarnetSshCommand,
        // the ssh:put CLI wrapper, does that is_dir() check, and this
        // command calls SshClient directly, bypassing it). Missing this
        // failed in testing too: the wipe above succeeds, then scp errors
        // "not a regular file" — left the remote dir simply missing, not
        // corrupted, but still a real bug caught only by testing this
        // against a scratch remote dir before ever pointing it at
        // framework/app/public.
        $put = $ssh->put($localDir, $remoteParent, ['stream' => false, 'recursive' => true]);

        if (!$put->ok()) {
            self::fail("Upload of {$label} to {$remoteDirNorm} failed (exit {$put->exitCode}).");
        }
        echo "  -> {$remoteDirNorm}" . PHP_EOL;
    }

    /** Same path GarnetBundleCommand::run() writes its output to. */
    private static function distAppPath(string $appName): string {
        return GARNET_ROOT . DS . 'dist' . DS . $appName;
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
  `php garnet bundle`), push to an EXISTING host — public/framework/app
  dirs wiped and re-uploaded wholesale (all three are stateless by
  design, safe to fully replace), runtime dir's `garnet` dispatcher +
  _shared_index.php synced individually (WorkDir/ — real Config/*.ini,
  DB backups, log journals — never touched), boot check, then
  \033[36mphp garnet deploy\033[0m (maintenance → backup → migrate →
  cache → off) triggered remotely over SSH as the final step.

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
