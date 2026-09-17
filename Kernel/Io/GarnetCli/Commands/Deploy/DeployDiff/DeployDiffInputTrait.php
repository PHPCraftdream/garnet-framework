<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\DeployDiff {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    /**
     * Что человек попросил выложить и что на это ответил git.
     *
     * Разбор флагов, раскладка хоста из ssh.ini/deploy.ini, превращение
     * селекторов в список коммитов и списка коммитов — в список файлов. Всё, что
     * здесь происходит, происходит локально: ни одного обращения к хосту.
     */
    trait DeployDiffInputTrait {
        /**
         * One-line description of what the run was asked to ship, for the journal
         * header. "which commits was this?" is the first question asked of a
         * deploy after the fact, and the answer has to survive the terminal.
         */
        private static function describeSelectors(array $opts): string {
            $parts = [];

            foreach (['since', 'from', 'after', 'range', 'branch'] as $key) {
                if (!empty($opts[$key])) {
                    $parts[] = "--{$key}={$opts[$key]}";
                }
            }

            foreach ((array)($opts['commits'] ?? []) as $sha) {
                $parts[] = "--commit={$sha}";
            }

            foreach ((array)($opts['files'] ?? []) as $file) {
                $parts[] = "--file={$file}";
            }

            if (!empty($opts['full_public'])) {
                $parts[] = '--full-public';
            }

            return $parts === [] ? '(none — resuming from remote deploy-sha)' : implode(' ', $parts);
        }

        private static function hasNoSelectors(array $opts): bool {
            return $opts['since'] === ''
                && $opts['from'] === ''
                && $opts['after'] === ''
                && $opts['range'] === ''
                && $opts['branch'] === ''
                && empty($opts['commits']);
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
                'no_log' => false,  // --no-log opts out of the run journal
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

                if ($arg === '--no-log') {
                    $opts['no_log'] = true;

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

        private static function commitExistsLocally(string $sha): bool {
            [$rc] = self::gitTry(['rev-parse', '--verify', "{$sha}^{commit}"]);

            return $rc === 0;
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

        /**
         * @return array{int, string}
         *
         * Also called by GarnetDeployFullCommand — unlike gitOut(), never
         * exit(1)s, so a caller that must stay non-fatal on a git failure (e.g.
         * after a deploy already fully succeeded) can check the return code
         * itself instead of the whole process dying on the way to reporting
         * success.
         */
        public static function gitTry(array $args): array {
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
    }
}
