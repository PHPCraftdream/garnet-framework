<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\Remote;

use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
use PHPCraftdream\Garnet\Kernel\Io\Services\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Services\Ssh\SshClient;

/**
 * `php garnet test:remote` — orchestrate a UI-test run against an external
 * (prod / staging) box, driven from the LOCAL machine.
 *
 * Lifecycle (token lives only for the duration of the run):
 *   1. generate a one-time secret token
 *   2. SSH → `php garnet test:provision` (plants .allow_tests, builds the
 *      isolated test_worker_0 scope: migrate + seed + role accounts)
 *   3. run Playwright LOCALLY against the remote URL, every request carrying
 *      `run-test-garnet-team: <token>` so the server flips to test_worker_0
 *   4. SSH → `php garnet test:teardown` (drops the scope + deletes the token)
 *
 * Step 4 runs even if Playwright fails, so a crashed run never leaves the
 * token (and thus the open gate) behind. Pass `--keep` to skip teardown for
 * debugging — then clean up by hand with `php garnet ssh "php garnet test:teardown" --cd-remote`.
 *
 * The token of a `--keep` run is remembered locally (WorkDir/, gitignored) so
 * `--no-provision` can reuse it. Without that, `--no-provision` sent a FRESH
 * token that was never planted on the box: the server then served the run as
 * ordinary traffic, `.test` auto-login never completed, and the whole thing
 * died in globalSetup on a login timeout — a failure that looks like anything
 * but a token mismatch. Teardown forgets the file again; `--token=<secret>`
 * overrides both.
 *
 * SSH connection params come from WorkDir/Config[Dev]/ssh.ini (same as
 * `php garnet ssh`). Remote commands run in `remote_path` (the runtime dir
 * that holds the deployed `garnet`).
 *
 * Usage:
 *   php garnet test:remote --base-url=https://example.com [playwright args...]
 *   php garnet test:remote --base-url=https://example.com --project=admin-tests
 *   php garnet test:remote --base-url=https://example.com --no-provision --keep
 */
final class GarnetTestRemoteCommand {
    public static function run(array $args): void {
        $flags = self::parseFlags($args);

        if ($flags['help']) {
            self::help();

            exit(0);
        }

        $baseUrl = $flags['base_url'];

        if ($baseUrl === '') {
            fwrite(STDERR, "Error: --base-url=<https://host> is required.\n");
            self::help();

            exit(1);
        }

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();

        // Remote commands must run from the runtime dir (where the deployed
        // `garnet` lives), NOT remote_path (the docroot parent). cd_remote
        // would land in the latter, so resolve <remote_path>/<runtime_dir>
        // from deploy.ini and pass it as an explicit cwd.
        $remoteDir = self::resolveRemoteRuntimeDir();

        $tokenFile = self::tokenFile();
        $chosen = self::chooseToken(
            $flags['no_provision'],
            $flags['token'],
            self::readRememberedToken($tokenFile),
        );

        if ($chosen['error'] !== '') {
            fwrite(STDERR, $chosen['error'] . "\n");

            exit(1);
        }
        $token = $chosen['token'];

        // Guards against a double teardown: once from the interrupt handler,
        // once from the normal finally block below.
        $torndown = false;
        $teardown = static function () use ($client, $remoteDir, $tokenFile, &$torndown): void {
            if ($torndown) {
                return;
            }
            $torndown = true;
            echo "\n[teardown] dropping remote test scope…\n";
            $res = $client->run('php garnet test:teardown', ['cwd' => $remoteDir, 'stream' => true]);

            if (!$res->ok()) {
                fwrite(STDERR, "Warning: remote teardown exited {$res->exitCode} — clean up manually.\n");
                self::hintIfCommandMissing();
            }
            // The scope is gone, so the remembered token is worthless — and
            // keeping it would let a later --no-provision run start against a
            // box that has no scope at all.
            self::forgetToken($tokenFile);
        };

        // A plain `finally` around runPlaywright() is NOT enough: Ctrl-C /
        // a killed terminal delivers SIGINT/CTRL_C straight to this process,
        // which by default terminates immediately without ever reaching the
        // finally block — leaving the token + test_worker_0 scope open on a
        // LIVE box indefinitely. Trap the interrupt on both platforms and
        // run the same teardown before exiting.
        self::installInterruptHandler($teardown);

        // 1. Provision (unless told to reuse an already-provisioned scope).
        if (!$flags['no_provision']) {
            echo "[provision] building remote test_worker_0 scope…\n";
            $res = $client->run('php garnet test:provision', [
                'cwd' => $remoteDir,
                'env' => ['GARNET_TEST_TOKEN=' . $token],
                'stream' => true,
            ]);

            if (!$res->ok()) {
                fwrite(STDERR, "Error: remote provision failed (exit {$res->exitCode}). Aborting.\n");
                self::hintIfCommandMissing();
                // Provision may have planted the token before failing — tear down.
                $teardown();

                exit(1);
            }
            // Remember it only now: before this point the box has no gate,
            // and a remembered token that was never planted is exactly the
            // trap this file used to lay.
            self::rememberToken($tokenFile, $token);
        } else {
            fwrite(STDERR, "Note: --no-provision set; reusing the token remembered from the provisioning run.\n");
        }

        // 2. Run Playwright locally against the remote box.
        $exitCode = 1;

        try {
            $exitCode = self::runPlaywright($baseUrl, $token, $flags['passthrough']);
        } finally {
            // 3. Always tear down (unless --keep), even on a thrown error.
            if (!$flags['keep']) {
                $teardown();
            } else {
                fwrite(STDERR, "Note: --keep set; remote scope + token left in place.\n");
            }
        }

        echo $exitCode === 0
            ? "\n=== Remote UI-test run PASSED ===\n"
            : "\n=== Remote UI-test run FAILED (exit {$exitCode}) ===\n";

        exit($exitCode);
    }

    /**
     * Trap Ctrl-C / a terminated shell so the remote scope is never left
     * open indefinitely. Unix: pcntl signals (SIGINT/SIGTERM), requires
     * async signal dispatch since this process never calls `pcntl_signal_dispatch`
     * itself. Windows: `sapi_windows_set_ctrl_handler` (no pcntl there).
     * Either mechanism may be unavailable (missing extension / non-CLI
     * SAPI) — silently skip rather than fail the whole command over a
     * best-effort safety net.
     *
     * @param callable(): void $teardown
     */
    private static function installInterruptHandler(callable $teardown): void {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $handler = static function (int $signo) use ($teardown): void {
                fwrite(STDERR, "\nInterrupted (signal {$signo}) — tearing down before exit…\n");
                $teardown();

                exit(130);
            };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);

            return;
        }

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(static function (int $event) use ($teardown): void {
                fwrite(STDERR, "\nInterrupted (Ctrl-C/Break) — tearing down before exit…\n");
                $teardown();

                exit(130);
            }, true);
        }
    }

    /**
     * `stream: true` pipes the remote command's stdout/stderr straight to
     * this process's own STDOUT/STDERR (see SshClient::execute()), so the
     * real SSH failure is already visible above this line — SshResult
     * carries no captured text to grep for "command not found" here. All
     * this can safely add is a pointer at the most likely cause: the app
     * has no `test:provision` / `test:teardown` commands registered (every
     * app scaffolded from Templates/Application/ has them by default via
     * Application::defineMigrationClass() — an older app predating that
     * scaffold change would need them added, see
     * Templates/Application/Common/Commands/CMDTestProvision.php).
     */
    private static function hintIfCommandMissing(): void {
        fwrite(STDERR, 'Hint: if the output above mentions an unknown/missing command, the '
            . 'remote app has no `test:provision` / `test:teardown` commands registered. Every app '
            . 'scaffolded from the current app template gets them by default — see '
            . 'Templates/Application/Common/Commands/CMDTestProvision.php in garnet-framework for what '
            . "to add (registered via CommandClasses::set() in your app's defineMigrationClass()).\n");
    }

    /**
     * Which token this run must carry.
     *
     * Three sources, in order: an explicit `--token=`, a freshly minted
     * secret (a provisioning run plants it), or the one remembered from the
     * provisioning run (`--no-provision`). The last case is the reason this
     * method exists: a fresh token there would never have been planted on the
     * box, and the run would fail far away from the cause — in globalSetup,
     * on a login timeout, because the server treats an unknown token as
     * ordinary traffic and never auto-completes the `.test` login.
     *
     * @return array{token: string, error: string} error non-empty ⇒ abort
     */
    private static function chooseToken(bool $noProvision, string $explicit, string $remembered): array {
        if ($explicit !== '') {
            return self::isValidToken($explicit)
                ? ['token' => $explicit, 'error' => '']
                : ['token' => '', 'error' => 'Error: --token must be 16-128 chars of [A-Za-z0-9_-].'];
        }

        if (!$noProvision) {
            // 32 hex chars — matches CMDTestProvision's [A-Za-z0-9_-]{16,128} gate.
            return ['token' => bin2hex(random_bytes(16)), 'error' => ''];
        }

        if ($remembered === '') {
            return ['token' => '', 'error' => 'Error: --no-provision has no token to reuse. The token is '
                . 'remembered only by a run that provisioned the scope and kept it (--keep); a teardown '
                . "forgets it.\n       Either drop --no-provision, or pass --token=<the secret that is "
                . 'planted on the box>.'];
        }

        if (!self::isValidToken($remembered)) {
            return ['token' => '', 'error' => 'Error: the remembered token is malformed — delete '
                . 'WorkDir/test-remote-token and provision again.'];
        }

        return ['token' => $remembered, 'error' => ''];
    }

    /** Same charset gate the remote `test:provision` applies to the token. */
    private static function isValidToken(string $token): bool {
        return preg_match('/^[A-Za-z0-9_-]{16,128}$/', $token) === 1;
    }

    /**
     * Where the provisioning token is remembered between invocations: the
     * app's WorkDir, which is runtime-only and gitignored in every app
     * scaffolded from the template. It holds a live gate into the remote test
     * scope, so it is written 0600 and deleted on teardown.
     */
    private static function tokenFile(): string {
        $appDir = GarnetEnv::getAppDir(GarnetEnv::requireAppName());

        return $appDir . DS . 'WorkDir' . DS . 'test-remote-token';
    }

    private static function readRememberedToken(string $file): string {
        if (!is_file($file)) {
            return '';
        }

        return trim((string)file_get_contents($file));
    }

    private static function rememberToken(string $file, string $token): void {
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            fwrite(STDERR, "Warning: cannot create {$dir}; --no-provision will have no token to reuse.\n");

            return;
        }

        if (file_put_contents($file, $token . "\n") === false) {
            fwrite(STDERR, "Warning: cannot write {$file}; --no-provision will have no token to reuse.\n");

            return;
        }
        // Best-effort: no-op on filesystems without POSIX modes (Windows).
        @chmod($file, 0o600);
    }

    private static function forgetToken(string $file): void {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Spawn the local Playwright run. PW_PROD=1 switches the harness into
     * remote mode (token header on every context, no local DB isolation,
     * SQL routed back over SSH). Returns the Playwright exit code.
     *
     * @param list<string> $passthrough extra args forwarded to `playwright test`
     */
    private static function runPlaywright(string $baseUrl, string $token, array $passthrough): int {
        $testsDir = self::resolveTestsDir();

        if (!is_dir($testsDir)) {
            fwrite(STDERR, "Error: tests dir not found at {$testsDir}.\n");

            return 1;
        }

        // Launch Playwright via `node <cli.js>` rather than `npx playwright`:
        // on Windows `npx` is a `.cmd` shim that proc_open's bypass_shell can't
        // CreateProcess (error 2), and the no-bypass fallback mis-resolves the
        // npm prefix. The CLI entrypoint is plain JS — running it through the
        // `node` binary is shell-agnostic and works identically on every OS.
        $cliJs = self::resolvePlaywrightCli($testsDir);

        if ($cliJs === null) {
            fwrite(STDERR, "Error: @playwright/test CLI not found under node_modules.\n");

            return 1;
        }

        $argv = array_merge(
            ['node', $cliJs, 'test', '--config=playwright.prod.config.ts'],
            $passthrough,
        );

        $env = getenv();
        $env['PW_PROD'] = '1';
        $env['BASE_URL'] = $baseUrl;
        $env['RUN_TEST_TOKEN'] = $token;
        // Single scope, single worker — shared hosting must not be hammered.
        $env['PW_WORKERS'] = '1';

        echo "[playwright] {$baseUrl}  (1 worker, token-gated)\n";

        $proc = proc_open(
            $argv,
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $testsDir,
            $env,
            ['bypass_shell' => true],
        );

        if ($proc === false) {
            // Fallback without proc_open: build a single shell line. `node` and
            // the absolute cli.js path resolve the same way under any shell.
            putenv('PW_PROD=1');
            putenv('BASE_URL=' . $baseUrl);
            putenv('RUN_TEST_TOKEN=' . $token);
            putenv('PW_WORKERS=1');
            $cmd = 'cd ' . escapeshellarg($testsDir) . ' && '
                . implode(' ', array_map('escapeshellarg', $argv));
            passthru($cmd, $code);

            return (int)$code;
        }

        return proc_close($proc);
    }

    /**
     * Resolve the local Playwright specs dir.
     *
     * App-mode (composer-vendored framework): GARNET_ROOT is the
     * vendored package dir under vendor/ — it never held app tests and, per
     * .gitattributes' `/tests/ export-ignore`, the framework's own tests/
     * doesn't even ship in the Packagist dist archive. The app's real specs
     * live in `<appDir>/Tests` (capital T — see Templates/Application/Tests
     * and Apps/MyApp/Tests). GarnetRunner::$appDir is the app root in
     * app-mode and equals $frameworkDir in legacy/framework-repo dev
     * checkout, where the framework's own lowercase tests/ (used only for
     * the framework's own Playwright specs) is still meaningful.
     */
    private static function resolveTestsDir(): string {
        $isAppMode = GarnetRunner::$appDir !== '' && GarnetRunner::$appDir !== GarnetRunner::$frameworkDir;

        return $isAppMode
            ? GarnetRunner::$appDir . DIRECTORY_SEPARATOR . 'Tests'
            : GARNET_ROOT . DIRECTORY_SEPARATOR . 'tests';
    }

    /**
     * Resolve the @playwright/test CLI entrypoint (plain JS). Prefer the
     * tests-local install (where the prod config lives), fall back to the
     * repo-root node_modules.
     */
    private static function resolvePlaywrightCli(string $testsDir): ?string {
        $candidates = [
            $testsDir . DS . 'node_modules' . DS . '@playwright' . DS . 'test' . DS . 'cli.js',
            GARNET_ROOT . DS . 'node_modules' . DS . '@playwright' . DS . 'test' . DS . 'cli.js',
        ];

        foreach ($candidates as $cli) {
            if (is_file($cli)) {
                return $cli;
            }
        }

        return null;
    }

    /**
     * @return array{help: bool, keep: bool, no_provision: bool, base_url: string, token: string, passthrough: list<string>}
     */
    private static function parseFlags(array $args): array {
        $out = [
            'help' => false,
            'keep' => false,
            'no_provision' => false,
            'base_url' => '',
            'token' => '',
            'passthrough' => [],
        ];

        foreach ($args as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $out['help'] = true;

                continue;
            }

            if ($arg === '--keep') {
                $out['keep'] = true;

                continue;
            }

            if ($arg === '--no-provision') {
                $out['no_provision'] = true;

                continue;
            }

            if (str_starts_with($arg, '--base-url=')) {
                $out['base_url'] = substr($arg, 11);

                continue;
            }

            if (str_starts_with($arg, '--token=')) {
                $out['token'] = substr($arg, 8);

                continue;
            }
            // Everything else is forwarded verbatim to `playwright test`.
            $out['passthrough'][] = $arg;
        }

        return $out;
    }

    /**
     * Absolute remote dir the deployed `garnet` lives in:
     * `<remote_path>/<runtime_dir>` from deploy.ini. Remote `php garnet …`
     * invocations cd here first.
     */
    private static function resolveRemoteRuntimeDir(): string {
        $deploy = IniConfig::deploy();
        $remotePath = rtrim($deploy->paramString('remote_path', ''), '/');
        $runtimeDir = trim($deploy->paramString('runtime_dir', ''), '/');

        if ($remotePath === '' || $runtimeDir === '') {
            fwrite(STDERR, "Error: deploy.ini must define remote_path and runtime_dir.\n");

            exit(1);
        }

        return $remotePath . '/' . $runtimeDir;
    }

    private static function bootApp(): void {
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            fwrite(STDERR, "Error: app has no run_cmd.php at {$runCmd}\n");

            exit(1);
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();
    }

    private static function help(): void {
        echo <<<HELP

  Usage: php garnet test:remote --base-url=<url> [playwright args...]

  Orchestrates a UI-test run against an external box: provisions an isolated
  test_worker_0 scope over SSH, runs Playwright locally against it, then tears
  the scope down. SSH params come from ssh.ini (same as `php garnet ssh`).

  Flags:
    --base-url=URL    Remote site URL (required), e.g. https://example.com
    --no-provision    Skip provision and reuse the scope + token a previous
                      --keep run left in place (its token is remembered in
                      WorkDir/test-remote-token). Refuses when there is no
                      token to reuse — an unplanted token would only surface
                      much later, as a login timeout in globalSetup.
    --token=SECRET    Use this token instead of minting/reusing one. With
                      provisioning it is what gets planted; with
                      --no-provision it must match what is already on the box.
    --keep            Skip teardown (leave scope + token in place for debugging)
    --help, -h        Show this help

  Any other arguments are forwarded to `playwright test`, e.g.:
    php garnet test:remote --base-url=https://example.com --project=admin-tests
    php garnet test:remote --base-url=https://example.com Apps/MyApp/Tests/x.spec.ts

HELP;
    }
}
