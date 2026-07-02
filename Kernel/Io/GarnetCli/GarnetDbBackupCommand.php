<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use mysqli;
use mysqli_result;
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
use RuntimeException;

/**
 * Logical SQL backup / restore of the WHOLE current database.
 *
 *   php garnet db:backup                 → dump every table to WorkDir/Backups/<auto>.sql
 *   php garnet db:backup --out=<file>    → dump to a specific path
 *   php garnet db:restore <file>         → restore from a dump (current DB is
 *                                          auto-backed-up FIRST, then replaced)
 *   php garnet db:backup --list          → list existing backups
 *
 * Safety: a backup is taken automatically before a destructive op —
 *   - `db:wipe` calls {@see self::autoBackup()} before dropping tables;
 *   - `db:restore` backs up the live DB before applying the dump.
 * Pass `--no-backup` to a restore/wipe to skip the safety snapshot.
 *
 * Dump format: SQL, one statement per block, blocks separated by a sentinel
 * comment line ({@see self::BOUNDARY}), streamed through gzip at medium
 * compression ({@see self::GZIP_LEVEL}) into a `.sql.gz` archive. Restore
 * executes each block on its own (no `multi_query`), so a single oversized
 * dump never trips `max_allowed_packet`, and data containing `;` is never
 * mis-split — every value is `real_escape_string`-quoted onto a single line
 * (escaped newlines), so the sentinel can't collide with row content. Restore
 * auto-detects gzip by magic bytes, so legacy plain `.sql` dumps still load.
 */
class GarnetDbBackupCommand {
    /** Statement separator. A literal newline can't appear in escaped data. */
    private const BOUNDARY = "\n-- @@GARNET_STMT_BOUNDARY@@\n";

    /** gzip compression level: 6 = balanced (medium) speed/ratio. */
    private const GZIP_LEVEL = 6;

    /** Rows per INSERT batch — keeps individual statements well-bounded. */
    private const ROWS_PER_INSERT = 200;

    public static function run(string $command, array $args): void {
        match ($command) {
            'db:backup' => in_array('--list', $args, true) ? self::listBackups() : self::backupCmd($args),
            'db:restore' => self::restoreCmd($args),
            default => self::help(),
        };

        exit(0);
    }

    // ── Commands ─────────────────────────────────────────────────────────────

    private static function backupCmd(array $args): void {
        $out = self::flagValue($args, '--out');
        self::createBackup('manual', $out !== null && $out !== '' ? $out : null);
    }

    /**
     * Dump the whole DB to a gzip file and return its path. Unlike the CLI
     * entrypoint ({@see run()}) this does NOT call exit(), so in-process
     * callers — e.g. the deploy flow taking a pre-migration safety snapshot —
     * can keep running afterwards. Throws on failure (dumpTo).
     *
     * @return string absolute path to the written .sql.gz
     */
    public static function createBackup(string $reason = 'manual', ?string $out = null): string {
        [$link, $dbName] = self::boot();
        $path = $out !== null && $out !== '' ? $out : self::autoPath($reason);

        echo "\033[1m=== Garnet DB Backup ===\033[0m" . PHP_EOL;
        echo "  database: {$dbName}" . PHP_EOL;

        $stats = self::dumpTo($link, $dbName, $path);

        echo "\033[32m  backup written:\033[0m {$path}" . PHP_EOL;
        echo "  tables: {$stats['tables']}, rows: {$stats['rows']}, size: " . self::humanSize($stats['bytes']) . PHP_EOL;
        echo PHP_EOL . "  Restore with: \033[1mphp garnet db:restore " . self::shellArg($path) . "\033[0m" . PHP_EOL;

        return $path;
    }

    private static function restoreCmd(array $args): void {
        $positional = self::positional($args);
        $file = $positional[0] ?? null;

        if ($file === null || $file === '') {
            echo "\033[31mError:\033[0m specify a dump file: php garnet db:restore <file>" . PHP_EOL;
            self::listBackups();

            exit(1);
        }

        if (!is_file($file)) {
            echo "\033[31mError:\033[0m backup file not found: {$file}" . PHP_EOL;

            exit(1);
        }

        [$link, $dbName] = self::boot();

        echo "\033[1m=== Garnet DB Restore ===\033[0m" . PHP_EOL;
        echo "  database: {$dbName}" . PHP_EOL;
        echo "  source:   {$file}" . PHP_EOL;

        // Safety snapshot of the LIVE db before we overwrite it — so a restore
        // is itself undoable. Skip only on explicit --no-backup.
        if (!in_array('--no-backup', $args, true)) {
            $safety = self::autoPath('pre-restore');
            echo PHP_EOL . '  Backing up current DB first…' . PHP_EOL;
            $s = self::dumpTo($link, $dbName, $safety);
            echo "\033[32m  pre-restore snapshot:\033[0m {$safety} (" . self::humanSize($s['bytes']) . ')' . PHP_EOL;
        } else {
            echo "\033[33m  --no-backup: skipping the pre-restore snapshot.\033[0m" . PHP_EOL;
        }

        echo PHP_EOL . '  Applying dump…' . PHP_EOL;
        $applied = self::applyDump($link, $file);
        echo "\033[32m  restore done — {$applied} statement(s) applied from {$file}.\033[0m" . PHP_EOL;
    }

    private static function listBackups(): void {
        $dir = self::backupsDir();
        $files = is_dir($dir) ? array_merge((array)glob($dir . DS . '*.sql.gz'), (array)glob($dir . DS . '*.sql')) : [];
        echo "\033[1mBackups in {$dir}:\033[0m" . PHP_EOL;

        if (empty($files)) {
            echo '  (none)' . PHP_EOL;

            return;
        }
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        foreach ($files as $f) {
            echo '  ' . date('Y-m-d H:i:s', (int)filemtime($f)) . '  '
                . str_pad(self::humanSize((int)filesize($f)), 9) . '  ' . basename($f) . PHP_EOL;
        }
    }

    // ── Reusable API (called by db:wipe too) ─────────────────────────────────

    /**
     * Auto-named snapshot of the current DB for `$reason`
     * (e.g. 'pre-wipe', 'pre-restore'). Returns the written path.
     */
    public static function autoBackup(IDbMySQLiLink $link, string $dbName, string $reason): string {
        $path = self::autoPath($reason);
        self::dumpTo($link, $dbName, $path);

        return $path;
    }

    /**
     * Dump every table of `$dbName` (reachable through `$link`) to `$path`.
     * Streams rows to disk in batches, so memory stays bounded on big tables.
     *
     * @return array{tables:int, rows:int, bytes:int}
     */
    public static function dumpTo(IDbMySQLiLink $link, string $dbName, string $path): array {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create backup dir: {$dir}");
        }

        $mysqli = $link->getMysqli();
        // gzip, medium compression (level 6) — streamed, so the SQL is never
        // held in memory uncompressed. `db:restore` auto-detects the gzip
        // magic, so .sql.gz and legacy plain .sql both load.
        $fh = gzopen($path, 'wb' . self::GZIP_LEVEL);

        if ($fh === false) {
            throw new RuntimeException("Cannot open backup file for writing: {$path}");
        }

        $write = static function (string $stmt) use ($fh): void {
            gzwrite($fh, $stmt . self::BOUNDARY);
        };

        gzwrite($fh, "-- Garnet DB backup\n-- database: {$dbName}\n-- created: " . date('c') . "\n");
        $write('SET FOREIGN_KEY_CHECKS = 0');
        $write('SET NAMES utf8mb4');

        $tables = [];
        $res = $mysqli->query('SHOW TABLES');

        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_row()) {
                $tables[] = (string)$row[0];
            }
            $res->free();
        }

        $rowCount = 0;

        foreach ($tables as $table) {
            $qTable = '`' . str_replace('`', '``', $table) . '`';

            $write('DROP TABLE IF EXISTS ' . $qTable);

            // CREATE statement verbatim from the server.
            $createRes = $mysqli->query('SHOW CREATE TABLE ' . $qTable);

            if ($createRes instanceof mysqli_result) {
                $createRow = $createRes->fetch_assoc();
                $createRes->free();
                $create = (string)($createRow['Create Table'] ?? $createRow['Create View'] ?? '');

                if ($create !== '') {
                    $write($create);
                }
            }

            // Real (insertable) columns only — exclude GENERATED columns
            // (VIRTUAL/STORED), which the server recomputes and refuses to
            // accept in an INSERT. Buffered query, fully drained before the
            // streaming SELECT below.
            $columns = self::insertableColumns($mysqli, $dbName, $table);

            if (empty($columns)) {
                continue;
            }
            $selectList = implode(', ', array_map(
                static fn (string $c) => '`' . str_replace('`', '``', $c) . '`',
                $columns,
            ));
            $colList = $selectList;

            // Rows, streamed (USE_RESULT) and flushed in batches.
            $rowsRes = $mysqli->query('SELECT ' . $selectList . ' FROM ' . $qTable, MYSQLI_USE_RESULT);

            if (!($rowsRes instanceof mysqli_result)) {
                continue;
            }

            $batch = [];

            while ($row = $rowsRes->fetch_row()) {
                $vals = [];

                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : "'" . $mysqli->real_escape_string((string)$v) . "'";
                }
                $batch[] = '(' . implode(', ', $vals) . ')';
                $rowCount++;

                if (count($batch) >= self::ROWS_PER_INSERT) {
                    $write('INSERT INTO ' . $qTable . ' (' . $colList . ') VALUES ' . implode(', ', $batch));
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $write('INSERT INTO ' . $qTable . ' (' . $colList . ') VALUES ' . implode(', ', $batch));
            }
            $rowsRes->free();
        }

        $write('SET FOREIGN_KEY_CHECKS = 1');
        gzclose($fh);

        return ['tables' => count($tables), 'rows' => $rowCount, 'bytes' => (int)filesize($path)];
    }

    /**
     * Column names for `$table` that an INSERT may target — i.e. everything
     * except VIRTUAL/STORED generated columns (the server recomputes those and
     * rejects an explicit value). Ordered by ordinal position.
     *
     * @return list<string>
     */
    private static function insertableColumns(mysqli $mysqli, string $dbName, string $table): array {
        $stmt = $mysqli->prepare(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
            . " AND (EXTRA IS NULL OR EXTRA NOT LIKE '%GENERATED%')"
            . ' ORDER BY ORDINAL_POSITION'
        );

        if ($stmt === false) {
            return [];
        }
        $stmt->bind_param('ss', $dbName, $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $cols = [];

        while ($res !== false && ($row = $res->fetch_row())) {
            $cols[] = (string)$row[0];
        }
        $stmt->close();

        return $cols;
    }

    /**
     * Read a dump file, transparently decompressing it when it's gzip
     * (detected by the 0x1f 0x8b magic — NOT the extension), so both .sql.gz
     * archives and legacy plain .sql dumps restore.
     */
    private static function readMaybeGzip(string $file): string {
        $fp = fopen($file, 'rb');

        if ($fp === false) {
            throw new RuntimeException("Cannot read backup file: {$file}");
        }
        $magic = fread($fp, 2);
        fclose($fp);

        $isGzip = strlen($magic) === 2 && $magic[0] === "\x1f" && $magic[1] === "\x8b";

        if (!$isGzip) {
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException("Cannot read backup file: {$file}");
            }

            return $sql;
        }

        $gz = gzopen($file, 'rb');

        if ($gz === false) {
            throw new RuntimeException("Cannot open gzip backup: {$file}");
        }
        $sql = '';

        while (!gzeof($gz)) {
            $chunk = gzread($gz, 1 << 20);

            if ($chunk === false) {
                gzclose($gz);

                throw new RuntimeException("Corrupt gzip backup: {$file}");
            }
            $sql .= $chunk;
        }
        gzclose($gz);

        return $sql;
    }

    /**
     * Execute every statement block in `$file` (a .sql or .sql.gz dump).
     * Returns the count applied. Public so snapshot:apply can reuse it.
     */
    public static function applyDump(IDbMySQLiLink $link, string $file): int {
        $sql = self::readMaybeGzip($file);

        $mysqli = $link->getMysqli();
        $applied = 0;

        foreach (explode(self::BOUNDARY, $sql) as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }
            // Strip leading comment-only lines (the file header).
            $block = preg_replace('/^(\s*--[^\n]*\n)+/', '', $block);
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (!$mysqli->query($block)) {
                throw new RuntimeException(
                    'Restore failed at statement #' . ($applied + 1) . ": {$mysqli->error}"
                    . "\n  SQL: " . substr($block, 0, 200)
                );
            }
            $applied++;
        }

        return $applied;
    }

    // ── Boot + helpers ───────────────────────────────────────────────────────

    /**
     * Boot the active app (so DbPool/IniConfig are wired) and open a link.
     * @return array{0: IDbMySQLiLink, 1: string} [link, dbName]
     */
    private static function boot(): array {
        $appName = GarnetEnv::requireAppName();
        $runCmd = GarnetEnv::getAppDir($appName) . DS . 'run_cmd.php';

        if (!file_exists($runCmd)) {
            echo "\033[31mError:\033[0m app has no run_cmd.php at {$runCmd}" . PHP_EOL;

            exit(1);
        }
        $GLOBALS['argv'] = [$runCmd, 'noop'];
        $GLOBALS['argc'] = 2;
        ob_start();
        require $runCmd;
        ob_end_clean();

        $isEnabled = (bool)DbPool::get()->getDbConfig()->paramInt('enabled');

        if (!$isEnabled) {
            echo "\033[31mError:\033[0m database is disabled in config (db.ini → enabled = 1)." . PHP_EOL;

            exit(1);
        }

        $link = DbPool::get()->newLink();
        $dbName = (string)DbPool::get()->getDbConfig()->paramString('dbname');

        return [$link, $dbName];
    }

    private static function backupsDir(): string {
        $appName = GarnetEnv::requireAppName();
        $envWorkDir = getenv('GARNET_WORKDIR_DIR');
        $workDir = $envWorkDir !== false && $envWorkDir !== ''
            ? rtrim($envWorkDir, '/\\')
            : GarnetEnv::getAppDir($appName) . DS . 'WorkDir';

        return $workDir . DS . 'Backups';
    }

    private static function autoPath(string $reason): string {
        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', $reason);

        return self::backupsDir() . DS . 'backup_' . date('Ymd-His') . '_' . $safeReason . '.sql.gz';
    }

    /** @return list<string> */
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

    private static function humanSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float)$bytes;

        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string)$bytes : number_format($n, 1)) . ' ' . $units[$i];
    }

    private static function shellArg(string $s): string {
        return preg_match('/[^A-Za-z0-9_\-.\/:\\\\]/', $s) ? '"' . $s . '"' : $s;
    }

    private static function help(): void {
        echo 'Usage:' . PHP_EOL;
        echo '  php garnet db:backup                   dump the whole DB to WorkDir/Backups/<auto>.sql' . PHP_EOL;
        echo '  php garnet db:backup --out=<file>      dump to a specific path' . PHP_EOL;
        echo '  php garnet db:backup --list            list existing backups' . PHP_EOL;
        echo '  php garnet db:restore <file>           restore (auto-backs-up current DB first)' . PHP_EOL;
        echo '  php garnet db:restore <file> --no-backup   restore WITHOUT the safety snapshot' . PHP_EOL;
    }
}
