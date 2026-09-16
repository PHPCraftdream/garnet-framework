<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

/**
 * Append-only record of what a deploy did, written as it happens.
 *
 * Exists because a deploy that misbehaves leaves nothing to look at. A run
 * that appeared to hang for an hour and a half turned out to be spending all
 * of it in one remote directory walk — finding that out required hand-editing
 * the installed command to print timestamps, because the command itself kept
 * no record of its phases. Worse, the only output was stdout: piping it
 * through `tail` block-buffers, so a backgrounded deploy left an empty file
 * and no way to see where it stood.
 *
 * Hence the two properties this class exists for:
 *
 *  - every line is flushed to disk the moment it is written, so a run that is
 *    killed (or times out, or takes down the terminal with it) still leaves a
 *    readable tail describing how far it got;
 *  - phases carry durations, so "it was slow" becomes "it was slow HERE"
 *    without anyone having to patch the code first.
 *
 * Writing to the journal must never be the reason a deploy fails: every
 * filesystem operation here is best-effort and silently gives up. A deploy
 * that refuses to ship because it could not write its own log would be a
 * worse tool than one with no log at all.
 */
final class DeployJournal {
    /** Keys whose values are secrets and must never reach the file. */
    private const SECRET_KEYS = ['identity_key', 'identityKey', 'password', 'passphrase', 'token', 'opcache_token'];

    private string $file = '';

    private string $runId = '';

    private float $startedAt = 0.0;

    /** @var array<string, float> phase label => monotonic start */
    private array $openPhases = [];

    private bool $finished = false;

    private bool $enabled = true;

    public function __construct(string $dir, string $command, bool $enabled = true) {
        $this->enabled = $enabled;

        if (!$enabled) {
            return;
        }
        $this->runId = self::mintRunId();
        $this->startedAt = microtime(true);

        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            // Can't create the journal directory — carry on without a journal.
            $this->enabled = false;

            return;
        }
        $this->file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $this->write(self::formatHeader($this->runId, $command, date('Y-m-d H:i:s')));
    }

    public function runId(): string {
        return $this->runId;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * A short, sortable, collision-resistant run id. Time-prefixed so the
     * journal reads chronologically even when several runs interleave.
     */
    public static function mintRunId(): string {
        return date('His') . '-' . bin2hex(random_bytes(3));
    }

    /** Context lines for the run header: selectors, target, layout. */
    public function context(array $pairs): void {
        foreach (self::redactPairs($pairs) as $key => $value) {
            $this->write("  {$key}: {$value}");
        }
    }

    public function phaseStart(string $label): void {
        $this->openPhases[$label] = microtime(true);
        $this->write(self::formatPhaseStart($label, date('H:i:s')));
    }

    public function phaseEnd(string $label, string $detail = ''): void {
        $started = $this->openPhases[$label] ?? null;
        unset($this->openPhases[$label]);
        $seconds = $started === null ? 0.0 : microtime(true) - $started;
        $this->write(self::formatPhaseEnd($label, $seconds, $detail));
    }

    public function note(string $line): void {
        $this->write('  · ' . self::redactLine($line));
    }

    /**
     * Close the run. A run WITHOUT this line was interrupted — that absence is
     * the signal a later run reads to warn about a half-finished deploy, so
     * this must only ever be written on a genuine end.
     */
    public function finish(int $exitCode, string $summary = ''): void {
        if ($this->finished) {
            return;
        }
        $this->finished = true;
        $this->write(self::formatFooter(
            $this->runId,
            $exitCode,
            microtime(true) - $this->startedAt,
            $summary,
        ));
    }

    // ── pure formatting / redaction (specs cover these without any I/O) ──

    public static function formatHeader(string $runId, string $command, string $startedAt): string {
        return "=== run {$runId} | {$command} | started {$startedAt}";
    }

    public static function formatPhaseStart(string $label, string $at): string {
        return "  [{$at}] {$label} …";
    }

    public static function formatPhaseEnd(string $label, float $seconds, string $detail = ''): string {
        $line = sprintf('  [%s] %s done in %.2fs', date('H:i:s'), $label, $seconds);

        return $detail === '' ? $line : $line . ' — ' . self::redactLine($detail);
    }

    public static function formatFooter(string $runId, int $exitCode, float $seconds, string $summary = ''): string {
        $verdict = $exitCode === 0 ? 'ok' : "exit {$exitCode}";
        $line = sprintf('=== end %s | %s | %.2fs', $runId, $verdict, $seconds);

        return $summary === '' ? $line : $line . ' | ' . self::redactLine($summary);
    }

    /**
     * Strip anything that looks like key material or a credential.
     *
     * Deliberately blunt: a journal is read by whoever is debugging a deploy,
     * including from a pasted snippet in a chat or an issue, so the bar is
     * "cannot leak" rather than "usually does not". PEM blocks, `-i <path>`
     * arguments (the identity tempfile) and explicit secret-ish key=value
     * pairs all collapse to a marker.
     */
    public static function redactLine(string $line): string {
        $line = (string)preg_replace(
            '#-----BEGIN[^-]*PRIVATE KEY-----.*?-----END[^-]*PRIVATE KEY-----#s',
            '[redacted-key]',
            $line
        );
        $line = (string)preg_replace('#(-i)\s+\S+#', '$1 [redacted]', $line);
        $keys = implode('|', array_map(static fn (string $k): string => preg_quote($k, '#'), self::SECRET_KEYS));

        return (string)preg_replace('#\b(' . $keys . ')\s*[=:]\s*\S+#i', '$1=[redacted]', $line);
    }

    /**
     * @param array<string, scalar|null> $pairs
     * @return array<string, string>
     */
    public static function redactPairs(array $pairs): array {
        $out = [];

        foreach ($pairs as $key => $value) {
            $out[$key] = in_array($key, self::SECRET_KEYS, true)
                ? '[redacted]'
                : self::redactLine((string)$value);
        }

        return $out;
    }

    /**
     * Parse journal files back into run records, newest last.
     *
     * A run is delimited by its `=== run` header and, if it got that far, its
     * `=== end` footer. A record WITHOUT an end is an interrupted run — the
     * single most useful thing this file can tell you, because a deploy that
     * died between uploading assets and uploading the code that references
     * them leaves the host in a state nobody is told about.
     *
     * @return list<array{id: string, command: string, started: string, ended: bool, verdict: string, lines: list<string>}>
     */
    public static function readRuns(string $dir, int $limit = 0): array {
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.log') ?: [];
        sort($files);
        $runs = [];

        foreach ($files as $file) {
            foreach (explode("\n", (string)file_get_contents($file)) as $raw) {
                $line = rtrim($raw, "\r");

                if ($line === '') {
                    continue;
                }

                if (preg_match('#^=== run (\S+) \| ([^|]+) \| started (.+)$#', $line, $m)) {
                    $runs[] = [
                        'id' => $m[1],
                        'command' => trim($m[2]),
                        'started' => trim($m[3]),
                        'ended' => false,
                        'verdict' => 'INTERRUPTED',
                        'lines' => [],
                    ];

                    continue;
                }

                if ($runs === []) {
                    continue; // stray line before any header (hand-edited file)
                }
                $idx = array_key_last($runs);

                if (preg_match('#^=== end (\S+) \| ([^|]+)#', $line, $m)) {
                    // Concurrent runs interleave, so close the matching id, not
                    // simply the most recent header.
                    foreach (array_reverse(array_keys($runs)) as $i) {
                        if ($runs[$i]['id'] === $m[1]) {
                            $runs[$i]['ended'] = true;
                            $runs[$i]['verdict'] = trim($m[2]);

                            break;
                        }
                    }

                    continue;
                }
                $runs[$idx]['lines'][] = $line;
            }
        }

        return $limit > 0 ? array_slice($runs, -$limit) : $runs;
    }

    /**
     * Runs that never wrote an end line. Used to warn on the NEXT deploy that
     * a previous one stopped halfway, which is the moment the warning is
     * actually actionable.
     *
     * @return list<array{id: string, command: string, started: string, ended: bool, verdict: string, lines: list<string>}>
     */
    public static function findUnfinished(string $dir, ?string $exceptRunId = null): array {
        return array_values(array_filter(
            self::readRuns($dir),
            static fn (array $run): bool => !$run['ended'] && $run['id'] !== $exceptRunId,
        ));
    }

    /** How many files a (possibly interrupted) run reported as landed. */
    public static function landedCount(array $run): int {
        $n = 0;

        foreach ($run['lines'] as $line) {
            if (preg_match('#landed in .*?/: (.+)$#', $line, $m)) {
                $n += count(explode(', ', trim($m[1])));
            }
        }

        return $n;
    }

    private function write(string $line): void {
        if (!$this->enabled || $this->file === '') {
            return;
        }
        // LOCK_EX so concurrent deploys can't interleave mid-line; failure to
        // write is swallowed on purpose (see the class docblock).
        @file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
