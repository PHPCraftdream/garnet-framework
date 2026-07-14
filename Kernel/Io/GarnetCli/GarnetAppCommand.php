<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use PHPCraftdream\Garnet\Kernel\Core\Tools\FsTools;

class GarnetAppCommand {
    public static function run(string $command, array $args): void {
        match ($command) {
            'app' => self::showCurrent(),
            'app:list' => self::listApps(),
            'app:use' => self::useApp($args),
            'app:create' => self::createApp($args),
            default => self::help(),
        };

        exit(0);
    }

    private static function showCurrent(): void {
        $name = GarnetEnv::readAppName();

        if (empty($name)) {
            echo 'No active app. Run: php garnet app:use <AppName>' . PHP_EOL;
        } else {
            echo "Current app: {$name}" . PHP_EOL;
        }
    }

    private static function listApps(): void {
        $apps = GarnetEnv::listApps();
        $current = GarnetEnv::readAppName();

        echo 'Available apps:' . PHP_EOL;

        foreach ($apps as $app) {
            $marker = ($app === $current) ? ' (active)' : '';
            echo "  {$app}{$marker}" . PHP_EOL;
        }
    }

    private static function useApp(array $args): void {
        $name = $args[0] ?? '';

        if (empty($name)) {
            echo 'Usage: php garnet app:use <AppName>' . PHP_EOL;

            exit(1);
        }

        if (!preg_match('#^[A-Za-z_][A-Za-z0-9_]+$#', $name)) {
            echo "Invalid app name: \"{$name}\"" . PHP_EOL;

            exit(1);
        }

        $appDir = GARNET_ROOT . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($appDir)) {
            echo "App not found: Apps/{$name}" . PHP_EOL;
            echo PHP_EOL . 'Available apps:' . PHP_EOL;

            foreach (GarnetEnv::listApps() as $app) {
                echo "  {$app}" . PHP_EOL;
            }

            exit(1);
        }

        GarnetEnv::writeAppName($name);
        echo "Switched to: {$name}" . PHP_EOL;

        echo 'Running prepare...' . PHP_EOL;
        GarnetPrepareCommand::run([]);
    }

    /**
     * Scaffold a new Garnet application.
     *
     * Usage:
     *   php garnet app:create <Name> [--target=<dir>] [--no-install] [--quiet]
     *
     * The command copies the `Apps/Application` template (the canonical
     * starter), substitutes the namespace token `Application` for the
     * chosen name in file paths and contents, wires the new app's
     * composer.json to the Framework via a path repository (so a
     * `composer install` inside the new dir picks up the local checkout
     * verbatim), runs `composer install`, and prints next steps.
     *
     * Target directory:
     *   --target=<dir>   Explicit path. Resolved against cwd if relative.
     *                    Defaults to `<monorepo>/Apps/<Name>` when a sibling
     *                    Apps/ directory exists, or `./<Name>` otherwise.
     *
     * Examples:
     *   php garnet app:create MyApp
     *   php garnet app:create FixtureApp --target=Framework/tests/fixture-app
     *   php garnet app:create Demo      --target=/tmp/demo --no-install
     */
    private static function createApp(array $args): void {
        $opts = self::parseCreateOpts($args);
        $name = $opts['name'];

        if ($name === '') {
            echo 'Usage: php garnet app:create <AppName> [--target=<dir>] [--no-install] [--quiet]' . PHP_EOL;

            exit(1);
        }

        if (!preg_match('#^[A-Z][A-Za-z0-9_]+$#', $name)) {
            echo "Invalid app name: \"{$name}\" — must start with an uppercase letter (PascalCase)." . PHP_EOL;

            exit(1);
        }

        $ds = DIRECTORY_SEPARATOR;
        $template = 'Application';
        $sourceDir = self::resolveTemplateDir($template);

        if ($sourceDir === '') {
            echo "Template not found. Looked for Apps/{$template}/ next to the framework." . PHP_EOL;

            exit(1);
        }

        $destDir = self::resolveTargetDir($opts['target'], $name);

        if (is_dir($destDir)) {
            echo "Target directory already exists: {$destDir}" . PHP_EOL;

            exit(1);
        }

        $quiet = $opts['quiet'];

        if (!$quiet) {
            echo "Source:  {$sourceDir}" . PHP_EOL;
            echo "Target:  {$destDir}" . PHP_EOL;
        }

        // 1. Copy the template, substituting the name token in paths AND contents.
        FsTools::copyDirectory(
            $sourceDir,
            $destDir,
            function (string $src, string $dest) use ($name, $template): void {
                // Only rewrite text files; leave binaries (favicons, fonts) alone.
                if (!self::isTextFile($dest)) {
                    return;
                }
                $content = file_get_contents($dest);
                $newContent = str_replace($template, $name, $content);

                if ($newContent !== $content) {
                    file_put_contents($dest, $newContent);
                }
            },
            fn (string $src, string $dest) => str_replace($template, $name, $dest),
            true,
        );

        if (!$quiet) {
            echo 'Copied template.' . PHP_EOL;
        }

        // 2. Wire composer.json's framework dependency for the environment
        //    we're actually running in: a dev checkout keeps the path
        //    repository (rewritten to the real relative path), while an
        //    installed Packagist release drops `repositories` entirely and
        //    pins a version constraint instead — otherwise the generated
        //    app would forever depend on the bootstrap install's vendor/
        //    path and break the moment that directory is removed.
        self::configureFrameworkDependency($destDir, $quiet);

        // 3. Drop an .env so the local `garnet` wrapper resolves APP_NAME
        //    without the developer having to remember to copy .env.example.
        $envFile = $destDir . $ds . '.env';

        if (!file_exists($envFile)) {
            file_put_contents($envFile, "APP_NAME={$name}\n");

            if (!$quiet) {
                echo 'Wrote .env (APP_NAME).' . PHP_EOL;
            }
        }

        // 4. composer install — pulls the framework via the path-repo and
        //    drops a vendor/ + autoload into the new app.
        if (!$opts['noInstall']) {
            self::runComposerInstall($destDir, $quiet);

            // 4b. Normalise code style for the new name. The template ships
            //     import lists ordered for the literal "Application" token;
            //     renaming to <Name> can reshuffle alphabetical ordering, so
            //     run the formatter once now (the dev-deps just installed it)
            //     and the app is born `composer cs:check`-clean.
            self::runComposerCsFix($destDir, $quiet);
        }

        // 5. Print next steps.
        $rel = self::relativise($destDir);
        echo PHP_EOL;
        echo "App '{$name}' created at {$rel}." . PHP_EOL;
        echo 'Next steps:' . PHP_EOL;
        echo "  cd {$rel}" . PHP_EOL;
        echo '  # Edit WorkDir/Config/*.ini (DB credentials, base_url, etc.)' . PHP_EOL;
        echo '  php garnet migration' . PHP_EOL;
        echo '  php garnet build' . PHP_EOL;
        echo '  php garnet serve' . PHP_EOL;
    }

    /**
     * Parse the loosely-ordered argv tail into a typed option struct.
     * Positional first arg is the name; the rest can be flags.
     */
    private static function parseCreateOpts(array $args): array {
        $name = '';
        $target = '';
        $noInstall = false;
        $quiet = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--target=')) {
                $target = trim(substr($arg, 9), " \t\"'");
            } elseif ($arg === '--no-install') {
                $noInstall = true;
            } elseif ($arg === '--quiet' || $arg === '-q') {
                $quiet = true;
            } elseif ($name === '' && !str_starts_with($arg, '-')) {
                $name = $arg;
            }
        }

        return ['name' => $name, 'target' => $target, 'noInstall' => $noInstall, 'quiet' => $quiet];
    }

    /**
     * Locate the `Application` template. In a monorepo it sits at
     * `<sibling>/Apps/Application/` next to the framework; in a standalone
     * package install it ships under `Templates/Application/` inside the
     * package (planned). Returns '' when neither is found.
     */
    private static function resolveTemplateDir(string $name): string {
        $ds = DIRECTORY_SEPARATOR;
        $frameworkDir = GarnetRunner::$frameworkDir !== ''
            ? GarnetRunner::$frameworkDir
            : GARNET_ROOT;

        $candidates = [
            // Monorepo: Apps/Application sits beside Framework.
            dirname($frameworkDir) . $ds . 'Apps' . $ds . $name,
            // Standalone: a bundled copy under the framework package.
            $frameworkDir . $ds . 'Templates' . $ds . $name,
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Resolve the new app's directory. Honours --target= when given;
     * otherwise places the app under <monorepo>/Apps/<Name> if Apps/ exists
     * (i.e. we're in the monorepo), or ./<Name> as a final fallback.
     */
    private static function resolveTargetDir(string $explicit, string $name): string {
        $ds = DIRECTORY_SEPARATOR;

        if ($explicit !== '') {
            // Absolute or relative path; relative is resolved against cwd
            // so the user can run `app:create … --target=./fixture` and
            // get something predictable.
            return self::isAbsolutePath($explicit)
                ? rtrim($explicit, '/\\')
                : rtrim(getcwd() . $ds . $explicit, '/\\');
        }

        $frameworkDir = GarnetRunner::$frameworkDir !== ''
            ? GarnetRunner::$frameworkDir
            : GARNET_ROOT;
        $monorepoApps = dirname($frameworkDir) . $ds . 'Apps';

        if (is_dir($monorepoApps)) {
            return $monorepoApps . $ds . $name;
        }

        return getcwd() . $ds . $name;
    }

    /**
     * Wire the new app's `phpcraftdream/garnet-framework` dependency for
     * whichever environment this CLI is currently running in:
     *
     *  - Dev checkout (monorepo, or `garnet-framework` required via a path
     *    repository / dev branch): rewrite the template's path-repo URL to
     *    the real relative path from the new app dir to the framework
     *    checkout, keeping `@dev`. This is the existing scaffold behaviour.
     *  - Installed Packagist release (a real tagged version, e.g. `0.1.0`):
     *    drop the `repositories` entry entirely and pin `require` to a
     *    caret constraint on the installed version, so the generated app
     *    resolves the framework from Packagist and stays valid after the
     *    bootstrap install directory is deleted.
     */
    private static function configureFrameworkDependency(string $destDir, bool $quiet): void {
        $composerFile = $destDir . DIRECTORY_SEPARATOR . 'composer.json';

        if (!file_exists($composerFile)) {
            return;
        }

        $json = json_decode(file_get_contents($composerFile), true);

        if (!is_array($json)) {
            return;
        }

        $releaseVersion = self::installedReleaseVersion();

        if ($releaseVersion === null) {
            self::wireDevPathRepo($json, $destDir, $quiet);
        } else {
            self::wireReleaseDependency($json, $releaseVersion, $quiet);
        }

        file_put_contents(
            $composerFile,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Returns the installed framework's version string when it's a real
     * tagged release (e.g. "0.1.0"), or null when we're in a dev checkout
     * (monorepo, path-repo, or a `dev-*`/branch-alias install) — in which
     * case the caller should wire a path repository instead.
     */
    private static function installedReleaseVersion(): ?string {
        if (self::isLegacyMonorepo() || !class_exists(\Composer\InstalledVersions::class)
            || !\Composer\InstalledVersions::isInstalled('phpcraftdream/garnet-framework')) {
            return null;
        }

        $version = \Composer\InstalledVersions::getVersion('phpcraftdream/garnet-framework');

        if ($version === null || str_starts_with($version, 'dev-') || str_contains($version, '-dev')) {
            return null;
        }

        return $version;
    }

    /**
     * A monorepo dev checkout has `Framework/` sitting next to `Apps/` on
     * disk (see GarnetRunner's legacy-mode detection); an installed package
     * never does.
     */
    private static function isLegacyMonorepo(): bool {
        return is_dir(GARNET_ROOT . DIRECTORY_SEPARATOR . 'Framework' . DIRECTORY_SEPARATOR . 'FrontBuilder');
    }

    private static function wireDevPathRepo(array &$json, string $destDir, bool $quiet): void {
        $frameworkDir = GarnetRunner::$frameworkDir !== ''
            ? GarnetRunner::$frameworkDir
            : GARNET_ROOT;

        $relPath = self::relativePath($destDir, $frameworkDir);
        $touched = false;

        if (isset($json['repositories']) && is_array($json['repositories'])) {
            foreach ($json['repositories'] as $i => $repo) {
                if (($repo['type'] ?? '') === 'path') {
                    $json['repositories'][$i]['url'] = $relPath;
                    $touched = true;
                }
            }
        }

        if ($touched && !$quiet) {
            echo "Wired composer path-repo → {$relPath}" . PHP_EOL;
        }
    }

    private static function wireReleaseDependency(array &$json, string $version, bool $quiet): void {
        unset($json['repositories']);

        $constraint = '^' . $version;

        if (isset($json['require']['phpcraftdream/garnet-framework'])) {
            $json['require']['phpcraftdream/garnet-framework'] = $constraint;
        }

        if (!$quiet) {
            echo "Pinned phpcraftdream/garnet-framework: {$constraint} (Packagist release, no path repository)" . PHP_EOL;
        }
    }

    private static function runComposerInstall(string $destDir, bool $quiet): void {
        $cwd = getcwd();
        chdir($destDir);

        $cmd = 'composer install --no-interaction --no-progress 2>&1';

        if ($quiet) {
            $cmd .= ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        }

        if (!$quiet) {
            echo PHP_EOL . 'Running composer install…' . PHP_EOL;
        }

        passthru($cmd, $code);
        chdir($cwd);

        if ($code !== 0) {
            echo PHP_EOL . "composer install exited with code {$code}." . PHP_EOL;
            echo "Inspect the new app at {$destDir} and re-run `composer install` manually." . PHP_EOL;

            exit($code);
        }
    }

    /**
     * Run `composer cs:fix` in the new app so its code style is clean for the
     * substituted app name. Best-effort: a non-zero exit here is not fatal —
     * the app still works, the developer can run the formatter themselves.
     */
    private static function runComposerCsFix(string $destDir, bool $quiet): void {
        $cwd = getcwd();
        chdir($destDir);

        $redirect = PHP_OS_FAMILY === 'Windows' ? ' > NUL 2>&1' : ' > /dev/null 2>&1';
        $cmd = 'composer cs:fix --no-interaction' . $redirect;

        if (!$quiet) {
            echo 'Normalising code style (composer cs:fix)…' . PHP_EOL;
        }

        passthru($cmd);
        chdir($cwd);
    }

    private static function isTextFile(string $path): bool {
        // Conservative: only rewrite the file types where the `Application`
        // token reasonably appears as source code or config. Skip binaries.
        return (bool)preg_match(
            '#\.(php|tsx?|jsx?|css|less|sass|scss|html?|twig|md|json|ini|env|yml|yaml|neon|xml|bat|sh|conf|template)$#i',
            $path,
        ) || basename($path) === '.env' || basename($path) === '.env.example';
    }

    private static function isAbsolutePath(string $p): bool {
        if ($p === '') {
            return false;
        }

        if ($p[0] === '/' || $p[0] === '\\') {
            return true;
        }

        // Windows drive letter: C:\… / D:/…
        return (bool)preg_match('#^[A-Za-z]:[/\\\\]#', $p);
    }

    /**
     * POSIX-style relative path from $from to $to. Used to wire the
     * composer path repo without baking absolute paths into the new app's
     * composer.json (which would break if either tree is moved).
     */
    private static function relativePath(string $from, string $to): string {
        $from = str_replace('\\', '/', rtrim((string)realpath($from) ?: $from, '/'));
        $to = str_replace('\\', '/', rtrim((string)realpath($to) ?: $to,   '/'));

        $fromParts = explode('/', $from);
        $toParts = explode('/', $to);

        $common = 0;
        $max = min(count($fromParts), count($toParts));

        while ($common < $max && $fromParts[$common] === $toParts[$common]) {
            $common++;
        }

        // No shared ancestor at all. On POSIX both absolute paths share the
        // leading "" segment (root "/"), so this only happens on Windows when
        // the two trees live on different drives (C: vs D:) — where a relative
        // path is impossible (`..` can't cross a drive). Fall back to the
        // absolute target; composer accepts absolute path-repo URLs.
        if ($common === 0) {
            return $to;
        }

        $up = array_fill(0, count($fromParts) - $common, '..');
        $down = array_slice($toParts, $common);
        $rel = implode('/', array_merge($up, $down));

        return $rel === '' ? '.' : $rel;
    }

    /**
     * Try to print a friendly relative path. Falls back to the absolute
     * path if the target lives outside cwd.
     */
    private static function relativise(string $path): string {
        $cwd = str_replace('\\', '/', (string)getcwd());
        $abs = str_replace('\\', '/', $path);

        if (str_starts_with($abs, $cwd . '/')) {
            return substr($abs, strlen($cwd) + 1);
        }

        return $path;
    }

    private static function help(): void {
        echo 'App commands:' . PHP_EOL;
        echo '  app             Show current active app' . PHP_EOL;
        echo '  app:list        List available apps' . PHP_EOL;
        echo '  app:use <Name>  Switch active app' . PHP_EOL;
        echo '  app:create <N>  Create a new app from template' . PHP_EOL;
    }
}
