<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use FilesystemIterator;
use Phar;
use PharData;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Full business-data snapshot of a deployed app — pulled to the local machine.
 *
 * Three commands, split across the two boxes:
 *
 *   php garnet snapshot:collect [--out=<dir>] [--with-uploads]   (runs ON the server)
 *       Gathers the irreplaceable, non-rebuildable parts into a staging dir:
 *         · db/        — fresh gzipped DB dump (via db:backup's dumper)
 *         · config/    — WorkDir/Config (db.ini, app.ini, email.ini, …)
 *         · logs/      — WorkDir/LogJournal + cron.log + public errors.log
 *         · upload-hidden/ — WorkDir/Upload (private, app-served files)   ┐ only with
 *         · upload-public/ — <docroot>/upload (public, web-served files)  ┘ --with-uploads
 *       Uploads are OPT-IN: they can be huge (user files) and a redeploy
 *       never touches them, so a routine db/config snapshot stays small.
 *       Prints `SNAPSHOT_DIR=<path>` as its last line.
 *
 *   php garnet snapshot:pack [<dir>] [--out=<archive>]   (runs ON the server)
 *       tar+gzip a collected staging dir into ONE `.tar.gz`. With no dir,
 *       packs the most recent snapshot. Prints `SNAPSHOT_ARCHIVE=<path>`.
 *
 *   php garnet snapshot:pull [--out=<dir>] [--keep]      (runs LOCALLY)
 *       Orchestrator: SSH → collect → pack, scp the archive down here, then
 *       delete the remote staging dir + archive (unless --keep). The end
 *       result is a single ready archive on the local machine.
 *
 * Rebuildable assets (framework code, built JS/CSS, caches) are intentionally
 * excluded — a snapshot is the stuff a redeploy can NOT regenerate.
 */
class GarnetSnapshotCommand {
    private const DIR_MARKER = 'SNAPSHOT_DIR=';

    private const ARCHIVE_MARKER = 'SNAPSHOT_ARCHIVE=';

    public static function run(string $command, array $args): void {
        match ($command) {
            'snapshot:collect' => self::collect($args),
            'snapshot:pack' => self::pack($args),
            'snapshot:pull' => self::pull($args),
            'snapshot:apply' => self::apply($args),
            'snapshot:up' => self::up($args),
            'snapshot:deploy' => self::deploy($args),
            default => self::help(),
        };

        exit(0);
    }

    // ── 1. collect (server-side) ─────────────────────────────────────────────

    private static function collect(array $args): void {
        [$link, $dbName, $workDir, $publicDir] = self::bootPaths();

        $stagingRoot = self::flagValue($args, '--out');
        $staging = $stagingRoot !== null && $stagingRoot !== ''
            ? rtrim($stagingRoot, '/\\')
            : $workDir . DS . 'Snapshots' . DS . 'snap_' . date('Ymd-His');

        self::mkdir($staging);

        echo "\033[1m=== Garnet snapshot:collect ===\033[0m" . PHP_EOL;
        echo "  staging: {$staging}" . PHP_EOL;

        $manifest = ['snapshot created: ' . date('c'), "database: {$dbName}", ''];

        // db — fresh gzipped dump straight into the staging dir.
        $dbFile = $staging . DS . 'db' . DS . $dbName . '.sql.gz';
        self::mkdir(dirname($dbFile));
        $stats = GarnetDbBackupCommand::dumpTo($link, $dbName, $dbFile);
        echo "  [db]            {$stats['tables']} tables, {$stats['rows']} rows -> " . self::human((int)filesize($dbFile)) . PHP_EOL;
        $manifest[] = "db/            {$stats['tables']} tables, {$stats['rows']} rows";

        // config / logs / uploads — copy the dirs verbatim.
        // Uploads (user files) can be huge and a redeploy doesn't touch them,
        // so they're OPT-IN: a routine DB/config snapshot stays small. Pass
        // --with-uploads to fold WorkDir/Upload + docroot/upload into the snap.
        $withUploads = in_array('--with-uploads', $args, true);
        $parts = [
            ['config',        $workDir . DS . 'Config',     'dir'],
            ['logs/LogJournal', $workDir . DS . 'LogJournal', 'dir'],
            ['logs/cron.log', $workDir . DS . 'cron.log',   'file'],
            ['logs/public-errors.log', $publicDir . DS . 'errors.log', 'file'],
        ];

        if ($withUploads) {
            $parts[] = ['upload-hidden', $workDir . DS . 'Upload',   'dir'];
            $parts[] = ['upload-public', $publicDir . DS . 'upload', 'dir'];
        } else {
            echo '  [skip]          uploads (pass --with-uploads to include WorkDir/Upload + docroot/upload)' . PHP_EOL;
            $manifest[] = 'upload-hidden  (skipped — no --with-uploads)';
            $manifest[] = 'upload-public  (skipped — no --with-uploads)';
        }

        foreach ($parts as [$label, $src, $kind]) {
            $dst = $staging . DS . $label;

            if (!file_exists($src)) {
                echo "  [skip]          {$label} (absent: {$src})" . PHP_EOL;
                $manifest[] = "{$label}    (absent)";

                continue;
            }
            self::mkdir(dirname($dst));
            $bytes = $kind === 'dir' ? self::copyTree($src, $dst) : self::copyFile($src, $dst);
            echo "  [{$kind}]" . str_repeat(' ', max(1, 11 - strlen($kind))) . "{$label} -> " . self::human($bytes) . PHP_EOL;
            $manifest[] = str_pad($label, 26) . self::human($bytes);
        }

        file_put_contents($staging . DS . 'MANIFEST.txt', implode(PHP_EOL, $manifest) . PHP_EOL);

        echo "\033[32m  collected.\033[0m" . PHP_EOL;
        // Machine-readable last line for snapshot:pull to parse.
        echo self::DIR_MARKER . $staging . PHP_EOL;
    }

    // ── 2. pack (server-side) ────────────────────────────────────────────────

    private static function pack(array $args): void {
        $dir = self::positional($args)[0] ?? null;

        if ($dir === null || $dir === '') {
            $dir = self::latestSnapshotDir();

            if ($dir === null) {
                echo "\033[31mError:\033[0m no snapshot dir given and none found under WorkDir/Snapshots." . PHP_EOL;

                exit(1);
            }
        }
        $dir = rtrim($dir, '/\\');

        if (!is_dir($dir)) {
            echo "\033[31mError:\033[0m not a directory: {$dir}" . PHP_EOL;

            exit(1);
        }

        $out = self::flagValue($args, '--out');
        $archive = $out !== null && $out !== '' ? $out : $dir . '.tar.gz';
        // PharData builds <base>.tar then compress() appends .gz.
        $tarPath = preg_replace('/\.gz$/', '', $archive);

        if ($tarPath === $archive) {
            $tarPath = $archive . '.tar';
            $archive = $tarPath . '.gz';
        }

        @unlink($tarPath);
        @unlink($archive);

        echo "\033[1m=== Garnet snapshot:pack ===\033[0m" . PHP_EOL;
        echo "  source:  {$dir}" . PHP_EOL;

        // PharData (data archive) is NOT blocked by phar.readonly, so this
        // works on locked-down shared hosting where exec() may be disabled.
        $phar = new PharData($tarPath);
        $phar->buildFromDirectory($dir);
        $phar->compress(Phar::GZ);
        unset($phar);
        @unlink($tarPath);

        if (!is_file($archive)) {
            echo "\033[31mError:\033[0m archive was not produced: {$archive}" . PHP_EOL;

            exit(1);
        }

        echo "\033[32m  archive:\033[0m {$archive} (" . self::human((int)filesize($archive)) . ')' . PHP_EOL;
        echo self::ARCHIVE_MARKER . $archive . PHP_EOL;
    }

    // ── 3. pull (local orchestrator) ─────────────────────────────────────────

    private static function pull(array $args): void {
        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();
        $remoteDir = self::resolveRemoteRuntimeDir();
        $keep = in_array('--keep', $args, true);

        $runOpts = ['cwd' => $remoteDir, 'stream' => false, 'tty' => false];

        echo "\033[1;36m[1/3 collect]\033[0m gathering snapshot on the server…" . PHP_EOL;
        // Forward --with-uploads so `snapshot:pull --with-uploads` can fold the
        // (potentially huge) upload dirs into the remote collect.
        $res = $client->run('php garnet snapshot:collect' . (in_array('--with-uploads', $args, true) ? ' --with-uploads' : ''), $runOpts);
        echo $res->stdout;

        if (!$res->ok()) {
            self::fail("remote collect failed (exit {$res->exitCode}).\n{$res->stderr}");
        }
        $stagingDir = self::parseMarker($res->stdout, self::DIR_MARKER);

        if ($stagingDir === null) {
            self::fail('could not read SNAPSHOT_DIR from remote collect output.');
        }

        echo "\033[1;36m[2/3 pack]\033[0m compressing on the server…" . PHP_EOL;
        $res = $client->run('php garnet snapshot:pack ' . self::remoteArg($stagingDir), $runOpts);
        echo $res->stdout;

        if (!$res->ok()) {
            self::fail("remote pack failed (exit {$res->exitCode}).\n{$res->stderr}");
        }
        $archive = self::parseMarker($res->stdout, self::ARCHIVE_MARKER);

        if ($archive === null) {
            self::fail('could not read SNAPSHOT_ARCHIVE from remote pack output.');
        }

        $outDir = self::flagValue($args, '--out');
        $outDir = $outDir !== null && $outDir !== '' ? rtrim($outDir, '/\\') : GARNET_ROOT . DS . 'snapshots';
        self::mkdir($outDir);
        $local = $outDir . DS . basename($archive);

        echo "\033[1;36m[3/3 download]\033[0m " . basename($archive) . " → {$local}" . PHP_EOL;
        $res = $client->get($archive, $local, ['stream' => true]);

        if (!$res->ok() || !is_file($local)) {
            self::fail("scp download failed (exit {$res->exitCode}).\n{$res->stderr}");
        }

        if (!$keep) {
            $client->run('rm -rf ' . self::remoteArg($stagingDir) . ' ' . self::remoteArg($archive), $runOpts);
        } else {
            fwrite(STDERR, "\033[33mNote:\033[0m --keep set; remote staging + archive left in place.\n");
        }

        echo PHP_EOL . "\033[32m=== snapshot ready ===\033[0m" . PHP_EOL;
        echo "  {$local} (" . self::human((int)filesize($local)) . ')' . PHP_EOL;
    }

    // ── 4. apply / up (restore a snapshot where this runs) ───────────────────

    /** Apply an already-unpacked snapshot dir to the local environment. */
    private static function apply(array $args): void {
        $dir = self::positional($args)[0] ?? null;

        if ($dir === null || !is_dir($dir)) {
            self::fail('snapshot:apply needs an unpacked snapshot dir. Use snapshot:up for an archive.');
        }
        self::applyDir(rtrim($dir, '/\\'), in_array('--with-config', $args, true), in_array('--with-uploads', $args, true));
    }

    /** Restore a snapshot from an archive (or dir) into the local environment. */
    private static function up(array $args): void {
        $src = self::positional($args)[0] ?? null;

        if ($src === null || $src === '' || !file_exists($src)) {
            self::fail('snapshot:up needs a snapshot archive (.tar.gz) or dir. Got: ' . ($src ?? '(none)'));
        }
        $withConfig = in_array('--with-config', $args, true);
        $withUploads = in_array('--with-uploads', $args, true);

        if (is_dir($src)) {
            self::applyDir(rtrim($src, '/\\'), $withConfig, $withUploads);

            return;
        }

        // Archive → extract to a temp dir, apply, then drop the temp.
        $tmp = self::resolveWorkDir() . DS . 'Snapshots' . DS . '_unpack_' . date('Ymd-His');
        self::mkdir($tmp);
        echo '  unpacking ' . basename($src) . ' …' . PHP_EOL;

        try {
            $phar = new PharData($src);
            $phar->extractTo($tmp, null, true);
            unset($phar);
            self::applyDir($tmp, $withConfig, $withUploads);
        } finally {
            self::rmTree($tmp);
        }
    }

    /**
     * Restore db (auto-snapshotting the current one first) + uploads, and —
     * only with --with-config — the environment config. Logs are never
     * restored. Upload dirs are merge-copied (overwrite + add, never delete).
     */
    private static function applyDir(string $dir, bool $withConfig, bool $withUploads): void {
        [$link, $dbName, $workDir, $publicDir] = self::bootPaths();

        echo "\033[1m=== Garnet snapshot:apply ===\033[0m" . PHP_EOL;
        echo "  source:   {$dir}" . PHP_EOL;
        echo "  database: {$dbName}" . PHP_EOL;

        // db — restore the dump, snapshotting the CURRENT db first (undoable).
        $dbFiles = array_merge((array)glob($dir . DS . 'db' . DS . '*.sql.gz'), (array)glob($dir . DS . 'db' . DS . '*.sql'));

        if (!empty($dbFiles)) {
            $safety = GarnetDbBackupCommand::autoBackup($link, $dbName, 'pre-apply');
            echo "  [db] current DB snapshotted → {$safety}" . PHP_EOL;
            $applied = GarnetDbBackupCommand::applyDump($link, $dbFiles[0]);
            echo "  [db] restored {$applied} statement(s) from " . basename($dbFiles[0]) . PHP_EOL;
        } else {
            echo '  [db] no dump in snapshot — skipped' . PHP_EOL;
        }

        // uploads — merge-copy over the live dirs. Opt-in (they can be huge and
        // overwrite live user files), mirroring --with-config.
        if ($withUploads) {
            $uploads = [
                ['upload-hidden', $dir . DS . 'upload-hidden', $workDir . DS . 'Upload'],
                ['upload-public', $dir . DS . 'upload-public', $publicDir . DS . 'upload'],
            ];

            foreach ($uploads as [$label, $src, $dst]) {
                if (!is_dir($src)) {
                    echo "  [skip] {$label} (not in snapshot)" . PHP_EOL;

                    continue;
                }
                $bytes = self::copyTree($src, $dst);
                echo "  [{$label}] → {$dst} (" . self::human($bytes) . ')' . PHP_EOL;
            }
        } else {
            echo '  [uploads] skipped (pass --with-uploads to overwrite live upload dirs)' . PHP_EOL;
        }

        // config — environment-specific (db creds, etc.); opt-in only.
        $cfgSrc = $dir . DS . 'config';

        if ($withConfig && is_dir($cfgSrc)) {
            $bak = $workDir . DS . 'Config.pre-apply-' . date('Ymd-His');

            if (is_dir($workDir . DS . 'Config')) {
                self::copyTree($workDir . DS . 'Config', $bak);
                echo "  [config] current config backed up → {$bak}" . PHP_EOL;
            }
            $bytes = self::copyTree($cfgSrc, $workDir . DS . 'Config');
            echo '  [config] → ' . $workDir . DS . 'Config (' . self::human($bytes) . ')' . PHP_EOL;
        } else {
            echo '  [config] skipped (pass --with-config to overwrite environment config)' . PHP_EOL;
        }

        echo "\033[32m  applied.\033[0m" . PHP_EOL;
    }

    // ── 5. deploy (local orchestrator: push a snapshot onto the server) ──────

    private static function deploy(array $args): void {
        $archive = self::positional($args)[0] ?? null;

        if ($archive === null || $archive === '' || !is_file($archive)) {
            self::fail('snapshot:deploy needs a local snapshot archive (.tar.gz). Got: ' . ($archive ?? '(none)'));
        }
        $withConfig = in_array('--with-config', $args, true);
        $withUploads = in_array('--with-uploads', $args, true);
        $keepRemote = in_array('--keep-remote', $args, true);

        self::bootApp();
        $client = SshClient::fromIniConfig();
        $client->validate();
        $remoteDir = self::resolveRemoteRuntimeDir();
        $runOpts = ['cwd' => $remoteDir, 'stream' => false, 'tty' => false];

        if (!in_array('--yes', $args, true) && !self::confirm($archive)) {
            echo 'Aborted.' . PHP_EOL;

            exit(1);
        }

        // 1. Safety: full snapshot of the CURRENT server state, archive kept.
        echo "\033[1;36m[1/4 safety]\033[0m snapshotting current server state…" . PHP_EOL;
        // Match the apply's upload scope so the rollback archive is complete.
        $res = $client->run('php garnet snapshot:collect' . ($withUploads ? ' --with-uploads' : ''), $runOpts);
        echo $res->stdout;

        if (!$res->ok()) {
            self::fail("remote safety collect failed (exit {$res->exitCode}).\n{$res->stderr}");
        }
        $safeDir = self::parseMarker($res->stdout, self::DIR_MARKER) ?? self::fail('no SNAPSHOT_DIR from safety collect.');
        $res = $client->run('php garnet snapshot:pack ' . self::remoteArg($safeDir), $runOpts);
        echo $res->stdout;

        if (!$res->ok()) {
            self::fail("remote safety pack failed (exit {$res->exitCode}).\n{$res->stderr}");
        }
        $rollback = self::parseMarker($res->stdout, self::ARCHIVE_MARKER) ?? self::fail('no SNAPSHOT_ARCHIVE from safety pack.');
        $client->run('rm -rf ' . self::remoteArg($safeDir), $runOpts);
        echo "\033[33m  rollback archive kept on server:\033[0m {$rollback}" . PHP_EOL;

        // 2. Upload the new snapshot.
        $remoteIncoming = $remoteDir . '/WorkDir/Snapshots/incoming_' . basename($archive);
        echo "\033[1;36m[2/4 upload]\033[0m " . basename($archive) . ' → server…' . PHP_EOL;
        $client->run('mkdir -p ' . self::remoteArg($remoteDir . '/WorkDir/Snapshots'), $runOpts);
        $res = $client->put($archive, $remoteIncoming, ['stream' => true]);

        if (!$res->ok()) {
            self::fail("upload failed (exit {$res->exitCode}).\n{$res->stderr}");
        }

        // 3. Apply on the server (snapshot:up unpacks + restores there).
        echo "\033[1;36m[3/4 apply]\033[0m restoring snapshot on the server…" . PHP_EOL;
        $cmd = 'php garnet snapshot:up ' . self::remoteArg($remoteIncoming)
            . ($withConfig ? ' --with-config' : '')
            . ($withUploads ? ' --with-uploads' : '');
        $res = $client->run($cmd, $runOpts);
        echo $res->stdout;

        if (!$res->ok()) {
            self::fail("remote apply failed (exit {$res->exitCode}).\n{$res->stderr}\n  Rollback: {$rollback}");
        }

        // 4. Cleanup the uploaded archive.
        echo "\033[1;36m[4/4 cleanup]\033[0m" . PHP_EOL;

        if (!$keepRemote) {
            $client->run('rm -f ' . self::remoteArg($remoteIncoming), $runOpts);
        }

        echo PHP_EOL . "\033[32m=== snapshot deployed to server ===\033[0m" . PHP_EOL;
        echo "  rollback (on server): \033[1mphp garnet snapshot:up " . $rollback . "\033[0m" . PHP_EOL;
    }

    private static function confirm(string $archive): bool {
        echo "\033[1;31mThis OVERWRITES the server's DB" . "\033[0m and uploads with:" . PHP_EOL;
        echo "  {$archive}" . PHP_EOL;
        echo 'The current server state is snapshotted first (rollback kept).' . PHP_EOL;
        echo "Type \033[1;36mdeploy\033[0m to proceed: ";

        return trim((string)fgets(STDIN)) === 'deploy';
    }

    // ── server-side helpers ──────────────────────────────────────────────────

    /**
     * Boot the app and resolve the data paths.
     * @return array{0: \PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink, 1: string, 2: string, 3: string}
     *               [link, dbName, workDir, publicDir]
     */
    private static function bootPaths(): array {
        $appName = GarnetEnv::requireAppName();
        self::bootApp();

        if (!(bool)DbPool::get()->getDbConfig()->paramInt('enabled')) {
            echo "\033[31mError:\033[0m database is disabled (db.ini → enabled = 1)." . PHP_EOL;

            exit(1);
        }
        $link = DbPool::get()->newLink();
        $dbName = (string)DbPool::get()->getDbConfig()->paramString('dbname');

        $workDir = self::resolveWorkDir();
        $publicDir = rtrim(GarnetEnv::getPublicDir($appName), '/\\');

        return [$link, $dbName, $workDir, $publicDir];
    }

    private static function latestSnapshotDir(): ?string {
        $dirs = glob(self::resolveWorkDir() . DS . 'Snapshots' . DS . 'snap_*', GLOB_ONLYDIR) ?: [];

        if (empty($dirs)) {
            return null;
        }
        usort($dirs, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $dirs[0];
    }

    private static function copyTree(string $src, string $dst): int {
        self::mkdir($dst);
        $bytes = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($it as $item) {
            /** @var SplFileInfo $item */
            $target = $dst . DS . $it->getSubPathName();

            if ($item->isDir()) {
                self::mkdir($target);
            } else {
                copy($item->getPathname(), $target);
                $bytes += (int)$item->getSize();
            }
        }

        return $bytes;
    }

    private static function copyFile(string $src, string $dst): int {
        self::mkdir(dirname($dst));
        copy($src, $dst);

        return (int)filesize($dst);
    }

    // ── local-orchestrator helpers ───────────────────────────────────────────

    private static function resolveRemoteRuntimeDir(): string {
        $deploy = IniConfig::deploy();
        $remotePath = rtrim($deploy->paramString('remote_path', ''), '/');
        $runtimeDir = trim($deploy->paramString('runtime_dir', ''), '/');

        if ($remotePath === '' || $runtimeDir === '') {
            self::fail('deploy.ini must define remote_path and runtime_dir.');
        }

        return $remotePath . '/' . $runtimeDir;
    }

    private static function parseMarker(string $stdout, string $marker): ?string {
        foreach (array_reverse(explode("\n", $stdout)) as $line) {
            $line = trim($line);

            if (str_starts_with($line, $marker)) {
                return trim(substr($line, strlen($marker)));
            }
        }

        return null;
    }

    /** Quote a remote (POSIX) path for the server's shell. */
    private static function remoteArg(string $path): string {
        return "'" . str_replace("'", "'\\''", $path) . "'";
    }

    // ── shared helpers ───────────────────────────────────────────────────────

    private static bool $appBooted = false;

    private static function bootApp(): void {
        if (self::$appBooted) {
            return;
        }
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            self::fail("app has no run_cmd.php at {$runCmd}");
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();
        self::$appBooted = true;
    }

    private static function resolveWorkDir(): string {
        $env = getenv('GARNET_WORKDIR_DIR');

        if ($env !== false && $env !== '') {
            return rtrim($env, '/\\');
        }

        return GarnetEnv::getAppDir(GarnetEnv::requireAppName()) . DS . 'WorkDir';
    }

    private static function rmTree(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private static function mkdir(string $dir): void {
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            self::fail("cannot create dir: {$dir}");
        }
    }

    private static function positional(array $args): array {
        return array_values(array_filter($args, static fn (string $a) => !str_starts_with($a, '-')));
    }

    private static function flagValue(array $args, string $flag): ?string {
        foreach ($args as $a) {
            if (str_starts_with($a, $flag . '=')) {
                return substr($a, strlen($flag) + 1);
            }
        }

        return null;
    }

    private static function human(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float)$bytes;

        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string)$bytes : number_format($n, 1)) . ' ' . $units[$i];
    }

    private static function fail(string $msg): never {
        fwrite(STDERR, "\033[31mError:\033[0m {$msg}\n");

        exit(1);
    }

    private static function help(): void {
        echo 'Usage:' . PHP_EOL;
        echo '  Pull (server → local):' . PHP_EOL;
        echo '    php garnet snapshot:pull [--out=<dir>] [--keep] [--with-uploads]   (LOCAL) collect+pack+download' . PHP_EOL;
        echo '    php garnet snapshot:collect [--out=<dir>] [--with-uploads]         (server) gather data into a staging dir' . PHP_EOL;
        echo '    php garnet snapshot:pack [<dir>] [--out=<file>]      (server) tar+gzip a staging dir' . PHP_EOL;
        echo '  Restore:' . PHP_EOL;
        echo '    php garnet snapshot:up <archive|dir> [--with-config] [--with-uploads]   restore a snapshot into THIS env' . PHP_EOL;
        echo '    php garnet snapshot:apply <dir> [--with-config] [--with-uploads]        apply an already-unpacked snapshot dir' . PHP_EOL;
        echo '    php garnet snapshot:deploy <archive> [--with-config] [--with-uploads] [--keep-remote] [--yes]' . PHP_EOL;
        echo '                                                          (LOCAL) push a snapshot onto the server' . PHP_EOL;
        echo '  db is always included; uploads only with --with-uploads (they can be huge); config only with' . PHP_EOL;
        echo '  --with-config; logs are collected but never restored.' . PHP_EOL;
    }
}
