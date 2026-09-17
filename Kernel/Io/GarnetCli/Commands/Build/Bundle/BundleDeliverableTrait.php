<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\Bundle {
    use Phar;

    /**
     * Чем выкладка заканчивается: архив, phar и скрипт удаления.
     *
     * phar самораспаковывающийся и умеет отдать по отдельности любой из
     * четырёх каталогов; uninstall.sh автономен и не требует php на хосте —
     * обе вещи существуют ровно потому, что на чужом хосте может не быть
     * ничего, кроме tar и sh.
     */
    trait BundleDeliverableTrait {
        /**
         * Pack the unpacked bundle dir into a self-executing PHP Phar
         * archive. The phar carries a stub that lets the user pick which
         * of the three sibling directories to extract (interactively or
         * via flags), with overwrite enabled by default — so the same
         * phar serves both first-time install and incremental updates
         * (e.g. ship a new framework dir without touching docroot/upload).
         *
         * Requires `phar.readonly=0` at build time. End users don't need
         * any special ini setting to execute the phar.
         */
        private static function buildPhar(
            string $src,
            string $pharFile,
            string $publicDir,
            string $fwDir,
            string $appDir,
            string $runtimeDir,
            string $appName,
        ): void {
            if (file_exists($pharFile)) {
                @unlink($pharFile);
            }

            $phar = new Phar($pharFile, 0, basename($pharFile));
            $phar->startBuffering();
            $phar->buildFromDirectory($src);
            $phar->setStub(self::renderPharStub($publicDir, $fwDir, $appDir, $runtimeDir, $appName));
            $phar->stopBuffering();

            // gzip every file inside — typical Garnet bundle compresses ~2x.
            if (Phar::canCompress(Phar::GZ)) {
                $phar->compressFiles(Phar::GZ);
            }
            @chmod($pharFile, 0o755);
        }

        /**
         * Phar stub: parses CLI flags, lists or extracts the requested
         * sibling dirs into the directory next to the phar (with overwrite).
         * No special ini settings needed on the host to run.
         */
        private static function renderPharStub(string $publicDir, string $fwDir, string $appDir, string $runtimeDir, string $appName): string {
            $q = static fn (string $s): string => "'" . str_replace("'", "\\'", $s) . "'";
            $publicQ = $q($publicDir);
            $fwQ = $q($fwDir);
            $appQ = $q($appDir);
            $runtimeQ = $q($runtimeDir);
            $nameQ = $q($appName);
            $date = date('Y-m-d H:i:s');

            return <<<PHP
#!/usr/bin/env php
<?php
// Garnet bundle phar — generated on {$date}
// Run:  php <this-file.phar> [--all | --public | --framework | --app | --list | --help]
// Without flags drops into an interactive picker.

Phar::mapPhar();

\$APP         = {$nameQ};
\$PUBLIC_DIR  = {$publicQ};
\$FW_DIR      = {$fwQ};
\$APP_DIR     = {$appQ};
\$RUNTIME_DIR = {$runtimeQ};

\$pharPath = __FILE__;
\$target   = getcwd() ?: dirname(\$pharPath);

\$args = \$_SERVER['argv'] ?? [];
array_shift(\$args);

\$pickAll = false;
\$pickPublic = false;
\$pickFw = false;
\$pickApp = false;
\$pickRuntime = false;
\$listOnly = false;
\$wantHelp = false;
\$noConfirm = false;

foreach (\$args as \$arg) {
    switch (\$arg) {
        case '--all':         \$pickAll = true; break;
        case '--public':      \$pickPublic = true; break;
        case '--framework':   \$pickFw = true; break;
        case '--app':         \$pickApp = true; break;
        case '--runtime':     \$pickRuntime = true; break;
        case '--list':        \$listOnly = true; break;
        case '--help':
        case '-h':            \$wantHelp = true; break;
        case '--yes':
        case '-y':            \$noConfirm = true; break;
        default:
            fwrite(STDERR, "Unknown arg: \$arg\\n");
            exit(2);
    }
}

if (\$wantHelp) {
    echo "Garnet deploy phar — {\$APP}\\n";
    echo "Usage: php " . basename(\$pharPath) . " [flags]\\n";
    echo "  --all          extract all three sibling directories (default in interactive mode)\\n";
    echo "  --public       extract only {\$PUBLIC_DIR}/\\n";
    echo "  --framework    extract only {\$FW_DIR}/\\n";
    echo "  --app          extract only {\$APP_DIR}/\\n";
    echo "  --runtime      extract only {\$RUNTIME_DIR}/\\n";
    echo "  --list         list files inside the phar\\n";
    echo "  --yes / -y     skip the confirmation prompt\\n";
    echo "  --help / -h    this message\\n";
    echo "Target dir: \$target (where this phar is invoked from)\\n";
    exit(0);
}

if (\$listOnly) {
    \$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('phar://' . \$pharPath));
    foreach (\$it as \$f) {
        echo str_replace('phar://' . \$pharPath . '/', '', \$f->getPathname()) . "\\n";
    }
    exit(0);
}

// Interactive picker if nothing requested
if (!\$pickAll && !\$pickPublic && !\$pickFw && !\$pickApp && !\$pickRuntime) {
    echo "Garnet deploy: {\$APP}\\n";
    echo "Target dir: \$target\\n";
    echo "Choose what to extract (overwrites existing files):\\n";
    echo "  1) all        — public + framework + app\\n";
    echo "  2) public     — {\$PUBLIC_DIR}/\\n";
    echo "  3) framework  — {\$FW_DIR}/\\n";
    echo "  4) app        — {\$APP_DIR}/\\n";
    echo "  5) runtime    — {\$RUNTIME_DIR}/\\n";
    echo "  q) quit\\n";
    echo "Enter one or more (space-separated, e.g. '3 4'): ";
    \$line = trim((string) fgets(STDIN));
    if (\$line === 'q' || \$line === '') exit(0);
    foreach (preg_split('/\\\\s+/', \$line) as \$p) {
        switch (\$p) {
            case '1': \$pickAll = true; break;
            case '2': \$pickPublic = true; break;
            case '3': \$pickFw = true; break;
            case '4': \$pickApp = true; break;
            case '5': \$pickRuntime = true; break;
        }
    }
    \$noConfirm = true; // already asked
}

\$selected = [];
if (\$pickAll) {
    \$selected = [\$PUBLIC_DIR, \$FW_DIR, \$APP_DIR, \$RUNTIME_DIR];
} else {
    if (\$pickPublic)  \$selected[] = \$PUBLIC_DIR;
    if (\$pickFw)      \$selected[] = \$FW_DIR;
    if (\$pickApp)     \$selected[] = \$APP_DIR;
    if (\$pickRuntime) \$selected[] = \$RUNTIME_DIR;
}
\$selected = array_values(array_unique(\$selected));

if (empty(\$selected)) {
    echo "Nothing selected.\\n";
    exit(0);
}

echo "Will extract into: \$target\\n";
foreach (\$selected as \$d) echo "  - \$d/\\n";

if (!\$noConfirm) {
    echo "Continue? Type YES to confirm: ";
    \$line = trim((string) fgets(STDIN));
    if (\$line !== 'YES') { echo "Aborted.\\n"; exit(1); }
}

\$phar = new Phar(\$pharPath);

// Build the explicit file list for each requested top-level dir.
// Phar::extractTo's "directory name" parameter is finicky across PHP
// versions and platforms (sometimes wants a leading slash, sometimes
// not, sometimes fails entirely). Walking the inner iterator and
// passing the exact list of relative file paths sidesteps all of that.
foreach (\$selected as \$d) {
    echo "  extracting \$d/...\\n";
    \$files = [];
    \$prefix = 'phar://' . \$pharPath . '/' . \$d;
    if (!is_dir(\$prefix)) {
        fwrite(STDERR, "  (skip: \$d not in archive)\\n");
        continue;
    }
    \$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\$prefix));
    \$strip = 'phar://' . \$pharPath . '/';
    \$stripFs = str_replace('\\\\', '/', \$strip);
    foreach (\$it as \$f) {
        if (!\$f->isFile()) continue;
        // Phar uses forward slashes internally; normalize Windows paths
        // BEFORE stripping the prefix (both sides need the same shape).
        \$p = str_replace('\\\\', '/', \$f->getPathname());
        \$files[] = substr(\$p, strlen(\$stripFs));
    }
    if (\$files) \$phar->extractTo(\$target, \$files, true);
}

// Always ship uninstall.sh alongside, if present.
if (file_exists('phar://' . \$pharPath . '/uninstall.sh')) {
    \$phar->extractTo(\$target, 'uninstall.sh', true);
    @chmod(\$target . DIRECTORY_SEPARATOR . 'uninstall.sh', 0755);
}

echo "Done.\\n";

__HALT_COMPILER();
PHP;
        }

        /**
         * Render a standalone uninstall.sh that knows the three sibling dir
         * names from this bundle. It removes whichever of them exist next to
         * itself, then deletes itself. Self-contained — no PHP needed on the
         * host. LF line endings (don't trip up `bash` on Linux).
         */
        private static function renderUninstallScript(string $publicDir, string $fwDir, string $appDir, string $runtimeDir, string $appName): string {
            $date = date('Y-m-d H:i:s');
            // bash single-quoted literals — pass dir names through addslashes
            // for ' just in case someone supplied weird --public-dir=foo'bar.
            $q = static fn (string $s): string => "'" . str_replace("'", "'\\''", $s) . "'";
            $publicQ = $q($publicDir);
            $fwQ = $q($fwDir);
            $appQ = $q($appDir);
            $runtimeQ = $q($runtimeDir);

            return <<<SH
#!/usr/bin/env bash
# Generated by `php garnet bundle` on {$date}
# Removes the three sibling directories this bundle installed
# (docroot, framework, app), relative to wherever this script lives.
#
# Usage:
#   bash uninstall.sh           # prompts before deleting
#   bash uninstall.sh --yes     # no prompt
#   bash uninstall.sh --dry-run # show what would happen, change nothing

set -euo pipefail

DIR="\$(cd "\$(dirname "\$0")" && pwd)"
APP_NAME={$appName}

DIRS=(
    {$publicQ}
    {$fwQ}
    {$appQ}
    {$runtimeQ}
)

YES=0
DRY=0
for arg in "\$@"; do
    case "\$arg" in
        --yes|-y)   YES=1 ;;
        --dry-run)  DRY=1 ;;
        *)
            echo "Unknown arg: \$arg" >&2
            echo "Usage: \$0 [--yes] [--dry-run]" >&2
            exit 2
            ;;
    esac
done

echo "Uninstalling \$APP_NAME bundle at: \$DIR"
echo "  will remove:"
for d in "\${DIRS[@]}"; do
    path="\$DIR/\$d"
    if [ -d "\$path" ]; then
        size=\$(du -sh "\$path" 2>/dev/null | cut -f1)
        echo "    - \$d  (\$size)"
    else
        echo "    - \$d  (missing)"
    fi
done

if [ "\$DRY" -eq 1 ]; then
    echo "(dry-run — nothing removed)"
    exit 0
fi

if [ "\$YES" -ne 1 ]; then
    printf "Type YES to confirm: "
    read -r answer
    if [ "\$answer" != "YES" ]; then
        echo "Aborted."
        exit 1
    fi
fi

for d in "\${DIRS[@]}"; do
    path="\$DIR/\$d"
    if [ -d "\$path" ]; then
        echo "  rm -rf \$d"
        rm -rf "\$path"
    fi
done

# Self-delete so the bundle's footprint is gone.
echo "  rm uninstall.sh"
rm -f "\$DIR/uninstall.sh"

echo "Done."

SH;
        }

        /**
         * Архив, если просили.
         *
         * Архивируется из chdir в целевой каталог и только относительными
         * путями: tar на Windows принимает «D:» за имя удалённого хоста и
         * падает, а BSD-tar не понимает даже --force-local.
         */
        private static function makeArchive(array $c): void {
            ['makeZip' => $makeZip, 'flatZip' => $flatZip, 'distRoot' => $distRoot, 'appName' => $appName, 'distApp' => $distApp] = $c;

            if ($makeZip) {
                $tarball = $distRoot . DS . $appName . '.tar.gz';
                echo PHP_EOL . "Creating archive: {$tarball}" . PHP_EOL;
                $cwd = getcwd();
                // On Windows, tar interprets `D:` in any path as a remote
                // host spec and dies. BSD-tar (Win10/11 default) doesn't
                // even accept --force-local. Cleanest fix: chdir to the
                // archive's target dir and pass only relative paths — that
                // way no colon ever reaches tar's argv. Works on every tar.
                $archiveName = $appName . '.tar.gz';

                if ($flatZip) {
                    // Pack the *contents* of dist/<App>/ — no wrapper dir.
                    // Distros extracting this archive get the sibling dirs
                    // (docroot, framework, app) straight into the cwd.
                    // chdir into distApp; archive sits one level up in distRoot.
                    chdir($distApp);
                    $relTarball = '..' . DS . $archiveName;
                    passthru('tar -czf ' . escapeshellarg($relTarball) . ' .', $code);
                } else {
                    chdir($distRoot);
                    passthru('tar -czf ' . escapeshellarg($archiveName) . ' ' . escapeshellarg($appName), $code);
                }
                chdir($cwd);

                if ($code === 0 && is_file($tarball)) {
                    echo '  Archive: ' . self::humanBytes(filesize($tarball)) . PHP_EOL;

                    if ($flatZip) {
                        echo '  Extract with:  tar -xzf ' . basename($tarball) . ' -C /target/dir' . PHP_EOL;
                    }
                } else {
                    echo "  Archive creation failed (exit {$code})" . PHP_EOL;
                }
            }
        }

        /**
         * Самораспаковывающийся phar — способ отдать бандл туда, где нет ничего,
         * кроме php.
         */
        private static function makePharFile(array $c): void {
            ['makePhar' => $makePhar, 'distRoot' => $distRoot, 'appName' => $appName, 'distApp' => $distApp, 'publicDirName' => $publicDirName, 'frameworkDirName' => $frameworkDirName, 'appDirName' => $appDirName, 'runtimeDirName' => $runtimeDirName] = $c;

            if ($makePhar) {
                $pharFile = $distRoot . DS . $appName . '.phar';
                echo PHP_EOL . "Creating phar: {$pharFile}" . PHP_EOL;
                self::buildPhar(
                    src: $distApp,
                    pharFile: $pharFile,
                    publicDir: $publicDirName,
                    fwDir: $frameworkDirName,
                    appDir: $appDirName,
                    runtimeDir: $runtimeDirName,
                    appName: $appName
                );

                if (is_file($pharFile)) {
                    echo '  Phar: ' . self::humanBytes(filesize($pharFile)) . PHP_EOL;
                    echo '  Run on host: php ' . basename($pharFile) . PHP_EOL;
                }
            }
        }

        /**
         * Распакованное дерево было черновиком: как только на диске есть
         * доставляемое, черновик убирается (--keep-dir оставляет).
         */
        private static function dropUnpackedTree(array $c): void {
            ['makeZip' => $makeZip, 'makePhar' => $makePhar, 'keepDir' => $keepDir, 'distApp' => $distApp] = $c;

            // Drop the unpacked dist/<App>/ tree once the deliverable
            // (zip and/or phar) is safely on disk — the tree was just
            // scratch space. Keep it with --keep-dir when debugging the
            // bundle layout.
            if (($makeZip || $makePhar) && is_dir($distApp)) {
                if (!$keepDir) {
                    self::rmrf($distApp);
                    echo "  removed unpacked dir: {$distApp}" . PHP_EOL;
                } else {
                    echo "  (kept unpacked dir at {$distApp} — --keep-dir)" . PHP_EOL;
                }
            }
        }
    }
}
