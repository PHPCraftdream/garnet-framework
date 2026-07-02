<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

/**
 * One-shot framework installer — replaces the manual "clone, then run these
 * eight commands" dance with a single `php bin/garnet setup` (or, after the
 * first `composer install`, the `composer setup` script alias).
 *
 * It performs every step this project used to require by hand:
 *   1. composer install   — PHP deps + vendor/bin tooling (phpstan, cs-fixer)
 *   2. npm install         — FrontBuilder node deps (rspack, tsgo, oxlint)
 *   3. node_modules junction at the framework root → FrontBuilder/node_modules
 *      so tsgo / Bundle-Front resolve packages without per-package paths.
 *
 * Every step is idempotent: re-running on an already-set-up tree is a no-op
 * (or a cheap "already up to date"). Steps can be skipped individually with
 * --skip-composer / --skip-npm / --skip-junction, and the whole node half is
 * suppressed when GARNET_SKIP_NODE_SETUP=1 (used by the composer post-install
 * hook in CI / node-less environments).
 */
class GarnetSetupCommand {
    public static function run(array $args): void {
        $frameworkDir = GarnetRunner::$frameworkDir;
        $appDir = GarnetRunner::$appDir;
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        $skipComposer = in_array('--skip-composer', $args, true);
        $skipNpm = in_array('--skip-npm', $args, true);
        $skipJunction = in_array('--skip-junction', $args, true);
        $skipPlaywright = in_array('--skip-playwright', $args, true);
        // --soft downgrades step failures to warnings (exit 0). The composer
        // post-install hook uses it so a node-less box never breaks `composer
        // install`.
        $soft = in_array('--soft', $args, true);
        // Node-less callers (a composer post-install hook on a CI box without
        // npm) set this so the PHP half still runs but the node half is quiet.
        $nodeSuppressed = getenv('GARNET_SKIP_NODE_SETUP') === '1';

        // No npm on PATH → silently degrade the node half instead of failing.
        if (!$skipNpm && !$nodeSuppressed && !self::hasNpm($isWindows)) {
            echo "  \033[33mnote:\033[0m npm not found on PATH — skipping node setup." . PHP_EOL;
            echo "        install Node.js, then re-run \033[36mphp garnet setup\033[0m." . PHP_EOL . PHP_EOL;
            $skipNpm = true;
            $skipJunction = true;
            $skipPlaywright = true;
        }

        // App-mode vs framework-mode: when run from inside a scaffolded app the
        // app dir differs from the framework package; setup then targets the
        // app (composer + its node deps + playwright) rather than the framework
        // (composer + FrontBuilder + junction).
        $isAppMode = $appDir !== '' && self::norm((string)realpath($appDir)) !== self::norm((string)realpath($frameworkDir));

        $opts = compact('skipComposer', 'skipNpm', 'skipJunction', 'skipPlaywright', 'soft', 'nodeSuppressed', 'isWindows');

        if ($isAppMode) {
            self::setupApp($appDir, $frameworkDir, $opts);
        } else {
            self::setupFramework($frameworkDir, $opts);
        }
    }

    /** Framework install: composer + FrontBuilder npm + node_modules junction. */
    private static function setupFramework(string $frameworkDir, array $o): void {
        $doComposer = !$o['skipComposer'];
        $doNpm = !$o['skipNpm'] && !$o['nodeSuppressed'];
        $doJunction = !$o['skipJunction'] && !$o['nodeSuppressed'];

        // Nothing to actually do (e.g. the composer post-install hook firing
        // inside a parent `garnet setup` that already owns the node half) —
        // stay silent rather than printing a nested, confusing banner.
        if (!$doComposer && !$doNpm && !$doJunction) {
            exit(0);
        }

        echo "\033[1m=== Garnet setup (framework) ===\033[0m" . PHP_EOL;
        echo "  framework: {$frameworkDir}" . PHP_EOL . PHP_EOL;

        if ($doComposer) {
            // Suppress the composer post-install hook's node half — this very
            // command does npm + junction below, so let the child stay quiet
            // instead of running them twice.
            self::step('composer install', static function () use ($frameworkDir): int {
                putenv('GARNET_SKIP_NODE_SETUP=1');
                $code = self::runIn($frameworkDir, 'composer install --no-interaction');
                putenv('GARNET_SKIP_NODE_SETUP');

                return $code;
            }, $o['soft']);
        } else {
            self::skipped('composer install');
        }

        if ($doNpm) {
            $frontDir = $frameworkDir . DS . 'FrontBuilder';
            self::step('npm install (FrontBuilder)', static fn (): int => self::runIn($frontDir, 'npm install'), $o['soft']);
        } else {
            self::skipped('npm install' . ($o['nodeSuppressed'] ? ' (GARNET_SKIP_NODE_SETUP=1)' : ''));
        }

        if ($doJunction) {
            self::linkNodeModules($frameworkDir, $o['isWindows']);
        } else {
            self::skipped('node_modules junction' . ($o['nodeSuppressed'] ? ' (GARNET_SKIP_NODE_SETUP=1)' : ''));
        }

        echo PHP_EOL . "\033[32m  [OK] Framework ready.\033[0m" . PHP_EOL;
        echo "  Next: scaffold an app with \033[36mphp bin/garnet app:create <Name>\033[0m" . PHP_EOL;

        exit(0);
    }

    /**
     * App install: composer (vendor + qa tooling) + root npm (@types/web for
     * IDE/tsgo) + Tests npm (playwright) + playwright browser install. The app
     * builds through the framework's FrontBuilder, so it needs no junction of
     * its own. Every node step is best-effort under --soft.
     */
    private static function setupApp(string $appDir, string $frameworkDir, array $o): void {
        $doComposer = !$o['skipComposer'];
        $doNpm = !$o['skipNpm'] && !$o['nodeSuppressed'];
        $doPlaywright = !$o['skipPlaywright'] && !$o['nodeSuppressed'];

        if (!$doComposer && !$doNpm && !$doPlaywright) {
            exit(0);
        }

        echo "\033[1m=== Garnet setup (app) ===\033[0m" . PHP_EOL;
        echo "  app:       {$appDir}" . PHP_EOL;
        echo "  framework: {$frameworkDir}" . PHP_EOL . PHP_EOL;

        if ($doComposer) {
            self::step('composer install', static function () use ($appDir): int {
                putenv('GARNET_SKIP_NODE_SETUP=1');
                $code = self::runIn($appDir, 'composer install --no-interaction');
                putenv('GARNET_SKIP_NODE_SETUP');

                return $code;
            }, $o['soft']);
        } else {
            self::skipped('composer install');
        }

        if ($doNpm) {
            self::step('npm install (app)', static fn (): int => self::runIn($appDir, 'npm install'), true);

            $testsDir = $appDir . DS . 'Tests';

            if (is_file($testsDir . DS . 'package.json')) {
                self::step('npm install (Tests)', static fn (): int => self::runIn($testsDir, 'npm install'), true);
            } else {
                self::skipped('npm install (Tests) — no Tests/package.json');
            }
        } else {
            self::skipped('npm install' . ($o['nodeSuppressed'] ? ' (GARNET_SKIP_NODE_SETUP=1)' : ''));
        }

        if ($doPlaywright) {
            $testsDir = $appDir . DS . 'Tests';

            if (is_dir($testsDir . DS . 'node_modules')) {
                // Browsers cache in a shared per-user dir, so this is a one-time
                // download across all apps on the machine. Best-effort.
                self::step('playwright install (browsers)', static fn (): int => self::runIn($testsDir, 'npx playwright install'), true);
            } else {
                self::skipped('playwright install — run npm install (Tests) first');
            }
        } else {
            self::skipped('playwright install' . ($o['nodeSuppressed'] ? ' (GARNET_SKIP_NODE_SETUP=1)' : ''));
        }

        echo PHP_EOL . "\033[32m  [OK] App ready.\033[0m" . PHP_EOL;
        echo "  Next: \033[36mphp garnet build\033[0m then \033[36mphp garnet serve\033[0m" . PHP_EOL;

        exit(0);
    }

    /** Forward-slash, trailing-slash-stripped path for comparison. */
    private static function norm(string $p): string {
        return rtrim(str_replace('\\', '/', $p), '/');
    }

    /**
     * Create the root-level node_modules junction/symlink pointing at
     * FrontBuilder/node_modules. Idempotent: a correct existing link is left
     * alone, a stale/broken one is replaced, a real directory is refused.
     */
    private static function linkNodeModules(string $frameworkDir, bool $isWindows): void {
        $link = $frameworkDir . DS . 'node_modules';
        $target = $frameworkDir . DS . 'FrontBuilder' . DS . 'node_modules';

        echo '  • node_modules junction ... ';

        if (!is_dir($target)) {
            echo "\033[33mskipped\033[0m (FrontBuilder/node_modules missing — run npm install first)" . PHP_EOL;

            return;
        }

        $norm = static fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/');
        $targetReal = (string)realpath($target);

        // Resolve whatever already sits at $link FIRST — junctions, dir
        // symlinks and real dirs all resolve via realpath, sidestepping the
        // unreliable is_link()/is_dir() semantics for Windows reparse points.
        $linkReal = realpath($link);

        if ($linkReal !== false && $norm($linkReal) === $norm($targetReal)) {
            echo "\033[32malready linked\033[0m" . PHP_EOL;

            return;
        }

        // Something else lives there. A reparse point (junction/symlink)
        // resolves elsewhere than its own path — drop & recreate it. A genuine
        // directory resolves to itself — refuse to clobber it.
        $entryExists = $linkReal !== false || is_link($link) || @lstat($link) !== false;

        if ($entryExists) {
            $resolvesElsewhere = $linkReal !== false && $norm($linkReal) !== $norm($link);

            if (!is_link($link) && !$resolvesElsewhere) {
                echo "\033[31mskipped\033[0m (a real node_modules dir exists at the framework root — remove it to enable the junction)" . PHP_EOL;

                return;
            }

            // Drop the stale reparse point. On Windows a junction is removed
            // with rmdir, not unlink; try both.
            if (!@unlink($link)) {
                @rmdir($link);
            }
        }

        $ok = $isWindows
            ? self::runIn($frameworkDir, 'cmd /c mklink /J "' . $link . '" "' . $target . '"') === 0
            : @symlink($target, $link);

        echo $ok
            ? "\033[32mlinked\033[0m" . PHP_EOL
            : "\033[31mfailed\033[0m" . PHP_EOL;
    }

    /** Run a shell command in $dir, streaming output; returns the exit code. */
    private static function runIn(string $dir, string $cmd): int {
        $cwd = getcwd();
        chdir($dir);
        passthru($cmd, $code);
        chdir($cwd !== false ? $cwd : $dir);

        return $code;
    }

    /** @param callable():int $fn */
    private static function step(string $label, callable $fn, bool $soft = false): void {
        echo "\033[1m  → {$label}\033[0m" . PHP_EOL;
        $code = $fn();

        if ($code !== 0) {
            if ($soft) {
                echo "\033[33m  [warn] '{$label}' failed (exit {$code}) — continuing (soft mode).\033[0m" . PHP_EOL . PHP_EOL;

                return;
            }

            echo "\033[31m  [ERROR] '{$label}' failed (exit {$code}).\033[0m" . PHP_EOL;

            exit($code);
        }

        echo PHP_EOL;
    }

    /** Is the npm executable resolvable on PATH? */
    private static function hasNpm(bool $isWindows): bool {
        $probe = $isWindows ? 'where npm' : 'command -v npm';
        exec($probe . ' 2>' . ($isWindows ? 'NUL' : '/dev/null'), $out, $code);

        return $code === 0;
    }

    private static function skipped(string $label): void {
        echo "  • {$label} ... \033[2mskipped\033[0m" . PHP_EOL;
    }
}
